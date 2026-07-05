<?php
// تصدير CSV (متوافق مع Excel العربي عبر BOM). يبثّ ويخرج.
function stream_csv(string $filename, array $headers, array $rows): void {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Pragma: no-cache');
  echo "\xEF\xBB\xBF"; // UTF-8 BOM
  $out = fopen('php://output', 'w');
  fputcsv($out, $headers);
  foreach ($rows as $r) fputcsv($out, $r);
  fclose($out);
  exit;
}
