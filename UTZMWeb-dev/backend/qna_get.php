<?php
require __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id <= 0) {
  echo json_encode(["error" => "product_id inválido"]);
  exit;
}

$sql = "
  SELECT q.id AS qid, q.text AS qtext, q.created_at AS qtime,
         u.id AS uid, u.full_name AS uname,
         a.id AS aid, a.text AS atext, a.created_at AS atime
  FROM questions q
  JOIN users u ON u.id = q.user_id
  LEFT JOIN answers a ON a.question_id = q.id
  WHERE q.product_id = ?
  ORDER BY q.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$product_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$out = [];
foreach ($rows as $r) {
  $qid = (int)$r['qid'];
  if (!isset($out[$qid])) {
    $out[$qid] = [
      'id' => $qid,
      'text' => $r['qtext'],
      'created_at' => $r['qtime'],
      'user' => [ 'id' => (int)$r['uid'], 'full_name' => $r['uname'] ],
      'answer' => null
    ];
  }
  if ($r['aid']) {
    $out[$qid]['answer'] = [
      'id' => (int)$r['aid'],
      'text' => $r['atext'],
      'created_at' => $r['atime']
    ];
  }
}

echo json_encode(array_values($out));
