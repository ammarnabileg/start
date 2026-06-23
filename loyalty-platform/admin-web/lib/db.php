<?php
// طبقة قاعدة البيانات (PDO + استعلامات مُحضّرة دائمًا).
function cfg(): array {
  static $c = null;
  if ($c === null) {
    $path = __DIR__ . '/../config.php';
    $c = file_exists($path) ? require $path : require __DIR__ . '/../config.sample.php';
  }
  return $c;
}

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $c = cfg();
  $pdo = new PDO($c['db_dsn'], $c['db_user'], $c['db_pass'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]);
  return $pdo;
}

function q(string $sql, array $p = []): PDOStatement {
  $st = db()->prepare($sql);
  $st->execute($p);
  return $st;
}
function one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function all(string $sql, array $p = []): array  { return q($sql, $p)->fetchAll(); }
function scalar(string $sql, array $p = []) {
  $r = q($sql, $p)->fetch(PDO::FETCH_NUM);
  return $r ? $r[0] : null;
}
