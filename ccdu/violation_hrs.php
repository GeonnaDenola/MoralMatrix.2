<?php
/**
 * violation_hrs.php
 * Helper functions to compute community-service hours.
 *
 * Rule:
 *   - Each GRAVE (category contains 'grave' but NOT 'less') = 20 hours
 *   - Every 3 of all other categories (light/moderate/less grave/minor/blank) = 10 hours
 *
 * Returned types:
 *   - Required: int (multiple of 10 or 20)
 *   - Logged: float (sum of validator entries)
 *   - Remaining: float (never negative)
 */

/** Small helper: does a table have a column? */
function _hasColumn(mysqli $conn, string $table, string $column): bool {
    $q = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if (!$q) return false;
    $q->bind_param("s", $column);
    $q->execute();
    $res = $q->get_result();
    $ok  = ($res && $res->num_rows > 0);
    $q->close();
    return $ok;
}

/**
 * REQUIRED hours from violations (robust).
 * Compatible with your old call site — you can keep using communityServiceHours($conn, $student_id).
 */
function communityServiceHours(mysqli $conn, string $student_id): int {
    $hasStatus = _hasColumn($conn, 'student_violation', 'status');

    // Count grave vs non-grave in SQL (case-insensitive), excluding void/canceled if status exists
    $sql = "
        SELECT
          SUM(CASE
                WHEN (LOWER(offense_category) LIKE '%grave%'
                      AND LOWER(offense_category) NOT LIKE '%less%')
                THEN 1 ELSE 0 END) AS grave_cnt,
          SUM(CASE
                WHEN NOT (LOWER(offense_category) LIKE '%grave%'
                          AND LOWER(offense_category) NOT LIKE '%less%')
                THEN 1 ELSE 0 END) AS non_grave_cnt
        FROM student_violation
        WHERE student_id = ?
    ";
    if ($hasStatus) {
        $sql .= " AND LOWER(status) NOT IN ('void','voided','canceled','cancelled')";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    $stmt->bind_param("s", $student_id);
    if (!$stmt->execute()) { $stmt->close(); return 0; }

    $res = $stmt->get_result();
    if (!$res) { $stmt->close(); return 0; }

    $row = $res->fetch_assoc() ?: ['grave_cnt' => 0, 'non_grave_cnt' => 0];
    $stmt->close();

    $grave     = (int)($row['grave_cnt'] ?? 0);
    $nonGrave  = (int)($row['non_grave_cnt'] ?? 0);

    // Apply the rule
    $required = ($grave * 20) + (intdiv($nonGrave, 3) * 10);

    return (int)$required;
}

/** LOGGED hours from community_service_entries (sum) */
function communityServiceLogged(mysqli $conn, string $student_id): float {
    // Early out if table is missing
    $chk = $conn->query("SHOW TABLES LIKE 'community_service_entries'");
    if (!$chk || $chk->num_rows === 0) { if ($chk) $chk->close(); return 0.0; }
    $chk->close();

    $sql = "SELECT COALESCE(SUM(hours),0) AS total FROM community_service_entries WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0.0;

    $stmt->bind_param("s", $student_id);
    if (!$stmt->execute()) { $stmt->close(); return 0.0; }

    $stmt->bind_result($sumHours);
    $stmt->fetch();
    $stmt->close();

    return (float)$sumHours;
}

/** REMAINING hours = REQUIRED − LOGGED (never negative) */
function communityServiceRemaining(mysqli $conn, string $student_id): float {
    $required = communityServiceHours($conn, $student_id);
    $logged   = communityServiceLogged($conn, $student_id);
    return max(0.0, (float)$required - (float)$logged);
}
