<?php
require_once dirname(__DIR__).'/includes/config.php';
pi_require_perm('manage_users'); // admins only

$db = 'admin_abouut';

// Collect all pi_ tables
$tables = [];
$r = $mysqli->query("SHOW TABLES FROM `$db` LIKE 'pi_%'");
while ($row = $r->fetch_row()) $tables[] = $row[0];

$sql  = "-- PioneerIcons DB Export\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Tables: " . count($tables) . "\n\n";
$sql .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Structure
    $cr = $mysqli->query("SHOW CREATE TABLE `$table`");
    $row = $cr->fetch_assoc();
    $create = $row['Create Table'] ?? $row[array_key_last($row)];
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $sql .= $create . ";\n\n";

    // Data
    $dr = $mysqli->query("SELECT * FROM `$table`");
    if (!$dr || $dr->num_rows === 0) continue;

    $cols = [];
    $fi = $dr->fetch_fields();
    foreach ($fi as $f) $cols[] = '`'.$f->name.'`';
    $col_list = implode(',', $cols);

    $rows = [];
    while ($drow = $dr->fetch_row()) {
        $vals = [];
        foreach ($drow as $v) {
            $vals[] = $v === null ? 'NULL' : "'".$mysqli->real_escape_string($v)."'";
        }
        $rows[] = '('.implode(',', $vals).')';
        if (count($rows) >= 100) {
            $sql .= "INSERT INTO `$table` ($col_list) VALUES\n" . implode(",\n", $rows) . ";\n";
            $rows = [];
        }
    }
    if ($rows) {
        $sql .= "INSERT INTO `$table` ($col_list) VALUES\n" . implode(",\n", $rows) . ";\n";
    }
    $sql .= "\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

$filename = 'pioneericons_' . date('Ymd_His') . '.sql';
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sql));
echo $sql;
exit;
