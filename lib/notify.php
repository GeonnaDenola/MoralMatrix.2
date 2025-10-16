<?php
// /MoralMatrix/lib/notify.php

if (!class_exists('Notify')) {
  class Notify {
    /**
     * Insert a notification row.
     * $data keys (all optional except target_role, title):
     *  - target_role: 'student'|'faculty'|'security'|'ccdu' (required)
     *  - target_user_id: string|null (only for student/faculty/security targeted notices)
     *  - type: 'info'|'success'|'warning'|'danger'  (default: 'info')
     *  - title: string (required)
     *  - body: string|null
     *  - url: string|null
     *  - violation_id: int|null
     *  - created_by: string|null
     * Returns inserted id (int).
     */
    public static function create(mysqli $conn, array $data): int {
      $allowedRoles = ['student','faculty','security','ccdu'];
      $allowedTypes = ['info','success','warning','danger'];

      $target_role    = strtolower((string)($data['target_role'] ?? ''));
      $target_user_id = $data['target_user_id'] ?? null;
      $type           = strtolower((string)($data['type'] ?? 'info'));
      $title          = (string)($data['title'] ?? '');
      $body           = $data['body'] ?? null;
      $url            = $data['url'] ?? null;
      $violation_id   = $data['violation_id'] ?? null;
      $created_by     = $data['created_by'] ?? null;

      if (!in_array($target_role, $allowedRoles, true)) {
        throw new InvalidArgumentException('Notify::create invalid target_role');
      }
      if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
      }
      if ($title === '') {
        throw new InvalidArgumentException('Notify::create requires title');
      }

      // Prepare insert
      $sql = "INSERT INTO notifications
              (target_role, target_user_id, type, title, body, url, violation_id, created_by)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
      $stmt = $conn->prepare($sql);

      // Cast nulls to proper types
      $target_user_id = ($target_user_id === '' ? null : $target_user_id);
      $body           = ($body === '' ? null : $body);
      $url            = ($url === '' ? null : $url);
      $violation_id   = ($violation_id === '' ? null : $violation_id);
      $created_by     = ($created_by === '' ? null : $created_by);

      // s s s s s s i s
      $stmt->bind_param(
        'ssssssis',
        $target_role,
        $target_user_id,
        $type,
        $title,
        $body,
        $url,
        $violation_id,   // can be null; MySQL will store NULL
        $created_by
      );
      $stmt->execute();
      $id = $conn->insert_id;
      $stmt->close();
      return (int)$id;
    }
  }
}
