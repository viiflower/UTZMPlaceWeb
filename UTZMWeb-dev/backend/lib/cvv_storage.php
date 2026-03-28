<?php

function cvv_storage_dir(): string {
  static $dir = null;
  if ($dir !== null) {
    return $dir;
  }
  $dir = dirname(__DIR__) . '/storage/payment_cvv';
  return $dir;
}

function cvv_storage_ensure_dir(): string {
  $dir = cvv_storage_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  return $dir;
}

function cvv_storage_file(int $methodId): string {
  return cvv_storage_dir() . '/' . $methodId . '.json';
}

function cvv_storage_save(int $methodId, string $cvv): void {
  if ($methodId <= 0 || $cvv === '') {
    return;
  }
  $dir = cvv_storage_ensure_dir();
  $payload = [
    'hash' => password_hash($cvv, PASSWORD_DEFAULT),
    'length' => strlen($cvv),
    'updated_at' => date('c')
  ];
  file_put_contents($dir . '/' . $methodId . '.json', json_encode($payload));
}

function cvv_storage_fetch(int $methodId): ?array {
  $file = cvv_storage_file($methodId);
  if (!is_file($file)) {
    return null;
  }
  $json = @file_get_contents($file);
  if ($json === false) {
    return null;
  }
  $data = json_decode($json, true);
  return is_array($data) ? $data : null;
}

function cvv_storage_verify(int $methodId, string $cvv): bool {
  $meta = cvv_storage_fetch($methodId);
  if (!$meta || empty($meta['hash'])) {
    return false;
  }
  return password_verify($cvv, $meta['hash']);
}

function cvv_storage_length(int $methodId): ?int {
  $meta = cvv_storage_fetch($methodId);
  return isset($meta['length']) ? (int)$meta['length'] : null;
}

