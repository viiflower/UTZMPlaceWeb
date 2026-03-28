<?php
require __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

// Requiere sesión
if (!isset($_SESSION['user']['id'])) {
  echo json_encode(["error" => "No autorizado"]);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$name  = trim($data['name'] ?? '');
$desc  = trim($data['description'] ?? '');
$price = (float)($data['price'] ?? 0);
$stock = (int)($data['stock'] ?? 0);
$categoryRaw = $data['category'] ?? '';

function normalize_text($t) {
  $t = strtolower($t);
  $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $t);
  if ($converted !== false && $converted !== null) {
    $t = $converted;
  }
  return preg_replace('/[^a-z0-9]+/', '', $t);
}

function resolve_category_id($val) {
  $map = [
    'electronica' => 1,
    'electronico' => 1,
    'electronic'  => 1,
    'papeleria' => 2,
    'vehiculos' => 3,
    'vehiculo' => 3,
    'electrodomesticos' => 4,
    'electrodomestico' => 4,
    'moda' => 5,
  ];
  if (is_numeric($val)) {
    $num = (int)$val;
    return $num > 0 ? $num : 0;
  }
  $norm = normalize_text((string)$val);
  return $map[$norm] ?? 0;
}

$category_id = resolve_category_id($categoryRaw);

if ($name === '' || $price <= 0 || $desc === '' || $stock <= 0) {
  echo json_encode(["error" => "Nombre, descripción, precio y stock son obligatorios"]);
  exit;
}
$allowNullCategory = true; // permite productos sin categoría en caso de que la lista no coincida
if ($category_id <= 0 && !$allowNullCategory) {
  echo json_encode(["error" => "Categoría inválida"]);
  exit;
}

$price_cents = (int) round($price * 100);
$seller_id = (int) $_SESSION['user']['id'];

try {
  $stmt = $pdo->prepare(
    "INSERT INTO products (name, description, price_cents, stock, seller_id, category_id) VALUES (?,?,?,?,?,?) RETURNING id"
  );
  $stmt->execute([$name, $desc, $price_cents, $stock, $seller_id, $category_id > 0 ? $category_id : null]);
  $product_id = (int)$stmt->fetchColumn();

  echo json_encode(["success" => true, "product_id" => $product_id]);
} catch (Exception $e) {
  echo json_encode(["error" => $e->getMessage()]);
}
