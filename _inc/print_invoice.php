<?php
ob_start();
session_start();
include("../_init.php");

if (!is_loggedin()) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => trans('error_login')));
    exit();
}

if (user_group_id() != 1 && !has_permission('access', 'pos_print')) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => trans('error_print_permission')));
    exit();
}

$invoice_id = isset($_GET['invoice_id']) ? trim($_GET['invoice_id']) : null;
if (!$invoice_id) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => 'Invoice ID requerido'));
    exit();
}

// Cargar datos de la factura
$invoice_model = registry()->get('loader')->model('invoice');
$invoice_info  = $invoice_model->getInvoiceInfo($invoice_id);
$invoice_items = $invoice_model->getInvoiceItems($invoice_id);

if (!$invoice_info) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => 'Factura no encontrada'));
    exit();
}

// Cargar impresora de tickets del store actual
$printer_id = store('receipt_printer');
$stmt = db()->prepare("SELECT p.*, p2s.path, p2s.ip_address, p2s.port
    FROM `printers` p
    LEFT JOIN `printer_to_store` p2s ON (p.`printer_id` = p2s.`pprinter_id`)
    WHERE p.`printer_id` = ?");
$stmt->execute(array($printer_id));
$printer = $stmt->fetch(PDO::FETCH_OBJ);

if (!$printer) {
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => 'No hay impresora de tickets configurada'));
    exit();
}

// Construir datos del recibo (igual que PrintReceiptModal.js)
$customerContact = '';
if (!empty($invoice_info['customer_mobile']) && $invoice_info['customer_mobile'] !== 'undefined') {
    $customerContact = $invoice_info['customer_mobile'];
} elseif (!empty($invoice_info['mobile_number']) && $invoice_info['mobile_number'] !== 'undefined') {
    $customerContact = $invoice_info['mobile_number'];
} else {
    $customerContact = !empty($invoice_info['customer_email']) ? $invoice_info['customer_email'] : '';
}

$text = new stdClass();
$text->store_name = store('name') . "\n";

$text->header  = store('address') . "\n";
$text->header .= store('mobile') . "\n\n";

$text->info  = "Fecha: "       . $invoice_info['created_at'] . "\n";
$text->info .= "Factura ID: "  . $invoice_info['invoice_id'] . "\n";
$text->info .= "Atendio: "     . ($invoice_info['by'] ?? '') . "\n\n";
$text->info .= "Cliente: "     . ($invoice_info['customer_name'] ?? '') . "\n";
$text->info .= "Contacto: "    . $customerContact . "\n\n";

$text->items = '';
$i = 1;
foreach ($invoice_items as $item) {
    $qty   = (float) $item['item_quantity'];
    $price = (float) $item['item_price'];
    $text->items .= "#" . $i++ . " " . $item['item_name'] . "\n";
    $text->items .= number_format($qty, 2) . " x " . number_format($price, 2)
                  . "  =  " . number_format($qty * $price, 2) . "\n";
}

$payable      = (float) ($invoice_info['payable_amount']  ?? 0);
$prev_due     = (float) ($invoice_info['previous_due']    ?? 0);
$paid         = (float) ($invoice_info['paid_amount']     ?? 0);
$prev_paid    = (float) ($invoice_info['prev_due_paid']   ?? 0);
$balance      = (float) ($invoice_info['balance']         ?? 0);
$return_amt   = (float) ($invoice_info['return_amount']   ?? 0);
$due          = (float) ($invoice_info['due']             ?? 0);
$order_tax    = (float) ($invoice_info['order_tax']       ?? 0);
$discount     = (float) ($invoice_info['discount_amount'] ?? 0);
$shipping     = (float) ($invoice_info['shipping_amount'] ?? 0);
$others       = (float) ($invoice_info['others_charge']   ?? 0);

$total_amount = $payable + $prev_due;
$paid_amount  = $paid + $prev_paid + ($balance - $return_amt);
$due_amount   = ($due + $prev_due) - $prev_paid;

$text->totals  = "\n";
$text->totals .= "Subtotal:        " . number_format($payable,      2) . "\n";
$text->totals .= "Impuesto:        " . number_format($order_tax,    2) . "\n";
$text->totals .= "Descuento:       " . number_format($discount,     2) . "\n";
$text->totals .= "Envio:           " . number_format($shipping,     2) . "\n";
$text->totals .= "Otros:           " . number_format($others,       2) . "\n";
$text->totals .= "Deuda anterior:  " . number_format($prev_due,     2) . "\n";
$text->totals .= "Total:           " . number_format($total_amount, 2) . "\n";
$text->totals .= "Pagado:          " . number_format($paid_amount,  2) . "\n";
$text->totals .= "Deuda:           " . number_format($due_amount,   2) . "\n";
$text->totals .= "Cambio:          " . number_format($balance,      2) . "\n";

$text->footer = !empty($invoice_info['invoice_note'])
    ? $invoice_info['invoice_note'] . "\n"
    : "Gracias por su compra.\n";

// Imprimir via ESC/POS
$data = new stdClass();
$data->printer    = $printer;
$data->logo       = '';   // skip logo: FCPATH no disponible en este contexto
$data->text       = $text;
$data->cash_drawer = '';

try {
    $escpos = new Escpos();
    $escpos->load($data->printer);
    $escpos->print_receipt($data);
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('success' => true));
} catch (Exception $e) {
    ob_end_clean();
    header('HTTP/1.1 422 Unprocessable Entity');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array('errorMsg' => $e->getMessage()));
}
