<?php
ob_start();
session_start();
include("../_init.php");

if (!is_loggedin()) {
    ob_end_clean();
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => trans('error_login')));
    exit();
}

$corte_id = isset($_GET['corte_id']) ? (int)$_GET['corte_id'] : 0;
if (!$corte_id) {
    ob_end_clean();
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => 'ID de corte requerido'));
    exit();
}

// Obtener datos del corte con usuario
$stmt = db()->prepare("
    SELECT cc.*, u.username AS user_name
    FROM corte_caja cc
    LEFT JOIN users u ON u.id = cc.user_id
    WHERE cc.id = ? AND cc.store_id = ?
");
$stmt->execute(array($corte_id, store_id()));
$corte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$corte) {
    ob_end_clean();
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => 'Corte no encontrado'));
    exit();
}

// Impresora de tickets configurada para este store
$printer_id = store('receipt_printer');
$stmt = db()->prepare("
    SELECT p.*, p2s.path, p2s.ip_address, p2s.port
    FROM printers p
    LEFT JOIN printer_to_store p2s ON p.printer_id = p2s.pprinter_id
    WHERE p.printer_id = ?
");
$stmt->execute(array($printer_id));
$printer = $stmt->fetch(PDO::FETCH_OBJ);

if (!$printer) {
    ob_end_clean();
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => 'No hay impresora de tickets configurada para esta tienda'));
    exit();
}

$cpl = (int)($printer->char_per_line ?: 42);

// Helpers de formato de texto para el ticket
function cLine($len, $char = '-') {
    return str_repeat($char, $len) . "\n";
}
function cRow($label, $valStr, $cpl) {
    $space = $cpl - strlen($label) - strlen($valStr);
    return $label . str_repeat(' ', max(1, $space)) . $valStr . "\n";
}
function cMoney($val) {
    return '$' . number_format((float)$val, 2);
}

$dif    = (float)$corte['diferencia'];
$difStr = ($dif >= 0 ? '+$' : '-$') . number_format(abs($dif), 2);

$text = new stdClass();
$text->store_name = store('name') . "\n";
$text->header     = "CORTE DE CAJA\n";

$text->info  = "Fecha:          " . $corte['fecha'] . "\n";
$text->info .= "Hora del corte: " . substr($corte['hora_corte'], 11, 8) . "\n";
$text->info .= "Cajero:         " . ($corte['user_name'] ?: '---') . "\n";

$text->items  = cLine($cpl);
$text->items .= cRow("Saldo Apertura",   cMoney($corte['opening_balance']),  $cpl);
$text->items .= cRow("Ingreso Total",    cMoney($corte['today_income']),     $cpl);
$text->items .= cRow("  Efectivo",       cMoney($corte['ingreso_efectivo']), $cpl);
$text->items .= cRow("  T. Credito",     cMoney($corte['tarjeta_credito']),  $cpl);
$text->items .= cRow("  T. Debito",      cMoney($corte['tarjeta_debito']),   $cpl);
$text->items .= cRow("Gastos (-)",       cMoney($corte['total_expense']),    $cpl);
$text->items .= cLine($cpl, '=');
$text->items .= cRow("SALDO SISTEMA",    cMoney($corte['saldo_sistema']),    $cpl);
$text->items .= cLine($cpl);
$text->items .= cRow("Efectivo Contado", cMoney($corte['efectivo_contado']), $cpl);
$text->items .= cRow("DIFERENCIA",       $difStr,                            $cpl);

$text->footer = '';
if (!empty($corte['notas'])) {
    $text->footer .= "Notas: " . $corte['notas'] . "\n";
}
$text->footer .= "Impreso: " . date('d/m/Y H:i') . "\n";

$data             = new stdClass();
$data->printer    = $printer;
$data->logo       = '';
$data->text       = $text;
$data->cash_drawer = '';

try {
    $escpos = new Escpos();
    $bytes  = $escpos->get_receipt_bytes($data);

    // Extraer nombre de la impresora del path UNC (último segmento)
    $parts     = array_values(array_filter(explode('/', str_replace('\\', '/', $printer->path))));
    $shareName = end($parts) ?: $printer->path;

    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array(
        'success'      => true,
        'printer_name' => $shareName,
        'data'         => base64_encode($bytes),
    ));
} catch (Exception $e) {
    ob_end_clean();
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
}
exit();
