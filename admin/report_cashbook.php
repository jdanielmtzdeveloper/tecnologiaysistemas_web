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

$today         = date('Y-m-d');
$yesterday     = date('Y-m-d', strtotime('-1 day'));
$week_start    = date('Y-m-d', strtotime('monday this week'));
$month_start   = date('Y-m-d', strtotime('first day of this month'));
$prev_m_start  = date('Y-m-d', strtotime('first day of last month'));
$prev_m_end    = date('Y-m-d', strtotime('last day of last month'));

// Si no hay ?from, redirigir con hoy para garantizar fecha válida a los controladores Angular
if (!from()) {
  redirect(relative_url() . '?from=' . $today . '&to=' . $today);
}
$from = date('Y-m-d', strtotime(from()));

// $to: parámetro explícito o igual al from (vista de día único)
$to_raw = isset($request->get['to']) && $request->get['to'] && $request->get['to'] != 'null'
    ? $request->get['to']
    : $from;
$to = date('Y-m-d', strtotime($to_raw));
if ($to < $from) { $to = $from; } // sanidad: to nunca puede ser antes que from

$is_range   = ($from !== $to);
$show_today = (!$is_range && $from === $today);

// Saldo apertura del primer día del rango
$day   = date('d', strtotime($from));
$month = date('m', strtotime($from));
$year  = date('Y', strtotime($from));
$where_query  = " DAY(`pos_register`.`created_at`) = $day";
$where_query .= " AND MONTH(`pos_register`.`created_at`) = $month";
$where_query .= " AND YEAR(`pos_register`.`created_at`) = $year";
if (isset($db)) {
  $statement = $db->prepare("SELECT `opening_balance` FROM `pos_register` WHERE $where_query AND `store_id` = ?");
  $statement->execute(array(store_id()));
  $row = $statement->fetch(PDO::FETCH_ASSOC);
  $openinig_balance = isset($row['opening_balance']) ? $row['opening_balance'] : 0;

  // Verificar si ya hay cortes registrados hoy (para bloquear saldo apertura)
  $stmt_corte_hoy = $db->prepare("SELECT COUNT(*) AS total FROM corte_caja WHERE store_id = ? AND fecha = ?");
  $stmt_corte_hoy->execute(array(store_id(), $today));
  $row_corte_hoy  = $stmt_corte_hoy->fetch(PDO::FETCH_ASSOC);
  $tiene_corte_hoy = ($row_corte_hoy && (int)$row_corte_hoy['total'] > 0);
} else {
  $openinig_balance = 0;
  $tiene_corte_hoy = false;
}

// Set Document Title
$document->setTitle(trans('title_cashbook'));
$document->setBodyClass('sidebar-collapse');

// Add Script
$document->addScript('../assets/qz-tray/qz-tray.js');
$document->addScript('../assets/itsolution24/angular/controllers/ReportIncomeDaywiseController.js');
$document->addScript('../assets/itsolution24/angular/controllers/ReportExpenseDaywiseController.js');

// Include Header and Footer
include("header.php"); 
include ("left_sidebar.php") ;
?>

<style type="text/css">
.income-expense-row:after {
  content: "";
  position: absolute;
  left: 50%;
  top: 0;
  width: 2px;
  height: 100%;
  background-color: #ECF0F5;
}
.select2-container {
  width: 50px;
}
</style>

