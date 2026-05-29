<?php
include ("../_init.php");

// Product Images
if($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'PRODUCTIMAGES') 
{
	try {
		$p_id = $request->get['p_id'];
		$images = get_product_images($p_id);
	    header('Content-Type: application/json');
	    echo json_encode(array('msg' => trans('text_success'), 'images' => $images));
	    exit();

	  } catch (Exception $e) { 
	    
	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	  }
}

// Banner Images
if($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'BANNERIMAGES') 
{
	try {
		$id = $request->get['id'];
		$images = get_banner_images($id);
	    header('Content-Type: application/json');
	    echo json_encode(array('msg' => trans('text_banner_images'), 'images' => $images));
	    exit();

	  } catch (Exception $e) { 
	    
	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	  }
}

// Quotation info
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'QUOTATIONINFO') 
{
	try {
		$ref_no = $request->post['ref_no'];
		$quotation_model = registry()->get('loader')->model('quotation');
		$quotation = $quotation_model->getQuotationInfo($ref_no);
		$quotation_items = $quotation_model->getQuotationItems($ref_no);
		$quotation['items'] = $quotation_items;
		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_success'), 'quotation' => $quotation));
		exit();

	} catch (Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Update POS tempalte content
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'UPDATEPOSTEMPALTECONTENT') 
{
	try {

		if (DEMO || (user_group_id() != 1 && !has_permission('access', 'receipt_template'))) {
	      throw new Exception(trans('error_update_permission'));
	    }

		$template_id = $request->post['template_id'];
		$content = $request->post['content'];
		$statement = db()->prepare("UPDATE `pos_templates` SET `template_content` = ? WHERE `template_id` = ?");
		$statement->execute(array($content, $template_id));

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_template_content_update_success')));
		exit();

	} catch (Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
	exit();
	}
}

// Update POS tempalte CSS
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'UPDATEPOSTEMPALTECSS') 
{
	try {
	    
	    if (DEMO || (user_group_id() != 1 && !has_permission('access', 'receipt_template'))) {
	      throw new Exception(trans('error_update_permission'));
	    }
	    
		$template_id = $request->post['template_id'];
		$content = $request->post['content'];
		$statement = db()->prepare("UPDATE `pos_templates` SET `template_css` = ? WHERE `template_id` = ?");
		$statement->execute(array($content, $template_id));

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_template_css_update_success')));
		exit();

	} catch (Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Update opening balance
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'UPDATEOPENINGBALANCE') 
{
	try {
		$balance = str_replace(',', '', $request->post['balance']);
		if (!is_numeric($balance)) {
			throw new Exception(trans('error_invalid_balance'));
		}

		// UPDATE OPENING BALANCE
		$from = date('Y-m-d');
		$day = date('d', strtotime($from));
		$month = date('m', strtotime($from));
		$year = date('Y', strtotime($from));
		$where_query = " DAY(`pos_register`.`created_at`) = $day";
		$where_query .= " AND MONTH(`pos_register`.`created_at`) = $month";
		$where_query .= " AND YEAR(`pos_register`.`created_at`) = $year";

		// If not exist then insert
		$statement = db()->prepare("SELECT `id` FROM `pos_register` WHERE $where_query AND `store_id` = ?");
		$statement->execute(array(store_id()));
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			$statement = db()->prepare("INSERT INTO `pos_register` SET `store_id` = ?, `created_at` = ?");
			$statement->execute(array(store_id(), date_time()));
		}

		$statement = db()->prepare("UPDATE `pos_register` SET `opening_balance` = ? WHERE $where_query AND `store_id` = ?");
		$statement->execute(array($balance, store_id()));

		// UPDATE CLOSING BALANCE
		$date = date('Y-m-d');
		$from = date( 'Y-m-d', strtotime( $date . ' -1 day' ) );
		$day = date('d', strtotime($from));
		$month = date('m', strtotime($from));
		$year = date('Y', strtotime($from));
		$where_query = " DAY(`pos_register`.`created_at`) = $day";
		$where_query .= " AND MONTH(`pos_register`.`created_at`) = $month";
		$where_query .= " AND YEAR(`pos_register`.`created_at`) = $year";
		$statement = db()->prepare("UPDATE `pos_register` SET `opening_balance` = ? WHERE $where_query AND `store_id` = ?");
		$statement->execute(array($balance, store_id()));

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_opening_balance_update_success')));
		exit();

	} catch (Exception $e) { 

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'PURCHASEITEM') 
{
	$sup_id = isset($request->post['sup_id']) ? $request->post['sup_id'] : null;
	$type = $request->post['type'];
	$name = $request->post['name_starts_with'];
	$query = "SELECT `p_id`, `p_name`, `p_code`, `category_id`, `unit_id`, `p2s`.`tax_method`, `p2s`.`purchase_price`, `p2s`.`sell_price`, `p2s`.`quantity_in_stock` 
		FROM `products` 
		LEFT JOIN `product_to_store` p2s ON (`products`.`p_id` = `p2s`.`product_id`)
		WHERE `p2s`.`store_id` = ? AND `p2s`.`status` = ? AND `p_type` != 'service'";
	if ($sup_id) {
		$query .= " AND `p2s`.`sup_id` = ?";
	}
	$query .= " AND (UPPER($type) LIKE '" . strtoupper($name) . "%' OR `p_code` = '{$name}') ORDER BY `p_id` DESC LIMIT 10";
	$statement = db()->prepare($query);
	if ($sup_id) {
		$statement->execute(array(store_id(), 1, $sup_id));
	} else {
		$statement->execute(array(store_id(), 1));
	}
	$products = $statement->fetchAll(PDO::FETCH_ASSOC);
	$data = array();
    foreach ($products as $product) {
    	$purchase_price = $product['purchase_price'];
    	$sell_price = $product['sell_price'];
    	$tax_amount = 0;
    	$tax_method = $product['tax_method'] ? $product['tax_method'] : 'exclusive';
    	$taxrate = 0;
    	$product_info = get_the_product($product['p_id']);
    	if ($product_info && $product_info['taxrate']) {
    		$taxrate = $product_info['taxrate']['taxrate'];
    		$tax_amount = ($product_info['taxrate']['taxrate'] / 100 ) * $purchase_price;
    	}
		$name = $product['p_id'].'|'.$product['p_name'].'|'.$product['p_code'].'|'.$product['category_id'].'|'.$product['quantity_in_stock'].'|'.get_the_unit($product['unit_id'],'unit_name').'|'.$purchase_price .'|'.$sell_price.'|'.$tax_amount.'|'.$tax_method.'|'.$taxrate.'|'.$product['quantity_in_stock'];
		array_push($data, $name);
    }
	echo json_encode($data);
	exit();
}

// Product list
if($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'SELLINGITEM') 
{
	$sup_id = isset($request->post['sup_id']) ? $request->post['sup_id'] : null;
	$type = $request->post['type'];
	$name = $request->post['name_starts_with'];
	$query = "SELECT `p_id`, `p_name`, `p_code`, `category_id`, `p2s`.`tax_method`, `p2s`.`purchase_price`, `p2s`.`sell_price`, `p2s`.`quantity_in_stock` 
		FROM `products` 
		LEFT JOIN `product_to_store` p2s ON (`products`.`p_id` = `p2s`.`product_id`)
		WHERE `p2s`.`store_id` = ? AND `p2s`.`status` = ?";
	if ($sup_id) {
		$query .= " AND `p2s`.`sup_id` = ?";
	}
	// $query .= " AND UPPER($type) LIKE '" . strtoupper($name) . "%' ORDER BY `p_id` DESC LIMIT 10";
	$query .= " AND (UPPER($type) LIKE '" . strtoupper($name) . "%' OR `p_code` = '{$name}') ORDER BY `p_id` DESC LIMIT 10";
	$statement = db()->prepare($query);
	if ($sup_id) {
		$statement->execute(array(store_id(), 1, $sup_id));
	} else {
		$statement->execute(array(store_id(), 1));
	}
	$products = $statement->fetchAll(PDO::FETCH_ASSOC);
	$data = array();
    foreach ($products as $product) {
    	$purchase_price = $product['purchase_price'];
    	$sell_price = $product['sell_price'];
    	$tax_amount = 0;
    	$tax_method = $product['tax_method'] ? $product['tax_method'] : 'exclusive';
    	$taxrate = 0;
    	$product_info = get_the_product($product['p_id']);
    	if ($product_info && $product_info['taxrate']) {
    		$taxrate = $product_info['taxrate']['taxrate'];
    		$tax_amount = ($product_info['taxrate']['taxrate'] / 100 ) * $sell_price;
    	}
		$name = $product['p_id'].'|'.$product['p_name'].'|'.$product['p_code'].'|'.$product['category_id'].'|'.$product['quantity_in_stock'].'|'.$purchase_price .'|'.$sell_price.'|'.$tax_amount.'|'.$tax_method.'|'.$taxrate;
		array_push($data, $name);
    }
	echo json_encode($data);
	exit();
}

// StockItems
if($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'STOCKITEMS') 
{
	try {
		$store_id = $request->get['store_id'] ? $request->get['store_id'] : store_id();
		$statement = db()->prepare("SELECT `purchase_item`.*, `purchase_info`.`inv_type` FROM `purchase_item` LEFT JOIN `purchase_info` ON (`purchase_item`.`invoice_id` = `purchase_info`.`invoice_id`) WHERE `purchase_item`.`store_id` = ? AND `purchase_item`.`item_quantity` > `purchase_item`.`total_sell` AND `purchase_item`.`status` IN ('stock','active') AND `purchase_info`.`inv_type` = ?");
	    $statement->execute(array($store_id, 'purchase'));
	    $products = $statement->fetchAll(PDO::FETCH_ASSOC);

	    header('Content-Type: application/json');
	    echo json_encode(array('msg' => trans('text_success'), 'products' => $products));
	    exit();

	  } catch (Exception $e) { 
	    
	    header('HTTP/1.1 422 Unprocessable Entity');
	    header('Content-Type: application/json; charset=UTF-8');
	    echo json_encode(array('errorMsg' => $e->getMessage()));
	    exit();
	  }
}

// StockItem
if($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'STOCKITEM') 
{
	try {
		$id = $request->get['id'];
		$quantity = $request->get['quantity'];
		$statement = db()->prepare("SELECT * FROM `purchase_item` WHERE `id` = ? AND `item_quantity` > `total_sell` AND `status` IN ('stock','active')");
		$statement->execute(array($id));
		$products = $statement->fetch(PDO::FETCH_ASSOC);

		header('Content-Type: application/json');
		echo json_encode(array('msg' => trans('text_success'), 'products' => $products));
		exit();

	} catch (Exception $e) {

		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Resumen del libro de pagos en HTML (carga dinámica) para cualquier fecha
if ($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'GETCASHBOOKSUMMARYHTML') {
	try {
		// Soporta día único (?from=X) y rango (?from=X&to=Y)
		$f_from = isset($request->get['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->get['from'])
			? $request->get['from'] : date('Y-m-d');
		$f_to   = isset($request->get['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->get['to'])
			? $request->get['to']   : $f_from;
		if ($f_to < $f_from) { $f_to = $f_from; }
		$is_range = ($f_from !== $f_to);

		// date_range_accounting_filter() ya añade 23:59:59 internamente cuando from != to.
		// Para rango: pasar $f_to como fecha simple → activa filtro BETWEEN correcto.
		// Para día único: $q_to = $f_from → from == to → activa filtro DAY/MONTH/YEAR.
		$q_to = $is_range ? $f_to : $f_from;

		$opening_balance  = get_opening_balance($f_from);  // saldo inicial del primer día
		$today_income     = get_total_income($f_from, $q_to);
		$tarjeta_credito  = get_pagos_tarjeta_credito($f_from, $q_to);
		$tarjeta_debito   = get_pagos_tarjeta_debito($f_from, $q_to);
		$ingreso_efectivo = max(0, $today_income - $tarjeta_credito - $tarjeta_debito);
		$total_expense    = get_total_expense($f_from, $q_to);
		$total_income     = (float)$opening_balance + (float)$today_income;
		$cash_in_hand     = $total_income - $total_expense;

		$fmt = function($n) { return number_format((float)$n, 2); };
		$lbl_income  = $is_range ? 'INGRESO TOTAL DEL PERÍODO' : trans('label_today_income');
		$lbl_expense = $is_range ? 'GASTOS DEL PERÍODO'        : trans('label_today_expense').' (-)';
		$lbl_closing = $is_range ? 'SALDO NETO DEL PERÍODO'    : trans('label_today_closing_balance');

		$html  = '<div class="table-responsive">';
		$html .= '<table class="table table-bordered table-striped mb-0"><tbody>';
		$html .= '<tr><td class="w-50 bg-gray text-right">'.trans('label_opening_balance').' <small>('.$f_from.')</small></td>';
		$html .= '<td class="w-50 bg-gray text-right">$'.$fmt($opening_balance).'</td></tr>';
		$html .= '<tr><td class="w-50 bg-gray text-right">'.$lbl_income.'</td>';
		$html .= '<td class="w-50 bg-gray text-right">$'.$fmt($today_income).'</td></tr>';
		$html .= '<tr><td class="w-50 text-right" style="background:#d9edf7;padding-left:30px;">&nbsp;&nbsp;<i class="fa fa-money"></i> INGRESO EFECTIVO</td>';
		$html .= '<td class="w-50 text-right" style="background:#d9edf7;">$'.$fmt($ingreso_efectivo).'</td></tr>';
		$html .= '<tr class="bg-green"><td class="w-50 text-right" style="padding-left:30px;">&nbsp;&nbsp;<i class="fa fa-credit-card"></i> PAGOS CON TARJETA CR&Eacute;DITO</td>';
		$html .= '<td class="w-50 text-right">$'.$fmt($tarjeta_credito).'</td></tr>';
		$html .= '<tr class="bg-green"><td class="w-50 text-right" style="padding-left:30px;">&nbsp;&nbsp;<i class="fa fa-credit-card-alt"></i> PAGOS CON TARJETA D&Eacute;BITO</td>';
		$html .= '<td class="w-50 text-right">$'.$fmt($tarjeta_debito).'</td></tr>';
		$html .= '<tr class="bg-blue"><td class="w-50 text-right">'.trans('label_total_income').'</td>';
		$html .= '<td class="w-50 text-right">$'.$fmt($total_income).'</td></tr>';
		$html .= '<tr class="bg-red"><td class="w-50 text-right">'.$lbl_expense.'</td>';
		$html .= '<td class="w-50 text-right">$'.$fmt($total_expense).'</td></tr>';
		$html .= '<tr class="bg-blue"><td class="w-50 text-right">'.trans('label_balance').' / '.trans('label_cash_in_hand').'</td>';
		$html .= '<td class="w-50 text-right">$'.$fmt($cash_in_hand).'</td></tr>';
		$html .= '<tr class="bg-yellow"><td class="w-50 text-right"><h4><b>'.$lbl_closing.'</b></h4></td>';
		$html .= '<td class="w-50 text-right"><h4><b>$'.$fmt($cash_in_hand).'</b></h4></td></tr>';
		$html .= '</tbody></table></div>';

		header('Content-Type: application/json');
		echo json_encode(array('html' => $html));
		exit();
	} catch (Exception $e) {
		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Get cashbook summary for Corte de Caja modal
if ($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'GETCASHBOOKSUMMARY') {
	try {
		$from = date('Y-m-d');

		// Totales acumulados del día completo
		$opening_balance  = get_opening_balance($from);
		$today_income     = get_total_income($from, $from);
		$tarjeta_credito  = get_pagos_tarjeta_credito($from, $from);
		$tarjeta_debito   = get_pagos_tarjeta_debito($from, $from);
		$ingreso_efectivo = max(0, $today_income - $tarjeta_credito - $tarjeta_debito);
		$total_expense    = get_total_expense($from, $from);
		$total_income     = $opening_balance + $today_income;
		$saldo_final      = $total_income - $total_expense;

		// Buscar el último corte registrado hoy para calcular incrementales
		$stmt = db()->prepare("
			SELECT hora_corte, today_income, ingreso_efectivo, tarjeta_credito,
			       tarjeta_debito, total_expense, saldo_sistema
			FROM corte_caja
			WHERE store_id = ? AND fecha = ?
			ORDER BY hora_corte DESC
			LIMIT 1
		");
		$stmt->execute(array(store_id(), $from));
		$last = $stmt->fetch(PDO::FETCH_ASSOC);

		// Efectivo esperado físico = Saldo Sistema − Tarjetas
		// Regla: la caja física contiene apertura + cobros en efectivo − gastos pagados en cash.
		// Los cobros con tarjeta no entran físicamente, así que se restan del saldo sistema.
		$tarjetas_total = (float)$tarjeta_credito + (float)$tarjeta_debito;
		$esperado_total = (float)$saldo_final - $tarjetas_total; // puede ser 0 o negativo

		if ($last) {
			// Valores incrementales desde el último corte
			$inc_income   = (float)$today_income    - (float)$last['today_income'];
			$inc_efectivo = (float)$ingreso_efectivo - (float)$last['ingreso_efectivo'];
			$inc_credito  = (float)$tarjeta_credito  - (float)$last['tarjeta_credito'];
			$inc_debito   = (float)$tarjeta_debito   - (float)$last['tarjeta_debito'];
			$inc_expense  = (float)$total_expense    - (float)$last['total_expense'];
			$inc_saldo    = (float)$saldo_final      - (float)$last['saldo_sistema'];
			$last_hora    = substr($last['hora_corte'], 11, 8);
			// Efectivo esperado incremental = Δ(Saldo − Tarjetas)
			$prev_esperado = (float)$last['saldo_sistema']
			               - ((float)$last['tarjeta_credito'] + (float)$last['tarjeta_debito']);
			$inc_esperado  = $esperado_total - $prev_esperado;
		} else {
			// Sin cortes previos: el esperado es el saldo efectivo del día completo
			$inc_income   = (float)$today_income;
			$inc_efectivo = (float)$ingreso_efectivo;
			$inc_credito  = (float)$tarjeta_credito;
			$inc_debito   = (float)$tarjeta_debito;
			$inc_expense  = (float)$total_expense;
			$inc_saldo    = (float)$saldo_final;
			$last_hora    = null;
			$inc_esperado = $esperado_total;
		}

		header('Content-Type: application/json');
		echo json_encode(array(
			// Acumulados del día
			'opening_balance'  => number_format($opening_balance, 2),
			'today_income'     => number_format($today_income, 2),
			'ingreso_efectivo' => number_format($ingreso_efectivo, 2),
			'tarjeta_credito'  => number_format($tarjeta_credito, 2),
			'tarjeta_debito'   => number_format($tarjeta_debito, 2),
			'total_expense'    => number_format($total_expense, 2),
			'saldo_final'      => number_format($saldo_final, 2),
			// Incrementales desde último corte
			'inc_income'       => number_format($inc_income, 2),
			'inc_efectivo'     => number_format($inc_efectivo, 2),
			'inc_credito'      => number_format($inc_credito, 2),
			'inc_debito'       => number_format($inc_debito, 2),
			'inc_expense'      => number_format($inc_expense, 2),
			'inc_saldo'        => number_format($inc_saldo, 2),
			// Efectivo físico esperado en caja (Saldo Sistema − Tarjetas, incremental)
			// Se envía como número (sin format) para que JS parseFloat funcione sin commas
			'inc_esperado'     => round((float)$inc_esperado, 2),
			'has_prev'         => ($last !== false),
			'last_hora'        => $last_hora,
		));
		exit();
	} catch (Exception $e) {
		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Corte de Caja Manual — registra un nuevo corte (permite múltiples por día)
if ($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'CORTEDECAJA') {
	try {
		$from = date('Y-m-d');
		$day   = date('d', strtotime($from));
		$month = date('m', strtotime($from));
		$year  = date('Y', strtotime($from));
		$where_query  = " DAY(`pos_register`.`created_at`) = $day";
		$where_query .= " AND MONTH(`pos_register`.`created_at`) = $month";
		$where_query .= " AND YEAR(`pos_register`.`created_at`) = $year";

		$saldo_final      = str_replace(',', '', $request->post['saldo_final']);
		$efectivo_contado = str_replace(',', '', $request->post['efectivo_contado']);
		$notas            = isset($request->post['notas']) ? trim($request->post['notas']) : '';
		$saldo_final      = is_numeric($saldo_final) ? (float)$saldo_final : 0;

		// Obtener totales acumulados del día (fuente de verdad: servidor)
		// Se pasa $from como $to para forzar filtro de día exacto (no rango multi-día)
		$opening_balance   = get_opening_balance($from);
		$today_income      = get_total_income($from, $from);
		$tarjeta_credito   = get_pagos_tarjeta_credito($from, $from);
		$tarjeta_debito    = get_pagos_tarjeta_debito($from, $from);
		$ingreso_efectivo  = max(0, $today_income - $tarjeta_credito - $tarjeta_debito);
		$total_expense     = get_total_expense($from, $from);

		// Efectivo físico esperado en caja = Saldo Sistema − Tarjetas
		// Regla: incluye apertura y gastos; los cobros con tarjeta NO van a caja física
		$saldo_sistema_actual = (float)$opening_balance + (float)$today_income - (float)$total_expense;
		$tarjetas_acum        = (float)$tarjeta_credito + (float)$tarjeta_debito;
		$esperado_total       = $saldo_sistema_actual - $tarjetas_acum;

		// Obtener el último corte para calcular el valor incremental esperado
		$stmt_last = db()->prepare("
			SELECT ingreso_efectivo, tarjeta_credito, tarjeta_debito, saldo_sistema
			FROM corte_caja
			WHERE store_id = ? AND fecha = ?
			ORDER BY hora_corte DESC LIMIT 1
		");
		$stmt_last->execute(array(store_id(), $from));
		$last_corte = $stmt_last->fetch(PDO::FETCH_ASSOC);

		if ($last_corte) {
			$prev_esperado        = (float)$last_corte['saldo_sistema']
			                      - ((float)$last_corte['tarjeta_credito'] + (float)$last_corte['tarjeta_debito']);
			$efectivo_esperado_inc = $esperado_total - $prev_esperado;
		} else {
			$efectivo_esperado_inc = $esperado_total; // primer corte: todo el efectivo físico del día
		}

		// Si no se ingresó efectivo contado, asumir que cuadra con el esperado
		$efectivo_contado = (is_numeric($efectivo_contado) && (float)$efectivo_contado != 0)
			? (float)$efectivo_contado
			: $efectivo_esperado_inc;
		// Diferencia: lo físicamente contado vs lo que el sistema espera en caja
		$diferencia = $efectivo_contado - $efectivo_esperado_inc;

		// Asegurar que exista registro en pos_register
		$statement = db()->prepare("SELECT `id` FROM `pos_register` WHERE $where_query AND `store_id` = ?");
		$statement->execute(array(store_id()));
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			$statement = db()->prepare("INSERT INTO `pos_register` SET `store_id` = ?, `created_at` = ?");
			$statement->execute(array(store_id(), date_time()));
		}
		// Actualizar closing_balance en pos_register (último corte del día)
		$statement = db()->prepare("UPDATE `pos_register` SET `closing_balance` = ? WHERE $where_query AND `store_id` = ?");
		$statement->execute(array($efectivo_contado, store_id()));

		// Insertar nuevo corte en la tabla de historial
		$stmt = db()->prepare("INSERT INTO `corte_caja`
			(`store_id`, `fecha`, `hora_corte`, `opening_balance`, `today_income`,
			 `ingreso_efectivo`, `tarjeta_credito`, `tarjeta_debito`, `total_expense`,
			 `saldo_sistema`, `efectivo_contado`, `diferencia`, `notas`, `user_id`, `created_at`)
			VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
		$stmt->execute(array(
			store_id(),
			$from,
			$opening_balance,
			$today_income,
			$ingreso_efectivo,
			$tarjeta_credito,
			$tarjeta_debito,
			$total_expense,
			$saldo_final,
			$efectivo_contado,
			$diferencia,
			$notas,
			user_id()
		));
		$nuevo_id = db()->lastInsertId();

		header('Content-Type: application/json');
		echo json_encode(array(
			'msg'  => 'Corte de caja registrado correctamente.',
			'id'   => $nuevo_id,
			'hora' => date('H:i:s')
		));
		exit();
	} catch (Exception $e) {
		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Obtener lista de cortes de caja de una fecha
if ($request->server['REQUEST_METHOD'] == 'GET' AND $request->get['type'] == 'GETCORTES') {
	try {
		// Soporta día único (?from=X) y rango (?from=X&to=Y)
		// También acepta el parámetro legacy ?fecha=X para compatibilidad
		$g_from = isset($request->get['from'])  && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->get['from'])
			? $request->get['from']
			: (isset($request->get['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->get['fecha'])
				? $request->get['fecha']
				: date('Y-m-d'));
		$g_to   = isset($request->get['to'])    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->get['to'])
			? $request->get['to']
			: $g_from;
		if ($g_to < $g_from) { $g_to = $g_from; }

		$stmt = db()->prepare("
			SELECT c.*, u.`username` AS `user_name`
			FROM `corte_caja` c
			LEFT JOIN `users` u ON u.`id` = c.`user_id`
			WHERE c.`store_id` = ? AND c.`fecha` BETWEEN ? AND ?
			ORDER BY c.`fecha` ASC, c.`hora_corte` ASC
		");
		$stmt->execute(array(store_id(), $g_from, $g_to));
		$cortes = $stmt->fetchAll(PDO::FETCH_ASSOC);

		header('Content-Type: application/json');
		echo json_encode(array('cortes' => $cortes, 'total' => count($cortes)));
		exit();
	} catch (Exception $e) {
		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}

// Eliminar un corte de caja (solo admin)
if ($request->server['REQUEST_METHOD'] == 'POST' AND $request->get['type'] == 'DELETECORTE') {
	try {
		if (user_group_id() != 1) {
			header('HTTP/1.1 403 Forbidden');
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(array('errorMsg' => 'Sin permisos.'));
			exit();
		}
		$id = isset($request->post['id']) ? (int)$request->post['id'] : 0;
		if ($id <= 0) {
			header('HTTP/1.1 422 Unprocessable Entity');
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(array('errorMsg' => 'ID inválido.'));
			exit();
		}
		$stmt = db()->prepare("DELETE FROM `corte_caja` WHERE `id` = ? AND `store_id` = ?");
		$stmt->execute(array($id, store_id()));

		header('Content-Type: application/json');
		echo json_encode(array('msg' => 'Corte eliminado correctamente.'));
		exit();
	} catch (Exception $e) {
		header('HTTP/1.1 422 Unprocessable Entity');
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('errorMsg' => $e->getMessage()));
		exit();
	}
}