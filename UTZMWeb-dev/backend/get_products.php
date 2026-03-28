<?php
require __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$category = (int) ($_GET['category'] ?? 0);
$availability = strtolower(trim($_GET['availability'] ?? 'in_stock'));
$sort = strtolower(trim($_GET['sort'] ?? 'recent'));

$whereParts = [];
$params = [];

if ($availability === 'in_stock' || $availability === '') {
  $whereParts[] = 'p.stock > 0';
} elseif ($availability === 'out_of_stock') {
  $whereParts[] = 'p.stock <= 0';
}

if ($category > 0) {
  $whereParts[] = 'p.category_id = ?';
  $params[] = $category;
}

if ($q !== '') {
  $tokens = preg_split('/\s+/', $q);
  $tokens = array_filter($tokens, fn($token) => $token !== '');
  if ($tokens) {
    $parts = [];
    foreach ($tokens as $token) {
      $parts[] = '(p.name ILIKE ? OR p.description ILIKE ? OR u.full_name ILIKE ? OR COALESCE(u.store_name, \'\') ILIKE ?)';
      $wildcard = '%' . $token . '%';
      $params[] = $wildcard;
      $params[] = $wildcard;
      $params[] = $wildcard;
      $params[] = $wildcard;
    }
    $whereParts[] = '(' . implode(' OR ', $parts) . ')';
  }
}

$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$orderBy = 'p.created_at DESC';
if ($sort === 'price_asc') {
  $orderBy = 'p.price_cents ASC, p.created_at DESC';
} elseif ($sort === 'price_desc') {
  $orderBy = 'p.price_cents DESC, p.created_at DESC';
} elseif ($sort === 'oldest') {
  $orderBy = 'p.created_at ASC';
}

$sql = "
  SELECT
    p.id,
    p.name,
    p.price_cents,
    p.stock,
    p.seller_id,
    p.category_id,
    p.created_at,
    COALESCE(u.store_name, u.full_name) AS seller_name,
    (SELECT url FROM product_images WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS image
  FROM products p
  LEFT JOIN users u ON u.id = p.seller_id
  $where
  ORDER BY $orderBy
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