<!-- Content Wrapper Start -->
<div class="content-wrapper">

  <!-- Content Header Start -->
  <section class="content-header">

    <h1>
      <?php echo trans('text_cashbook_title'); ?>
        <small>
        <?php echo store('name'); ?>
      </small>
    </h1>
    <ol class="breadcrumb">
      <li>
        <a href="dashboard.php">
          <i class="fa fa-dashboard"></i> 
          <?php echo trans('text_dashboard'); ?>
        </a>
      </li>
      <li class="active">
        <?php echo trans('text_cashbook_title'); ?> 
      </li>
    </ol>
  </section>
  <!-- Content Header end -->

  <!-- Content Start -->
  <section class="content">

    <?php if(DEMO) : ?>
    <div class="box">
      <div class="box-body">
        <div class="alert alert-info mb-0">
          <p><span class="fa fa-fw fa-info-circle"></span> <?php echo $demo_text; ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="box box-default">
      <div class="box-header bg-warning">
        <h3 class="box-title">
          <?php echo trans('text_cashbook_details_title'); ?>

          <!-- ── Presets de rango ── -->
          <div class="btn-group btn-group-xs" style="margin-left:8px;">
            <?php
              function rangeBtn($label, $f, $t, $currentFrom, $currentTo, $extra='') {
                $active = ($f == $currentFrom && $t == $currentTo) ? ' btn-primary' : ' btn-default';
                echo '<a href="?from='.$f.'&to='.$t.'" class="btn btn-xs'.$active.'" '.$extra.'>'.$label.'</a>';
              }
              rangeBtn('Hoy',       $today,      $today,      $from, $to);
              rangeBtn('Ayer',      $yesterday,  $yesterday,  $from, $to);
              rangeBtn('Semana',    $week_start, $today,      $from, $to);
              rangeBtn('Mes',       $month_start,$today,      $from, $to);
              rangeBtn('Mes ant.',  $prev_m_start,$prev_m_end,$from, $to);
            ?>
          </div>

          <!-- ── Selector de rango personalizado ── -->
          <div style="display:inline-block;position:relative;margin-left:6px;" id="range-picker-wrap">
            <span class="btn btn-xs btn-info" id="btn-toggle-range" title="Rango personalizado" style="cursor:pointer;">
              <i class="fa fa-calendar"></i>
              <strong id="range-label">
                <?php
                  if ($is_range) {
                    echo format_only_date($from) . ' &ndash; ' . format_only_date($to);
                  } else {
                    echo format_only_date($from);
                  }
                ?>
              </strong>
              <i class="fa fa-caret-down" style="margin-left:4px;"></i>
            </span>

            <div id="custom-range-panel" style="display:none;position:absolute;top:30px;left:0;z-index:1050;background:#fff;border:1px solid #ccc;border-radius:4px;padding:12px 14px;min-width:300px;box-shadow:0 4px 14px rgba(0,0,0,.18);">
              <div style="display:flex;align-items:center;gap:8px;">
                <div>
                  <label style="font-size:11px;color:#777;margin-bottom:2px;display:block;">Desde</label>
                  <input type="text" id="dp-from" class="form-control input-sm" style="width:130px;" autocomplete="off" value="<?php echo $from; ?>">
                </div>
                <span style="margin-top:16px;color:#aaa;">—</span>
                <div>
                  <label style="font-size:11px;color:#777;margin-bottom:2px;display:block;">Hasta</label>
                  <input type="text" id="dp-to" class="form-control input-sm" style="width:130px;" autocomplete="off" value="<?php echo $to; ?>">
                </div>
              </div>
              <div style="margin-top:10px;text-align:right;">
                <button class="btn btn-default btn-xs" id="btn-cancel-range">Cancelar</button>
                <button class="btn btn-primary btn-xs" id="btn-apply-range">
                  <i class="fa fa-check"></i> Aplicar
                </button>
              </div>
            </div>
          </div>
        </h3>

        <?php $print_date = $is_range ? (format_only_date($from).' – '.format_only_date($to)) : format_only_date($from); ?>
        <div class="pull-right no-print" style="display:flex;gap:8px;align-items:center;">
          <?php if ($show_today): ?>
          <button type="button" class="btn btn-danger btn-sm" id="btn-corte-caja" title="Corte de Caja Manual">
            <i class="fa fa-lock"></i> CORTE DE CAJA
          </button>
          <?php endif; ?>
          <a class="pointer" href="export_cashbook_excel.php?from=<?php echo $from; ?>&to=<?php echo $to; ?>" title="Exportar a Excel">
            <i class="fa fa-file-excel-o"></i> Exportar a Excel
          </a>
          <a class="pointer" onClick="window.printContent('cashbook-summary', {title:'<?php echo trans('title_cashbook').' - '.$print_date;?>', 'headline':'<?php echo trans('title_cashbook').' - '.$print_date;?>', screenSize:'fullScreen'});">
            <i class="fa fa-print"></i> <?php echo trans('text_print');?>
          </a>
        </div>
      </div>
      <div class="row">
        <div class="col-md-6 col-md-offset-3">
          <div class="table-responsive">
            <table class="table table-striped mt-20">
              <tbody>
                <tr>
                  <td class="w-50 bg-info text-right"><?php echo trans('label_opening_balance'); ?></td>
                  <td class="w-50 bg-green text-right">
                    <?php if (strtotime($from) == strtotime(date('Y-m-d'))): ?>
                      <?php if ($tiene_corte_hoy): ?>
                        <div class="input-group">
                          <div class="input-group-addon bg-default" style="cursor:default;" title="Bloqueado: ya existe un corte registrado hoy">
                            <i class="fa fa-lock text-muted"></i>
                          </div>
                          <input style="font-size:22px;font-weight:700;background:#f5f5f5;color:#888;" class="form-control text-center" type="text" value="<?php echo currency_format($openinig_balance);?>" disabled>
                        </div>
                        <small class="text-muted" id="lbl-balance-locked"><i class="fa fa-info-circle"></i> No editable: ya existe un corte registrado.</small>
                      <?php else: ?>
                        <div class="input-group" id="balance-editable-group">
                          <div class="input-group-addon pointer bg-blue" id="btn-update-balance" title="<?php echo trans('button_save'); ?>">
                            <i class="fa fa-pencil"></i>
                          </div>
                          <input style="font-size:22px;font-weight:700;" id="opening-balance" class="form-control text-center" type="text" value="<?php echo currency_format($openinig_balance);?>" name="opening_balance" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;" onclick="this.select();">
                        </div>
                      <?php endif; ?>
                    <?php else:?>
                      <h4 class="text-center"><b><?php echo currency_format($openinig_balance);?></b></h4>
                    <?php endif;?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    
    <div class="box box-default">
      <div class="income-expense-row">
        <div class="row">
          <div class="col-md-6" ng-controller="ReportIncomeDaywiseController">
            <div class="box-header">
              <h3 class="box-title">
                Credit / <?php echo trans('title_income'); ?>
              </h3>
            </div>
            <div class='box-body'>
              
              <?php $hide_colums = "";?>
              <div class="table-responsive">                     
                <table id="income-income-list" class="table table-bordered table-striped table-hovered" data-hide-colums="<?php echo $hide_colums; ?>">
                  <thead>
                    <tr class="bg-gray">
                      <th class="w-5">
                        <?php echo trans('label_serial_no'); ?>
                      </th>
                      <th class="w-35">
                        <?php echo trans('label_title'); ?>
                      </th>
                      <th class="w-20">
                        <?php echo trans('label_amount'); ?>
                      </th>
                    </tr>
                  </thead>
                  <tfoot>
                    <tr class="bg-gray">
                      <th class="text-right" colspan="2">
                        <?php echo trans('label_total'); ?>
                      </th>
                      <th class="w-20">
                        <?php echo trans('label_amount'); ?>
                      </th>
                    </tr>
                  </tfoot>
                </table>    
              </div>

            </div>
          </div>
          <div class="col-md-6 expense-col" ng-controller="ReportExpenseDaywiseController">
            <div class="box-header">
              <h3 class="box-title">
                Debit / <?php echo trans('title_expense'); ?>
              </h3>
            </div>
            <div class='box-body'>     
              
              <?php $hide_colums = "";?>
              <div class="table-responsive">                     
                <table id="expense-expense-list" class="table table-bordered table-striped table-hovered" data-hide-colums="<?php echo $hide_colums; ?>">
                  <thead>
                    <tr class="bg-gray">
                      <th class="w-5">
                        <?php echo trans('label_serial_no'); ?>
                      </th>
                      <th class="w-35">
                        <?php echo trans('label_title'); ?>
                      </th>
                      <th class="w-20">
                        <?php echo trans('label_amount'); ?>
                      </th>
                    </tr>
                  </thead>
                  <tfoot>
                    <tr class="bg-gray">
                      <th class="text-right" colspan="2">
                        <?php echo trans('label_total'); ?>
                      </th>
                      <th class="w-20">
                        <?php echo trans('label_amount'); ?>
                      </th>
                    </tr>
                  </tfoot>
                </table>    
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>

    <div id="cashbook-summary" class="box box-default">
      <div class="box-header" style="background:#ecf0f5;border-bottom:1px solid #ddd;">
        <h3 class="box-title"><i class="fa fa-calculator"></i> Resumen del día</h3>
        <div class="box-tools pull-right no-print">
          <button type="button" class="btn btn-xs btn-default" id="btn-refresh-summary" title="Actualizar resumen">
            <i class="fa fa-refresh"></i> Actualizar
          </button>
        </div>
      </div>
      <div class="row mt-20">
        <div class="col-md-6 col-md-offset-3 mb-20" id="cashbook-summary-body">
          <!-- Se carga dinámicamente vía AJAX -->
          <div class="text-center" id="summary-loading">
            <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== HISTORIAL DE CORTES DE CAJA ===== -->
    <div class="box box-default" id="box-historial-cortes">
      <div class="box-header bg-navy">
        <h3 class="box-title">
          <i class="fa fa-list-alt"></i> Historial de Cortes de Caja
          <span class="label label-default" id="badge-total-cortes">0</span>
        </h3>
        <div class="box-tools pull-right no-print">
          <button type="button" class="btn btn-box-tool" data-widget="collapse">
            <i class="fa fa-minus"></i>
          </button>
        </div>
      </div>
      <div class="box-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover" id="tabla-cortes">
            <thead>
              <tr class="bg-gray">
                <th class="text-center" style="width:40px;">#</th>
                <th class="text-center col-fecha" style="width:100px;<?php echo !$is_range ? 'display:none;' : ''; ?>"><i class="fa fa-calendar-o"></i> Fecha</th>
                <th class="text-center" style="width:90px;"><i class="fa fa-clock-o"></i> Hora</th>
                <th class="text-right">Saldo Apertura</th>
                <th class="text-right">Ingreso Total</th>
                <th class="text-right"><i class="fa fa-money"></i> Efectivo</th>
                <th class="text-right"><i class="fa fa-credit-card"></i> Tarjetas</th>
                <th class="text-right text-red">Gastos</th>
                <th class="text-right"><b>Saldo Sistema</b></th>
                <th class="text-right">Efectivo Contado</th>
                <th class="text-center">Diferencia</th>
                <th>Notas</th>
                <th>Usuario</th>
                <?php if (user_group_id() == 1): ?>
                <th class="text-center no-print" style="width:50px;">Acc.</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody id="tbody-cortes">
              <tr id="row-sin-cortes">
                <td colspan="13" class="text-center text-muted">
                  <i class="fa fa-info-circle"></i> No hay cortes registrados para esta fecha.
                </td>
              </tr>
            </tbody>
            <tfoot id="tfoot-cortes" style="display:none;">
              <tr class="bg-yellow">
                <th id="tfoot-label" colspan="2" class="text-right" style="vertical-align:middle;font-size:11px;">
                  RESUMEN FINAL<br>
                  <small style="font-weight:normal;color:#777;">
                    <span style="color:#31708f;">●</span> al último corte &nbsp;
                    <span style="color:#3c763d;">∑</span> suma de cortes
                  </small>
                </th>
                <th class="text-right" id="tfoot-apertura" title="Saldo de apertura del período">—</th>
                <th class="text-right" id="tfoot-ingreso" title="Ingreso acumulado al último corte del día" style="color:#31708f;">—</th>
                <th class="text-right" id="tfoot-efectivo" title="Efectivo acumulado al último corte del día" style="color:#31708f;">—</th>
                <th class="text-right" id="tfoot-tarjetas" title="Tarjetas acumuladas al último corte del día" style="color:#31708f;">—</th>
                <th class="text-right" id="tfoot-gastos" title="Gastos acumulados al último corte del día" style="color:#31708f;">—</th>
                <th class="text-right" id="tfoot-saldo" title="Saldo sistema al último corte del día" style="color:#31708f;">—</th>
                <th class="text-right" id="tfoot-contado" title="Suma de todo el efectivo contado en los cortes" style="color:#3c763d;">—</th>
                <th class="text-center" id="tfoot-diferencia" title="Suma de todas las diferencias de los cortes" style="color:#3c763d;">—</th>
                <th colspan="<?php echo user_group_id() == 1 ? '3' : '2'; ?>"></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
    <!-- ===== FIN HISTORIAL CORTES ===== -->

  </section>
  <!-- Content End -->
