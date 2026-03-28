<?php
function load_env_file(string $path): void
{
  if (!is_file($path)) {
    return;
  }

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($lines === false) {
    return;
  }

  foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
      continue;
    }

    $parts = explode('=', $trimmed, 2);
    if (count($parts) !== 2) {
      continue;
    }

    $key = trim($parts[0]);
    $value = trim($parts[1]);
    $value = trim($value, "\"'");

    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
  }
}

function env_value(string $key): ?string
{
  $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
  return ($value === false || $value === '') ? null : $value;
}

load_env_file(__DIR__ . '/.env');
