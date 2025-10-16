<?php
// /MoralMatrix/notifications_api.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/../config.php';

$role    = strtolower($_SESSION['account_type'] ?? $_SESSION['actor_role'] ?? '');
$actorId = $_SESSION['actor_id'] ?? $_SESSION['user_id'] ?? '';

header('Content-Type: application/json');

if ($role === '') {
  http_response_code(401);
  echo json_encode(['error' => 'no-session-role']);
  exit;
}

$conn = new mysqli(
  $database_settings['servername'],
  $database_settings['username'],
  $database_settings['password'],
  $database_settings['dbname']
);
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['error'=>'db']);
  exit;
}

/* Scope which rows this user can see */
$where  = '';
$params = [];
$types  = '';

switch ($role) {
  case 'ccdu':
    $where = "target_role = 'ccdu'";
    break;
  case 'student':
    $where = "target_role = 'student' AND target_user_id = ?";
    $types = 's'; $params[] = $actorId;
    break;
  case 'faculty':
  case 'security':
    $where = "target_role = ? AND target_user_id = ?";
    $types = 'ss'; $params[] = $role; $params[] = $actorId;
    break;
  default:
    http_response_code(403);
    echo json_encode(['error'=>'forbidden']);
    exit;
}

/* POST = mark read / mark all (Option A: global read_at per row) */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (($_POST['mark'] ?? '') === 'all') {
    $sql = "UPDATE notifications SET read_at = NOW() WHERE $where AND read_at IS NULL";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode(['ok'=>false]); $conn->close(); exit; }
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute(); $stmt->close();
    echo json_encode(['ok'=>true]); $conn->close(); exit;
  }
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    $sql = "UPDATE notifications SET read_at = NOW() WHERE id=? AND $where AND read_at IS NULL";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { echo json_encode(['ok'=>false]); $conn->close(); exit; }
    $bindTypes = 'i' . $types;
    $bindVals  = array_merge([$id], $params);
    $stmt->bind_param($bindTypes, ...$bindVals);
    $stmt->execute(); $stmt->close();
    echo json_encode(['ok'=>true]); $conn->close(); exit;
  }
}

/* GET = fetch recent */
$limit = 10; // show 10 in dropdown
$sql = "SELECT id, type, title, body, url, created_at, read_at
        FROM notifications
        WHERE $where
        ORDER BY (read_at IS NULL) DESC, created_at DESC
        LIMIT ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['error'=>'prepare']);
  $conn->close();
  exit;
}
if ($types) {
  $bindTypes = $types . 'i';
  $bindVals  = array_merge($params, [$limit]);
  $stmt->bind_param($bindTypes, ...$bindVals);   // ✅ merged, single unpack
} else {
  $stmt->bind_param('i', $limit);
}
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($r = $res->fetch_assoc()) $items[] = $r;
$stmt->close();

/* Unread count */
$sql = "SELECT COUNT(*) AS c FROM notifications WHERE $where AND read_at IS NULL";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['error'=>'prepare']);
  $conn->close();
  exit;
}
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$cnt = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

echo json_encode(['unread'=>$cnt,'items'=>$items]);
$conn->close();