</div>
<!-- Content Wrapper End -->

<!-- ===== Modal Nuevo Corte de Caja ===== -->
<div class="modal fade" id="modal-corte-caja" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-red">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-lock"></i> CORTE DE CAJA &mdash; <span id="corte-hora-actual"></span></h4>
      </div>
      <div class="modal-body">
        <!-- Alerta: cambia según si hay cortes previos -->
        <div id="corte-alerta-normal" class="alert alert-warning" style="display:none;">
          <i class="fa fa-warning"></i> Se registrar&aacute; el primer corte del d&iacute;a.
        </div>
        <div id="corte-alerta-previo" class="alert alert-info" style="display:none;">
          <i class="fa fa-info-circle"></i> Ya existe un corte hoy a las <strong id="corte-last-hora">—</strong>.
          Los valores <span class="label label-warning">desde último corte</span> son los que debes cuadrar ahora.
        </div>

        <div class="row">
          <div class="col-md-7">
            <div class="table-responsive">
              <table class="table table-bordered table-striped" style="font-size:13px;">
                <tbody>
                  <tr>
                    <td class="bg-gray text-right" style="width:55%"><strong>SALDO APERTURA</strong></td>
                    <td class="text-right" id="corte-apertura">—</td>
                  </tr>
                  <tr>
                    <td class="bg-gray text-right"><strong>INGRESO TOTAL HOY</strong></td>
                    <td class="text-right" id="corte-ingreso">—</td>
                  </tr>
                  <tr>
                    <td class="text-right" style="background:#d9edf7;padding-left:20px;"><i class="fa fa-money"></i> Efectivo</td>
                    <td class="text-right" style="background:#d9edf7;" id="corte-efectivo">—</td>
                  </tr>
                  <tr class="bg-green">
                    <td class="text-right" style="padding-left:20px;"><i class="fa fa-credit-card"></i> Tarjeta Cr&eacute;dito</td>
                    <td class="text-right" id="corte-credito">—</td>
                  </tr>
                  <tr class="bg-green">
                    <td class="text-right" style="padding-left:20px;"><i class="fa fa-credit-card-alt"></i> Tarjeta D&eacute;bito</td>
                    <td class="text-right" id="corte-debito">—</td>
                  </tr>
                  <tr class="bg-red">
                    <td class="text-right"><strong>GASTOS HOY</strong></td>
                    <td class="text-right" id="corte-gastos">—</td>
                  </tr>
                  <tr class="bg-yellow">
                    <td class="text-right"><h4><b>SALDO SISTEMA</b></h4></td>
                    <td class="text-right"><h4><b id="corte-saldo-final">—</b></h4></td>
                  </tr>

                  <!-- Fila incremental: visible solo cuando hay cortes previos -->
                  <tr id="corte-row-inc" style="display:none;background:#fff3cd;border-top:2px solid #e6a817;">
                    <td class="text-right" style="padding-left:0;">
                      <span class="label label-warning" style="font-size:11px;"><i class="fa fa-check-circle"></i> Efectivo esperado en caja</span><br>
                      <small class="text-muted">Saldo Sistema &minus; Tarjetas</small>
                    </td>
                    <td class="text-right" style="font-size:1.2em;font-weight:700;color:#856404;">
                      $<span id="corte-inc-efectivo">—</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-md-5">
            <div class="form-group">
              <label><strong><i class="fa fa-money"></i> Efectivo contado en caja:</strong></label>
              <div class="input-group">
                <span class="input-group-addon">$</span>
                <input type="text" id="corte-efectivo-contado" class="form-control input-lg text-right" placeholder="0.00" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;">
              </div>
              <small class="text-muted">Monto f&iacute;sico contado (opcional).</small>
            </div>
            <div id="corte-diferencia-row" class="well well-sm text-center" style="display:none;">
              <small class="text-muted" id="corte-dif-label">Contado vs Efectivo del día</small><br>
              <strong>Diferencia: </strong>
              <span id="corte-diferencia" style="font-size:1.3em;font-weight:700;"></span>
            </div>
            <div class="form-group">
              <label><strong><i class="fa fa-pencil"></i> Notas del corte:</strong></label>
              <textarea id="corte-notas" class="form-control" rows="3" placeholder="Observaciones del corte de caja..."></textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default btn-lg" data-dismiss="modal">
          <i class="fa fa-times"></i> Cancelar
        </button>
        <button type="button" class="btn btn-danger btn-lg" id="btn-confirmar-corte">
          <i class="fa fa-lock"></i> Registrar Corte
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Modal Ver Detalle de Corte ===== -->
<div class="modal fade" id="modal-detalle-corte" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-navy">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-file-text-o"></i> Detalle del Corte de Caja</h4>
      </div>
      <div class="modal-body" id="detalle-corte-body">
        <!-- Se llenará dinámicamente -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-info" id="btn-imprimir-detalle">
          <i class="fa fa-print"></i> Imprimir Ticket
        </button>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
