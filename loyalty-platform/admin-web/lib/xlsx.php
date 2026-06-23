<?php
// كاتب XLSX بسيط وصحيح (OOXML) بدون مكتبات — صفّ عناوين عريض + بيانات.
function _xlsx_col(int $i): string { // 0 -> A
  $s = '';
  for ($i = $i + 1; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
  return $s;
}
function _xlsx_cell(int $col, int $row, $val, int $style = 0): string {
  $ref = _xlsx_col($col) . $row;
  $st  = $style ? ' s="' . $style . '"' : '';
  if (is_int($val) || (is_string($val) && preg_match('/^-?\d+$/', $val) && strlen($val) < 15)) {
    return '<c r="' . $ref . '"' . $st . '><v>' . (int)$val . '</v></c>';
  }
  $t = htmlspecialchars((string)$val, ENT_QUOTES | ENT_XML1, 'UTF-8');
  return '<c r="' . $ref . '"' . $st . ' t="inlineStr"><is><t xml:space="preserve">' . $t . '</t></is></c>';
}

function xlsx_bytes(array $headers, array $rows): string {
  if (!class_exists('ZipArchive')) return ''; // المُتحقّق يتراجع لـ CSV
  $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
  $r = 1;
  $hcells = ''; foreach (array_values($headers) as $i => $h) $hcells .= _xlsx_cell($i, $r, $h, 1);
  $sheet .= '<row r="' . $r . '">' . $hcells . '</row>'; $r++;
  foreach ($rows as $row) {
    $cells = ''; foreach (array_values($row) as $i => $v) $cells .= _xlsx_cell($i, $r, $v, 0);
    $sheet .= '<row r="' . $r . '">' . $cells . '</row>'; $r++;
  }
  $sheet .= '</sheetData></worksheet>';

  $files = [
    '[Content_Types].xml' =>
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      . '<Default Extension="xml" ContentType="application/xml"/>'
      . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
      . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
      . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
    '_rels/.rels' =>
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
    'xl/workbook.xml' =>
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
      . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>',
    'xl/_rels/workbook.xml.rels' =>
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
      . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
      . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
    'xl/styles.xml' =>
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
      . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
      . '<borders count="1"><border/></borders>'
      . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
      . '<cellXfs count="2"><xf/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>',
    'xl/worksheets/sheet1.xml' => $sheet,
  ];

  $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
  $zip = new ZipArchive();
  $zip->open($tmp, ZipArchive::OVERWRITE);
  foreach ($files as $name => $content) $zip->addFromString($name, $content);
  $zip->close();
  $bytes = file_get_contents($tmp);
  @unlink($tmp);
  return $bytes;
}

function stream_xlsx(string $filename, array $headers, array $rows): void {
  $bytes = xlsx_bytes($headers, $rows);
  if ($bytes === '') { stream_csv(preg_replace('/\.xlsx$/', '.csv', $filename), $headers, $rows); }
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . strlen($bytes));
  echo $bytes;
  exit;
}
