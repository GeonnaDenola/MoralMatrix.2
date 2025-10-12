<?php
/**
 * violation_hrs.php
 * Helper functions to compute community-service hours.
 *
 * Rule:
 *   - Each GRAVE (category contains 'grave' but NOT 'less') = 20 hours
 *   - Every 3 of all other categories (light/moderate/less grave/minor/blank) = 10 hours
 *
 * Returns:
 *   - communityServiceHours(...)    : int    (required hours)
 *   - communityServiceLogged(...)   : float  (sum of logged hours)
 *   - communityServiceRemaining(...): float  (max(required - logged, 0))
 */

/** Safe “has column” check (no prepared SHOW; sanitize identifiers) */
function _hasColumn(mysqli $conn, string $table, string $column): bool {
    // allow only [A-Za-z0-9_]
    $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') return false;

    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $res = $conn->query($sql);
    if (!$res) return false;
    $ok = ($res->num_rows > 0);
    $res->free();
    return $ok;
}

/** REQUIRED hours from violations */
function communityServiceHours(mysqli $conn, string $student_id): int {
    $hasStatus = _hasColumn($conn, 'student_violation', 'status');

    // Normalize category to handle NULL/blank
    $cat = "LOWER(TRIM(COALESCE(offense_category,'')))";

    $sql = "
        SELECT
          SUM(CASE
                WHEN ($cat LIKE '%grave%' AND $cat NOT LIKE '%less%') THEN 1
                ELSE 0
              END) AS grave_cnt,
          SUM(CASE
                -- everything NOT “grave but not less” is counted as non_grave (including blank)
                WHEN ($cat LIKE '%grave%' AND $cat NOT LIKE '%less%') THEN 0
                ELSE 1
              END) AS non_grave_cnt
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
    $res->free();
    $stmt->close();

    $grave    = (int)($row['grave_cnt'] ?? 0);
    $nonGrave = (int)($row['non_grave_cnt'] ?? 0);

    return (int)(($grave * 20) + (intdiv($nonGrave, 3) * 10));
}

/** LOGGED hours from community_service_entries (sum) */
function communityServiceLogged(mysqli $conn, string $student_id): float {
    // Early out if the table doesn’t exist
    $res = $conn->query("SHOW TABLES LIKE 'community_service_entries'");
    if (!$res || $res->num_rows === 0) { if ($res) $res->free(); return 0.0; }
    $res->free();

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
