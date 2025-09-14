<?php
function communityServiceHours(mysqli $conn, string $student_id): int {
    $sql = "SELECT LOWER(TRIM(offense_category)) AS cat, COUNT(*) AS cnt
            FROM student_violation
            WHERE student_id = ?
            GROUP BY LOWER(TRIM(offense_category))";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    $stmt->bind_param("s", $student_id);
    if (!$stmt->execute()) { $stmt->close(); return 0; }

    $res = $stmt->get_result();
    if (!$res) { $stmt->close(); return 0; }

    $light = $moderate = $grave = 0;
    while ($row = $res->fetch_assoc()) {
        $cat = $row['cat'] ?? '';
        $cnt = (int)($row['cnt'] ?? 0);
        if ($cat === 'light')    $light    = $cnt;
        if ($cat === 'moderate') $moderate = $cnt;
        if ($cat === 'grave')    $grave    = $cnt;
    }
    $res->free(); $stmt->close();

    $totalMinor = $light + $moderate;               // every 3 minor => +10
    return intdiv($totalMinor, 3) * 10 + $grave*20; // each grave => +20
}