(function($) {
  var currentFrom = '<?php echo $from; ?>';
  var currentTo   = '<?php echo $to; ?>';
  var isRange     = <?php echo $is_range ? 'true' : 'false'; ?>;
  var cortesData  = [];

  /* ─── Helpers de formato ─── */
  function fmt(n) {
    n = parseFloat(n) || 0;
    return n.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function difClass(n) {
    n = parseFloat(n) || 0;
    return n >= 0 ? 'text-success' : 'text-danger';
  }
  function difFmt(n) {
    n = parseFloat(n) || 0;
    return (n >= 0 ? '+' : '') + fmt(n);
  }

  /* ─── Cargar historial de cortes ─── */
  function cargarCortes() {
    $.ajax({
      url: window.baseUrl + '/_inc/ajax.php?type=GETCORTES&from=' + currentFrom + '&to=' + currentTo,
      dataType: 'JSON',
      type: 'GET',
      success: function(res) {
        cortesData = res.cortes || [];
        renderTablaCortes(cortesData);
      }
    });
  }

  function renderTablaCortes(cortes) {
    var $tbody = $('#tbody-cortes');
    var $tfoot = $('#tfoot-cortes');
    $('#badge-total-cortes').text(cortes.length);

    var colspan = <?php echo user_group_id() == 1 ? 13 : 12; ?> + (isRange ? 1 : 0);
    if (!cortes.length) {
      $tbody.html('<tr id="row-sin-cortes"><td colspan="' + colspan + '" class="text-center text-muted">' +
        '<i class="fa fa-info-circle"></i> No hay cortes registrados para este período.</td></tr>');
      $tfoot.hide();
      return;
    }

    // Mostrar/ocultar columna Fecha según si es rango
    $('#tabla-cortes thead tr th.col-fecha, #tabla-cortes tfoot tr th.col-fecha').toggle(isRange);

    var html = '';

    // Agrupar cortes por fecha → tomar el último de cada día para columnas acumuladas
    var byFecha = {};
    cortes.forEach(function(c) {
      if (!byFecha[c.fecha]) byFecha[c.fecha] = [];
      byFecha[c.fecha].push(c);
    });
    var cumIngreso = 0, cumEfectivo = 0, cumTarjetas = 0, cumGastos = 0, cumSaldo = 0, cumApertura = 0;
    Object.keys(byFecha).forEach(function(fecha) {
      var lista = byFecha[fecha];
      var ultimo  = lista[lista.length - 1]; // último corte del día = estado final
      var primero = lista[0];
      cumApertura += parseFloat(primero.opening_balance) || 0;
      cumIngreso  += parseFloat(ultimo.today_income) || 0;
      cumEfectivo += parseFloat(ultimo.ingreso_efectivo) || 0;
      cumTarjetas += (parseFloat(ultimo.tarjeta_credito)||0) + (parseFloat(ultimo.tarjeta_debito)||0);
      cumGastos   += parseFloat(ultimo.total_expense) || 0;
      cumSaldo    += parseFloat(ultimo.saldo_sistema) || 0;
    });

    // Solo estas columnas se suman entre cortes (son incrementales)
    var totContado = 0, totDif = 0;
    cortes.forEach(function(c) {
      totContado += parseFloat(c.efectivo_contado) || 0;
      totDif     += parseFloat(c.diferencia) || 0;
    });

    var apertura0 = parseFloat(cortes[0].opening_balance) || 0;

    cortes.forEach(function(c, i) {
      var tarjetas = (parseFloat(c.tarjeta_credito)||0) + (parseFloat(c.tarjeta_debito)||0);
      var dif = parseFloat(c.diferencia) || 0;
      var hora = c.hora_corte ? c.hora_corte.substring(11, 19) : '—';
      var fechaCorte = c.fecha || '';
      <?php $isAdmin = (user_group_id() == 1); ?>
      html += '<tr>' +
        '<td class="text-center">' + (i+1) + '</td>' +
        (isRange ? '<td class="text-center col-fecha"><strong>' + fechaCorte + '</strong></td>' : '') +
        '<td class="text-center"><strong>' + hora + '</strong></td>' +
        '<td class="text-right">$' + fmt(c.opening_balance) + '</td>' +
        '<td class="text-right">$' + fmt(c.today_income) + '</td>' +
        '<td class="text-right">$' + fmt(c.ingreso_efectivo) + '</td>' +
        '<td class="text-right">$' + fmt(tarjetas) + '</td>' +
        '<td class="text-right text-red">$' + fmt(c.total_expense) + '</td>' +
        '<td class="text-right"><strong>$' + fmt(c.saldo_sistema) + '</strong></td>' +
        '<td class="text-right">$' + fmt(c.efectivo_contado) + '</td>' +
        '<td class="text-center"><span class="' + difClass(dif) + '"><strong>' + difFmt(dif) + '</strong></span></td>' +
        '<td><small>' + (c.notas ? c.notas : '<em class="text-muted">—</em>') + '</small></td>' +
        '<td><small>' + (c.user_name ? c.user_name : '<em class="text-muted">—</em>') + '</small></td>' +
        <?php if (user_group_id() == 1): ?>
        '<td class="text-center no-print">' +
          '<button class="btn btn-xs btn-default btn-ver-corte" data-idx="' + i + '" title="Ver detalle"><i class="fa fa-eye"></i></button> ' +
          '<button class="btn btn-xs btn-info btn-print-corte" data-idx="' + i + '" title="Imprimir ticket"><i class="fa fa-print"></i></button> ' +
          '<button class="btn btn-xs btn-danger btn-del-corte" data-id="' + c.id + '" title="Eliminar"><i class="fa fa-trash"></i></button>' +
        '</td>' +
        <?php else: ?>
        '<td class="text-center no-print">' +
          '<button class="btn btn-xs btn-default btn-ver-corte" data-idx="' + i + '" title="Ver detalle"><i class="fa fa-eye"></i></button> ' +
          '<button class="btn btn-xs btn-info btn-print-corte" data-idx="' + i + '" title="Imprimir ticket"><i class="fa fa-print"></i></button>' +
        '</td>' +
        <?php endif; ?>
        '</tr>';
    });
    $tbody.html(html);

    // Totales en tfoot
    // Columnas acumuladas: valor del último corte por día (no suma)
    $('#tfoot-label').attr('colspan', isRange ? 3 : 2);
    $('#tfoot-apertura').text('$' + fmt(cumApertura));
    $('#tfoot-ingreso').text('$' + fmt(cumIngreso));
    $('#tfoot-efectivo').text('$' + fmt(cumEfectivo));
    $('#tfoot-tarjetas').text('$' + fmt(cumTarjetas));
    $('#tfoot-gastos').text('$' + fmt(cumGastos));
    $('#tfoot-saldo').text('$' + fmt(cumSaldo));
    // Columnas incrementales: suma real entre cortes
    $('#tfoot-contado').text('$' + fmt(totContado));
    $('#tfoot-diferencia')
      .removeClass('text-success text-danger')
      .addClass(totDif >= 0 ? 'text-success' : 'text-danger')
      .text(difFmt(totDif));
    $tfoot.show();
  }

  /* ─── Abrir modal de nuevo corte ─── */
  $(document).on('click', '#btn-corte-caja', function(e) {
    e.preventDefault();
    var now = new Date();
    var hh = String(now.getHours()).padStart(2,'0');
    var mm = String(now.getMinutes()).padStart(2,'0');
    var ss = String(now.getSeconds()).padStart(2,'0');
    $('#corte-hora-actual').text(hh + ':' + mm + ':' + ss);

    $.ajax({
      url: window.baseUrl + '/_inc/ajax.php?type=GETCASHBOOKSUMMARY',
      dataType: 'JSON',
      type: 'GET',
      success: function(res) {
        $('#corte-apertura').text('$' + res.opening_balance);
        $('#corte-ingreso').text('$' + res.today_income);
        $('#corte-efectivo').text('$' + res.ingreso_efectivo);
        $('#corte-credito').text('$' + res.tarjeta_credito);
        $('#corte-debito').text('$' + res.tarjeta_debito);
        $('#corte-gastos').text('$' + res.total_expense);
        $('#corte-saldo-final').text(res.saldo_final);
        $('#corte-efectivo-contado').val('');
        $('#corte-diferencia-row').hide();
        $('#corte-notas').val('');

        // Mostrar info de cortes previos e incrementales
        // inc_esperado = Saldo Sistema − Tarjetas (incremental): efectivo físico que debe haber
        var incEsperado = res.inc_esperado;

        // incEsperado llega como número float desde PHP (sin comas) → parseFloat funciona correctamente
        var incEsperadoFmt = fmt(incEsperado); // formateado solo para mostrar

        if (res.has_prev) {
          $('#corte-last-hora').text(res.last_hora);
          $('#corte-inc-efectivo').text(incEsperadoFmt);
          $('#corte-row-inc').show();
          $('#corte-alerta-previo').show();
          $('#corte-alerta-normal').hide();
          $('#corte-dif-label').text('Contado vs Efectivo esperado desde último corte (' + res.last_hora + ')');
        } else {
          $('#corte-inc-efectivo').text(incEsperadoFmt);
          $('#corte-row-inc').show();
          $('#corte-alerta-normal').show();
          $('#corte-alerta-previo').hide();
          $('#corte-dif-label').text('Contado vs Efectivo esperado en caja (Saldo − Tarjetas)');
        }
        // Guardar como número: parseFloat(number) funciona sin stripping de comas
        $('#corte-efectivo-contado').data('inc-efectivo', incEsperado);

        $('#modal-corte-caja').modal('show');
      },
      error: function() {
        $('#modal-corte-caja').modal('show');
      }
    });
  });

  /* ─── Calcular diferencia en tiempo real ─── */
  $(document).on('input', '#corte-efectivo-contado', function() {
    var contado      = parseFloat($(this).val().replace(/,/g,'')) || 0;
    // Comparar contra el efectivo incremental desde el último corte
    var incEfectivo  = parseFloat($(this).data('inc-efectivo') || '0') || 0;
    var efectivo     = incEfectivo;
    var dif          = contado - efectivo;
    var clase        = dif >= 0 ? 'text-success' : 'text-danger';
    $('#corte-diferencia').removeClass('text-success text-danger').addClass(clase)
      .text(difFmt(dif));
    $('#corte-diferencia-row').show();
  });

  /* ─── Confirmar y guardar corte ─── */
  $(document).on('click', '#btn-confirmar-corte', function() {
    var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
    var notas        = $('#corte-notas').val();
    var saldoText       = $('#corte-saldo-final').text().replace(/[$,]/g,'');
    var incEfectivo     = $('#corte-efectivo-contado').data('inc-efectivo') || '0';
    var efectivoContado = $('#corte-efectivo-contado').val().replace(/,/g,'');
    if (!efectivoContado || parseFloat(efectivoContado) === 0) {
      efectivoContado = incEfectivo; // si no se ingresó, asumir que cuadra con el efectivo incremental
    }
    $.ajax({
      url: window.baseUrl + '/_inc/ajax.php?type=CORTEDECAJA',
      dataType: 'JSON',
      type: 'POST',
      data: { efectivo_contado: efectivoContado, notas: notas, saldo_final: saldoText, ingreso_efectivo: incEfectivo },
      success: function(res) {
        $('#modal-corte-caja').modal('hide');
        lockSaldoApertura();
        window.swal('Corte Registrado', res.msg, 'success')
          .then(function() {
            cargarCortes();
            cargarResumen();
          });
      },
      error: function(xhr) {
        try {
          var err = JSON.parse(xhr.responseText);
          window.swal('Error', err.errorMsg, 'error');
        } catch(ex) { alert(xhr.responseText); }
      },
      complete: function() {
        $btn.prop('disabled', false).html('<i class="fa fa-lock"></i> Registrar Corte');
      }
    });
  });

  /* ─── Imprimir ticket de corte de caja vía QZ Tray ─── */
  function printTicketCorte(c) {
    if (!c || !c.id) return;

    var $toastr = window.toastr.info('Conectando con impresora...', '', { timeOut: 0, extendedTimeOut: 0 });

    function clearMsg() { window.toastr.clear($toastr); }

    // Modo unsigned — uso interno en red local
    qz.security.setCertificatePromise(function(resolve) { resolve(); });
    qz.security.setSignaturePromise(function() { return function(resolve) { resolve(); }; });

    $.get(window.baseUrl + '/_inc/print_corte.php', { corte_id: c.id, mode: 'data' })
      .done(function(response) {
        var printData = [{ type: 'raw', format: 'base64', data: response.data }];

        (qz.websocket.isActive() ? Promise.resolve() : qz.websocket.connect())
          .then(function() { return qz.printers.find(response.printer_name); })
          .then(function(printer) { return qz.print(qz.configs.create(printer), printData); })
          .then(function() {
            clearMsg();
            window.toastr.success('Ticket impreso correctamente');
          })
          .catch(function(err) {
            clearMsg();
            var msg = typeof err === 'string' ? err : (err && err.message ? err.message : 'Error al imprimir');
            window.swal('Error de impresión', msg, 'error');
          })
          .finally(function() {
            if (qz.websocket.isActive()) qz.websocket.disconnect();
          });
      })
      .fail(function(xhr) {
        clearMsg();
        try {
          var err = JSON.parse(xhr.responseText);
          window.swal('Error', err.errorMsg, 'error');
        } catch(e) {
          window.swal('Error', 'No se pudo preparar el ticket de impresión', 'error');
        }
      });
  }

  /* ─── Ver detalle de un corte ─── */
  var currentCorteDetalle = null;

  $(document).on('click', '.btn-ver-corte', function() {
    var idx = $(this).data('idx');
    var c   = cortesData[idx];
    if (!c) return;
    currentCorteDetalle = c;
    var hora = c.hora_corte ? c.hora_corte.substring(11,19) : '—';
    var tarjetas = (parseFloat(c.tarjeta_credito)||0) + (parseFloat(c.tarjeta_debito)||0);
    var dif = parseFloat(c.diferencia) || 0;
    var html =
      '<table class="table table-bordered table-striped">' +
        '<tbody>' +
          '<tr><td class="bg-gray text-right" style="width:50%"><strong>Fecha</strong></td><td>' + (c.fecha||'—') + '</td></tr>' +
          '<tr><td class="bg-gray text-right"><strong>Hora del Corte</strong></td><td><strong>' + hora + '</strong></td></tr>' +
          '<tr><td class="bg-gray text-right">Saldo Apertura</td><td class="text-right">$' + fmt(c.opening_balance) + '</td></tr>' +
          '<tr><td class="bg-gray text-right">Ingreso Total</td><td class="text-right">$' + fmt(c.today_income) + '</td></tr>' +
          '<tr><td style="background:#d9edf7;text-align:right;padding-left:20px"><i class="fa fa-money"></i> Efectivo</td><td class="text-right" style="background:#d9edf7">$' + fmt(c.ingreso_efectivo) + '</td></tr>' +
          '<tr class="bg-green"><td class="text-right" style="padding-left:20px"><i class="fa fa-credit-card"></i> Tarjeta Crédito</td><td class="text-right">$' + fmt(c.tarjeta_credito) + '</td></tr>' +
          '<tr class="bg-green"><td class="text-right" style="padding-left:20px"><i class="fa fa-credit-card-alt"></i> Tarjeta Débito</td><td class="text-right">$' + fmt(c.tarjeta_debito) + '</td></tr>' +
          '<tr class="bg-red"><td class="text-right">Gastos</td><td class="text-right">$' + fmt(c.total_expense) + '</td></tr>' +
          '<tr class="bg-yellow"><td class="text-right"><strong>Saldo Sistema</strong></td><td class="text-right"><strong>$' + fmt(c.saldo_sistema) + '</strong></td></tr>' +
          '<tr><td class="text-right">Efectivo Contado</td><td class="text-right">$' + fmt(c.efectivo_contado) + '</td></tr>' +
          '<tr><td class="text-right"><strong>Diferencia</strong></td><td class="text-right"><strong class="' + difClass(dif) + '">' + difFmt(dif) + '</strong></td></tr>' +
          '<tr><td class="bg-gray text-right">Notas</td><td>' + (c.notas ? c.notas : '<em class="text-muted">Sin notas</em>') + '</td></tr>' +
          '<tr><td class="bg-gray text-right">Usuario</td><td>' + (c.user_name || '—') + '</td></tr>' +
        '</tbody>' +
      '</table>';
    $('#detalle-corte-body').html(html);
    $('#modal-detalle-corte').modal('show');
  });

  /* ─── Imprimir ticket desde modal de detalle ─── */
  $(document).on('click', '#btn-imprimir-detalle', function() {
    printTicketCorte(currentCorteDetalle);
  });

  /* ─── Imprimir ticket directo desde fila del historial ─── */
  $(document).on('click', '.btn-print-corte', function() {
    var idx = $(this).data('idx');
    printTicketCorte(cortesData[idx]);
  });

  /* ─── Eliminar corte ─── */
  $(document).on('click', '.btn-del-corte', function() {
    var id  = $(this).data('id');
    var $tr = $(this).closest('tr');
    window.swal({
      title: '¿Eliminar corte?',
      text: 'Esta acción no se puede deshacer.',
      type: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dd4b39',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then(function(result) {
      if (result.value) {
        $.ajax({
          url: window.baseUrl + '/_inc/ajax.php?type=DELETECORTE',
          dataType: 'JSON',
          type: 'POST',
          data: { id: id },
          success: function(res) {
            window.toastr.success(res.msg);
            cargarCortes();
          },
          error: function(xhr) {
            try { window.swal('Error', JSON.parse(xhr.responseText).errorMsg, 'error'); }
            catch(e) { alert(xhr.responseText); }
          }
        });
      }
    });
  });

  /* ─── Bloquear saldo apertura tras primer corte (sin recargar página) ─── */
  function lockSaldoApertura() {
    var $group = $('#balance-editable-group');
    if (!$group.length) return; // ya bloqueado desde PHP o no es hoy
    var val = $('#opening-balance').val();
    $group.replaceWith(
      '<div class="input-group">' +
        '<div class="input-group-addon bg-default" style="cursor:default;" title="Bloqueado: ya existe un corte registrado hoy">' +
          '<i class="fa fa-lock text-muted"></i>' +
        '</div>' +
        '<input style="font-size:22px;font-weight:700;background:#f5f5f5;color:#888;" class="form-control text-center" type="text" value="' + val + '" disabled>' +
      '</div>' +
      '<small class="text-muted" id="lbl-balance-locked"><i class="fa fa-info-circle"></i> No editable: ya existe un corte registrado.</small>'
    );
  }

  /* ─── Botón actualizar saldo apertura ─── */
  $(document).on('click', '#btn-update-balance', function(e) {
    e.preventDefault();
    var balance = $('#opening-balance').val();
    if (!balance) { alert('Please, Input opening balance'); return false; }
    $.ajax({
      url: window.baseUrl + '/_inc/ajax.php?type=UPDATEOPENINGBALANCE',
      dataType: 'JSON',
      type: 'POST',
      data: { balance: balance },
      success: function(res) {
        window.swal('Success!', res.msg, 'success').then(function() {
          window.location = window.location;
        });
      },
      error: function(xhr, opts, err) {
        alert(err + '\r\n' + xhr.statusText + '\r\n' + xhr.responseText);
      }
    });
  });

  /* ─── Cargar resumen AJAX (siempre datos en tiempo real) ─── */
  function cargarResumen() {
    $('#summary-loading').show();
    $('#cashbook-summary-body').find('table').css('opacity', '.5');
    $.ajax({
      url: window.baseUrl + '/_inc/ajax.php?type=GETCASHBOOKSUMMARYHTML&from=' + currentFrom + '&to=' + currentTo,
      dataType: 'JSON',
      type: 'GET',
      success: function(res) {
        $('#cashbook-summary-body').html(res.html);
        var titulo = isRange
          ? 'Resumen del período: ' + currentFrom + ' – ' + currentTo
          : 'Resumen del día';
        $('#cashbook-summary .box-title').html('<i class="fa fa-calculator"></i> ' + titulo);
      },
      error: function() {
        $('#cashbook-summary-body').html('<div class="alert alert-danger">Error al cargar el resumen.</div>');
      }
    });
  }

  /* ─── Inicializar datepickers del rango ─── */
  function initRangePickers() {
    var opts = {
      language: (typeof langCode !== 'undefined' ? langCode : 'es'),
      format: 'yyyy-mm-dd',
      autoclose: true,
      todayHighlight: true,
      endDate: '0d'
    };
    $('#dp-from').datepicker(opts);
    $('#dp-to').datepicker(opts);

    // Al cambiar "Desde", si es mayor que "Hasta" → igualar "Hasta"
    $('#dp-from').on('changeDate', function(e) {
      if ($('#dp-to').val() < e.format('yyyy-mm-dd')) {
        $('#dp-to').datepicker('setDate', e.date);
      }
    });
    // Al cambiar "Hasta", si es menor que "Desde" → igualar "Desde"
    $('#dp-to').on('changeDate', function(e) {
      if ($('#dp-from').val() > e.format('yyyy-mm-dd')) {
        $('#dp-from').datepicker('setDate', e.date);
      }
    });
  }

  /* ─── Selector de rango ─── */
  $(document).ready(function() {
    cargarCortes();
    cargarResumen();
    initRangePickers();

    // Abrir/cerrar panel de rango personalizado
    $(document).on('click', '#btn-toggle-range', function(e) {
      e.stopPropagation();
      $('#custom-range-panel').toggle();
    });
    // Cerrar al clic fuera
    $(document).on('click', function(e) {
      if (!$(e.target).closest('#range-picker-wrap').length) {
        $('#custom-range-panel').hide();
      }
    });

    // Cancelar
    $(document).on('click', '#btn-cancel-range', function() {
      $('#custom-range-panel').hide();
    });

    // Aplicar rango personalizado
    $(document).on('click', '#btn-apply-range', function() {
      var from = $('#dp-from').val();
      var to   = $('#dp-to').val();
      if (!from || !to) { window.toastr.warning('Selecciona ambas fechas.'); return; }
      if (from > to) { var tmp = from; from = to; to = tmp; }
      window.location.href = window.location.pathname + '?from=' + from + '&to=' + to;
    });

    // Botón "Actualizar" del resumen
    $(document).on('click', '#btn-refresh-summary', function() {
      var $icon = $(this).find('i');
      $icon.addClass('fa-spin');
      cargarResumen();
      setTimeout(function() { $icon.removeClass('fa-spin'); }, 1000);
    });

    // Auto-actualizar resumen cada 60 seg solo cuando vemos el día de hoy (single)
    var hoy = new Date().toISOString().slice(0, 10);
    if (!isRange && currentFrom === hoy) {
      setInterval(cargarResumen, 60000);
    }
  });

})(jQuery);
</script>

<?php include ("footer.php"); ?>