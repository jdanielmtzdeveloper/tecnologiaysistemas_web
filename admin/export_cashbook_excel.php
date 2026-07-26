<?php
ob_start();
session_start();
include ("../_init.php");

// Redirect, If user is not logged in
if (!is_loggedin()) {
  redirect(root_url() . '/index.php?redirect_to=' . url());
}

// Redirect, If User has not Read Permission
if (user_group_id() != 1 && !has_permission('access', 'read_cashbook_report')) {
  redirect(root_url() . '/'.ADMINDIRNAME.'/dashboard.php');
}

$today = date('Y-m-d');

$from = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']))
  ? $_GET['from'] : $today;
$to_raw = (isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']))
  ? $_GET['to'] : $from;
$to = ($to_raw < $from) ? $from : $to_raw;
$is_range = ($from !== $to);
$q_to = $is_range ? $to : $from;

$store_id = store_id();

// ── Resumen ──────────────────────────────────────────────
$opening_balance  = get_opening_balance($from);
$today_income     = get_total_income($from, $q_to);
$tarjeta_credito  = get_pagos_tarjeta_credito($from, $q_to);
$tarjeta_debito   = get_pagos_tarjeta_debito($from, $q_to);
$ingreso_efectivo = max(0, $today_income - $tarjeta_credito - $tarjeta_debito);
$total_expense    = get_total_expense($from, $q_to);
$total_income     = (float)$opening_balance + (float)$today_income;
$cash_in_hand     = $total_income - $total_expense;

// ── Credit / Ingresos, agrupados por fuente (igual que report_income_daywise.php) ──
$where_income  = "bank_transaction_info.store_id = $store_id AND bank_transaction_info.transaction_type IN ('deposit')";
$where_income .= date_range_accounting_filter($from, $q_to);
$stmt = db()->prepare("SELECT bank_transaction_info.source_id
  FROM bank_transaction_info
  JOIN income_sources ON bank_transaction_info.source_id = income_sources.source_id
  JOIN bank_transaction_price ON bank_transaction_info.info_id = bank_transaction_price.info_id
  WHERE $where_income
  GROUP BY bank_transaction_info.source_id");
$stmt->execute();
$income_sources_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$income_list = array();
foreach ($income_sources_rows as $row) {
  $category = get_the_income_source($row['source_id']);
  $parent = '';
  if ($category['parent_id']) {
    $p = get_the_income_source($category['parent_id']);
    $parent = $p['source_name'] . ' > ';
  }
  $income_list[] = array(
    'title'  => $parent . $category['source_name'],
    'amount' => get_total_source_income($row['source_id'], $from, $q_to),
  );
}

// ── Debit / Gastos, agrupados por categoría (igual que report_expense_daywise.php) ──
$where_expense  = "bank_transaction_info.store_id = $store_id AND bank_transaction_info.transaction_type IN ('withdraw') AND bank_transaction_info.is_hide != 1";
$where_expense .= date_range_accounting_filter($from, $q_to);
$stmt = db()->prepare("SELECT bank_transaction_info.exp_category_id
  FROM bank_transaction_info
  JOIN expense_categorys ON bank_transaction_info.exp_category_id = expense_categorys.category_id
  JOIN bank_transaction_price ON bank_transaction_info.info_id = bank_transaction_price.info_id
  WHERE $where_expense
  GROUP BY bank_transaction_info.exp_category_id");
$stmt->execute();
$expense_categorys_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$expense_list = array();
foreach ($expense_categorys_rows as $row) {
  $category = get_the_expense_category($row['exp_category_id']);
  $parent = '';
  if ($category['parent_id']) {
    $p = get_the_expense_category($category['parent_id']);
    $parent = $p['category_name'] . ' > ';
  }
  $expense_list[] = array(
    'title'  => $parent . $category['category_name'],
    'amount' => get_total_category_expense($row['exp_category_id'], $from, $q_to),
  );
}

// ── Detalle de ventas (facturas) del período ──
$where_sell  = "selling_info.store_id = '$store_id' AND selling_info.status = 1";
$where_sell .= date_range_filter($from, $q_to);
$stmt = db()->prepare("SELECT selling_info.invoice_id, selling_info.created_at, selling_info.customer_id,
    selling_info.created_by, selling_info.payment_status,
    selling_price.subtotal, selling_price.discount_amount, selling_price.payable_amount,
    selling_price.paid_amount, selling_price.due
  FROM selling_info
  LEFT JOIN selling_price ON (selling_info.invoice_id = selling_price.invoice_id)
  WHERE $where_sell
  ORDER BY selling_info.created_at ASC");
$stmt->execute();
$sells = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Historial de cortes de caja del período ──
$stmt = db()->prepare("
  SELECT c.*, u.username AS user_name
  FROM corte_caja c
  LEFT JOIN users u ON u.id = c.user_id
  WHERE c.store_id = ? AND c.fecha BETWEEN ? AND ?
  ORDER BY c.fecha ASC, c.hora_corte ASC
");
$stmt->execute(array($store_id, $from, $to));
$cortes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Salida: archivo .xlsx real, con 3 hojas separadas ──
// Se construye a mano el paquete OOXML (Office Open XML) vía ZipArchive,
// porque el truco de "HTML multi-tabla" no crea hojas reales en Excel
// (todo el contenido termina en una sola pestaña).

function xlsx_col($n) {
  $s = '';
  while ($n > 0) {
    $m = ($n - 1) % 26;
    $s = chr(65 + $m) . $s;
    $n = intdiv($n - 1, 26);
  }
  return $s;
}
function xlsx_esc($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
// Estilos (índices en <cellXfs> de xl/styles.xml):
// 0 normal | 1 encabezado azul | 2 título navy | 3 fila total | 4 número | 5 número en fila total
function xc_str($v, $s = 0) { return array('t' => 's', 'v' => $v, 's' => $s); }
function xc_num($v, $s = 4) { return array('t' => 'n', 'v' => $v, 's' => $s); }
function xc_pad($s = 0)     { return array('t' => 's', 'v' => '', 's' => $s); }
function xc_banner($text, $ncols, $style) {
  $row = array(xc_str($text, $style));
  for ($i = 1; $i < $ncols; $i++) { $row[] = xc_pad($style); }
  return $row;
}
function xlsx_row_xml($cells, $rowIndex) {
  $xml = '<row r="' . $rowIndex . '">';
  $col = 1;
  foreach ($cells as $cell) {
    $ref = xlsx_col($col) . $rowIndex;
    $style = isset($cell['s']) ? ' s="' . (int)$cell['s'] . '"' : '';
    if ($cell['t'] === 's') {
      $xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc($cell['v']) . '</t></is></c>';
    } else {
      $val = ($cell['v'] === '' || $cell['v'] === null) ? '' : sprintf('%.2f', (float)$cell['v']);
      $xml .= '<c r="' . $ref . '"' . $style . '>' . ($val !== '' ? '<v>' . $val . '</v>' : '') . '</c>';
    }
    $col++;
  }
  $xml .= '</row>';
  return $xml;
}
function xlsx_sheet_xml($rows, $colWidths) {
  $cols = '<cols>';
  $i = 1;
  foreach ($colWidths as $w) {
    $cols .= '<col min="' . $i . '" max="' . $i . '" width="' . $w . '" customWidth="1"/>';
    $i++;
  }
  $cols .= '</cols>';
  $body = '';
  $r = 1;
  foreach ($rows as $row) {
    $body .= xlsx_row_xml($row, $r);
    $r++;
  }
  return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
    '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
    $cols . '<sheetData>' . $body . '</sheetData></worksheet>';
}

$period_label = $is_range ? ($from . ' a ' . $to) : $from;

// ── Hoja 1: LIBRO DE PAGO ──
$rows1 = array();
$rows1[] = xc_banner('LIBRO DE PAGO — ' . $period_label, 2, 2);
$rows1[] = array(xc_pad(), xc_pad());
$rows1[] = xc_banner('RESUMEN', 2, 1);
$rows1[] = array(xc_str('Saldo Apertura'), xc_num($opening_balance));
$rows1[] = array(xc_str('Ingreso Total'), xc_num($today_income));
$rows1[] = array(xc_str('  Ingreso Efectivo'), xc_num($ingreso_efectivo));
$rows1[] = array(xc_str('  Pagos con Tarjeta Crédito'), xc_num($tarjeta_credito));
$rows1[] = array(xc_str('  Pagos con Tarjeta Débito'), xc_num($tarjeta_debito));
$rows1[] = array(xc_str('Gastos'), xc_num($total_expense));
$rows1[] = array(xc_str('Saldo Final', 3), xc_num($cash_in_hand, 5));
$rows1[] = array(xc_pad(), xc_pad());
$rows1[] = xc_banner('CREDIT / INGRESOS', 2, 1);
$rows1[] = array(xc_str('Título', 1), xc_str('Cantidad', 1));
$income_total = 0;
foreach ($income_list as $row) {
  $income_total += (float)$row['amount'];
  $rows1[] = array(xc_str($row['title']), xc_num($row['amount']));
}
$rows1[] = array(xc_str('TOTAL', 3), xc_num($income_total, 5));
$rows1[] = array(xc_pad(), xc_pad());
$rows1[] = xc_banner('DEBIT / GASTOS', 2, 1);
$rows1[] = array(xc_str('Título', 1), xc_str('Cantidad', 1));
$expense_total = 0;
foreach ($expense_list as $row) {
  $expense_total += (float)$row['amount'];
  $rows1[] = array(xc_str($row['title']), xc_num($row['amount']));
}
$rows1[] = array(xc_str('TOTAL', 3), xc_num($expense_total, 5));

// ── Hoja 2: DETALLES VENTAS ──
$rows2 = array();
$rows2[] = xc_banner('DETALLE DE VENTAS — ' . $period_label, 10, 2);
$rows2[] = array_fill(0, 10, xc_pad());
$rows2[] = array(
  xc_str('Factura', 1), xc_str('Fecha', 1), xc_str('Cliente', 1), xc_str('Vendedor', 1), xc_str('Estado', 1),
  xc_str('Subtotal', 1), xc_str('Descuento', 1), xc_str('Total', 1), xc_str('Pagado', 1), xc_str('Adeudo', 1),
);
if (count($sells)) {
  $sell_subtotal = 0; $sell_discount = 0; $sell_total = 0; $sell_paid = 0; $sell_due = 0;
  foreach ($sells as $s) {
    $customer_name = get_the_customer($s['customer_id'], 'customer_name');
    $customer_name = $customer_name ? $customer_name : 'Público en general';
    $vendedor = get_the_user($s['created_by'], 'username');
    $estado = $s['payment_status'] == 'due' ? 'Adeudo' : 'Pagado';
    $sell_subtotal += (float)$s['subtotal'];
    $sell_discount += (float)$s['discount_amount'];
    $sell_total    += (float)$s['payable_amount'];
    $sell_paid     += (float)$s['paid_amount'];
    $sell_due      += (float)$s['due'];
    $rows2[] = array(
      xc_str($s['invoice_id']), xc_str($s['created_at']), xc_str($customer_name), xc_str($vendedor), xc_str($estado),
      xc_num($s['subtotal']), xc_num($s['discount_amount']), xc_num($s['payable_amount']),
      xc_num($s['paid_amount']), xc_num($s['due']),
    );
  }
  $rows2[] = array(
    xc_str('TOTAL (' . count($sells) . ' facturas)', 3), xc_pad(3), xc_pad(3), xc_pad(3), xc_pad(3),
    xc_num($sell_subtotal, 5), xc_num($sell_discount, 5), xc_num($sell_total, 5),
    xc_num($sell_paid, 5), xc_num($sell_due, 5),
  );
} else {
  $rows2[] = array_merge(array(xc_str('No hay ventas registradas para este período.')), array_fill(0, 9, xc_pad()));
}

// ── Hoja 3: CORTES DE CAJA ──
$rows3 = array();
$rows3[] = xc_banner('HISTORIAL DE CORTES DE CAJA — ' . $period_label, 12, 2);
$rows3[] = array_fill(0, 12, xc_pad());
$rows3[] = array(
  xc_str('Fecha', 1), xc_str('Hora', 1), xc_str('Saldo Apertura', 1), xc_str('Ingreso Total', 1),
  xc_str('Efectivo', 1), xc_str('Tarjetas', 1), xc_str('Gastos', 1), xc_str('Saldo Sistema', 1),
  xc_str('Efectivo Contado', 1), xc_str('Diferencia', 1), xc_str('Notas', 1), xc_str('Usuario', 1),
);
if (count($cortes)) {
  foreach ($cortes as $c) {
    $tarjetas = (float)$c['tarjeta_credito'] + (float)$c['tarjeta_debito'];
    $hora = $c['hora_corte'] ? substr($c['hora_corte'], 11, 8) : '';
    $rows3[] = array(
      xc_str($c['fecha']), xc_str($hora), xc_num($c['opening_balance']), xc_num($c['today_income']),
      xc_num($c['ingreso_efectivo']), xc_num($tarjetas), xc_num($c['total_expense']), xc_num($c['saldo_sistema']),
      xc_num($c['efectivo_contado']), xc_num($c['diferencia']), xc_str($c['notas']), xc_str($c['user_name']),
    );
  }
} else {
  $rows3[] = array_merge(array(xc_str('No hay cortes de caja registrados para este período.')), array_fill(0, 11, xc_pad()));
}

$sheet1Xml = xlsx_sheet_xml($rows1, array(34, 16));
$sheet2Xml = xlsx_sheet_xml($rows2, array(14, 18, 26, 16, 10, 13, 13, 13, 13, 13));
$sheet3Xml = xlsx_sheet_xml($rows3, array(12, 10, 14, 14, 13, 13, 13, 14, 15, 12, 30, 16));

$contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
  '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
  '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
  '<Default Extension="xml" ContentType="application/xml"/>' .
  '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
  '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
  '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
  '<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
  '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
  '</Types>';

$rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
  '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
  '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
  '</Relationships>';

$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
  '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
  '<sheets>' .
  '<sheet name="LIBRO DE PAGO" sheetId="1" r:id="rId1"/>' .
  '<sheet name="DETALLES VENTAS" sheetId="2" r:id="rId2"/>' .
  '<sheet name="CORTES DE CAJA" sheetId="3" r:id="rId3"/>' .
  '</sheets></workbook>';

$workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
  '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
  '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
  '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>' .
  '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>' .
  '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
  '</Relationships>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
  '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
  '<fonts count="3">' .
  '<font><sz val="11"/><name val="Calibri"/></font>' .
  '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .
  '<font><b/><sz val="11"/><name val="Calibri"/></font>' .
  '</fonts>' .
  '<fills count="5">' .
  '<fill><patternFill patternType="none"/></fill>' .
  '<fill><patternFill patternType="gray125"/></fill>' .
  '<fill><patternFill patternType="solid"><fgColor rgb="FF4472C4"/><bgColor indexed="64"/></patternFill></fill>' .
  '<fill><patternFill patternType="solid"><fgColor rgb="FF203864"/><bgColor indexed="64"/></patternFill></fill>' .
  '<fill><patternFill patternType="solid"><fgColor rgb="FFD9E1F2"/><bgColor indexed="64"/></patternFill></fill>' .
  '</fills>' .
  '<borders count="2">' .
  '<border><left/><right/><top/><bottom/><diagonal/></border>' .
  '<border><left style="thin"><color rgb="FFCCCCCC"/></left><right style="thin"><color rgb="FFCCCCCC"/></right><top style="thin"><color rgb="FFCCCCCC"/></top><bottom style="thin"><color rgb="FFCCCCCC"/></bottom><diagonal/></border>' .
  '</borders>' .
  '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
  '<cellXfs count="6">' .
  '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' .
  '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' .
  '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' .
  '<xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' .
  '<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' .
  '<xf numFmtId="4" fontId="2" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' .
  '</cellXfs>' .
  '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
  '</styleSheet>';

$tmpFile = tempnam(sys_get_temp_dir(), 'cbxlsx');
$zip = new ZipArchive();
$zip->open($tmpFile, ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', $contentTypesXml);
$zip->addFromString('_rels/.rels', $rootRelsXml);
$zip->addFromString('xl/workbook.xml', $workbookXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
$zip->addFromString('xl/styles.xml', $stylesXml);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheet1Xml);
$zip->addFromString('xl/worksheets/sheet2.xml', $sheet2Xml);
$zip->addFromString('xl/worksheets/sheet3.xml', $sheet3Xml);
$zip->close();

$filename = 'libro_pago_' . $from . ($is_range ? '_a_' . $to : '') . '.xlsx';

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');
readfile($tmpFile);
unlink($tmpFile);
exit();
