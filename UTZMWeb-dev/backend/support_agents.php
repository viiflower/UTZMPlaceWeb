<?php
require __DIR__ . '/support_common.php';

$user = support_require_user($pdo);
support_require_staff($user);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  support_json(['error' => 'Método no permitido'], 405);
}

$stmt = $pdo->query(
  "SELECT id, full_name, email, role
   FROM users
   WHERE role IN ('support', 'admin')
   ORDER BY CASE role WHEN 'admin' THEN 0 ELSE 1 END, full_name ASC, email ASC"
);

$agents = array_map(static function (array $row): array {
  return [
    'id' => (int)$row['id'],
    'full_name' => $row['full_name'],
    'email' => $row['email'],
    'role' => $row['role'],
  ];
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

support_json(['agents' => $agents]);
