<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['id'])) {
  echo json_encode(["error" => "No autorizado"]);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = (int)($data['product_id'] ?? 0);
$text = trim($data['text'] ?? '');
if ($product_id <= 0 || $text === '') {
  echo json_encode(["error" => "Datos inválidos"]);
  exit;
}

try {
  $stmt = $pdo->prepare("INSERT INTO questions (product_id, user_id, text) VALUES (?,?,?) RETURNING id");
  $stmt->execute([$product_id, (int)$_SESSION['user']['id'], $text]);
  $id = (int)$stmt->fetchColumn();
  echo json_encode(["success" => true, "id" => $id]);
} catch (Exception $e) {
  echo json_encode(["error" => $e->getMessage()]);
}
