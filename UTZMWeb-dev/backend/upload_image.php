<?php
require __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['imagen'])) {
  die(json_encode(["error" => "No se recibió archivo"]));
}

$product_id = $_POST['product_id'];

// Crear carpeta si no existe
$dir = "../public/uploads/";
if (!is_dir($dir)) {
  mkdir($dir, 0777, true);
}

$filename = time() . "_" . basename($_FILES['imagen']['name']);
$path = $dir . $filename;

if (move_uploaded_file($_FILES['imagen']['tmp_name'], $path)) {

  $url = "/public/uploads/" . $filename;

  // Guardar la URL en BD
  $stmt = $pdo->prepare("INSERT INTO product_images (product_id, url) VALUES (?, ?)");
  $stmt->execute([$product_id, $url]);

  echo json_encode(["success" => true, "url" => $url]);

} else {
  echo json_encode(["error" => "No se pudo mover el archivo"]);
}
