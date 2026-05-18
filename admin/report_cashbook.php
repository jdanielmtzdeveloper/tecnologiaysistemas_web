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

$from = from();
if (!$from) {
  $from = date('Y-m-d');
}
$day = date('d', strtotime($from));
$month = date('m', strtotime($from));
$year = date('Y', strtotime($from));
$where_query = " DAY(`pos_register`.`created_at`) = $day";
$where_query .= " AND MONTH(`pos_register`.`created_at`) = $month";
$where_query .= " AND YEAR(`pos_register`.`created_at`) = $year";
$statement = $db->prepare("SELECT `opening_balance` FROM `pos_register` WHERE $where_query AND `store_id` = ?");
$statement->execute(array(store_id()));
$row = $statement->fetch(PDO::FETCH_ASSOC);
$openinig_balance = isset($row['opening_balance']) ? $row['opening_balance'] : 0;

// Set Document Title
$document->setTitle(trans('title_cashbook'));
$document->setBodyClass('sidebar-collapse');

// Add Script
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
        <?php 
        $query_string = '';
        if (from()): ?>
          <?php 
            if (!empty($request->get)) {
              $inc = 1;
              foreach ($request->get as $key => $value) {
                if (!in_array($key, array('from', 'to'))) {
                  if ($inc == 1) {
                      $query_string = '?'.$key.'='.$value;
                  } else {
                      $query_string .= '&'.$key.'='.$value;
                  }
                  $inc++;
                }
              }
            } 
            $from = date('Y-m-d', strtotime(from())); ?>
            <div style="display: inline-block;" class="apply-filter">
                <a href="<?php echo relative_url().$query_string; ?>" class="btn btn-xs btn-info" title="Remove this filter">
                  <?php if (isset($request->get['ftype'])):?>
                      <span class="label label-warning w-50">
                          <?php switch ($request->get['ftype']) {
                              case 'today':
                                  echo 'Today';
                                  break;
                              case 'week':
                                  echo 'Last 7 Days';
                                  break;
                              case 'month':
                                  echo 'Last 30 Days';
                                  break;
                              case 'year':
                                  echo 'Last 365 Days';
                                  break;
                          }?>
                      </span>&nbsp;
                  <?php endif;?>
                  <strong><?php echo format_only_date(date('Y-m-d', strtotime($from))); ?></strong>&nbsp;&nbsp;<i class="fa fa-close text-red"></i>
                </a>
            </div>
          <?php else: ?>
            <div style="display: inline-block;" class="apply-filter">
                <a href="<?php echo relative_url().$query_string; ?>" class="btn btn-xs btn-info mb-0" title="Remove this filter">
                    <strong><?php echo format_only_date(date('Y-m-d', strtotime($from))); ?></strong>
                </a>
            </div>
          <?php endif; ?> 
        </h3>
        <?php $print_date = from() ? from().' to '.to() : date_time();?>
        <div class="pull-right no-print" style="display:flex;gap:8px;align-items:center;">
          <?php if (strtotime($from) == strtotime(date('Y-m-d'))): ?>
          <button type="button" class="btn btn-danger btn-sm" id="btn-corte-caja" title="Corte de Caja Manual">
            <i class="fa fa-lock"></i> CORTE DE CAJA
          </button>
          <?php endif; ?>
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
                      <div class="input-group">
                        <div class="input-group-addon pointer bg-blue" id="btn-update-balance" title="<?php echo trans('button_save'); ?>">
                          <i class="fa fa-pencil"></i>
                        </div>
                        <input style="font-size:22px;font-weight:700;" id="opening-balance" class="form-control text-center" type="text" value="<?php echo currency_format($openinig_balance);?>" name="opening_balance" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;">
                      </div>
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
      <div class="row mt-20">
        <div class="col-md-6 col-md-offset-3  mb-20">
          <?php include ROOT.'/_inc/template/partials/report_cashbook_summary.php';?>
        </div>
      </div>
    </div>

  </section>
  <!-- Content End -->
</div>
<!-- Content Wrapper End -->

<!-- Modal Corte de Caja -->
<div class="modal fade" id="modal-corte-caja" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-red">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title"><i class="fa fa-lock"></i> CORTE DE CAJA MANUAL</h4>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning">
          <i class="fa fa-warning"></i> Esta acci&oacute;n registrar&aacute; el cierre de caja del d&iacute;a de hoy.
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-striped">
            <tbody>
              <tr>
                <td class="bg-gray text-right"><strong>SALDO APERTURA</strong></td>
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
                <td class="text-right"><h4><b>SALDO FINAL HOY</b></h4></td>
                <td class="text-right"><h4><b id="corte-saldo-final">—</b></h4></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="form-group">
          <label><strong>Efectivo contado en caja:</strong></label>
          <div class="input-group">
            <span class="input-group-addon"><i class="fa fa-money"></i></span>
            <input type="text" id="corte-efectivo-contado" class="form-control" placeholder="0.00" onkeypress="return IsNumeric(event);" ondrop="return false;" onpaste="return false;">
          </div>
          <small class="text-muted">Ingrese el monto f&iacute;sico contado en la caja (opcional).</small>
        </div>
        <div id="corte-diferencia-row" class="well well-sm" style="display:none;">
          <strong>Diferencia:</strong> <span id="corte-diferencia" class="text-danger"></span>
        </div>
        <div class="form-group">
          <label><strong>Notas del corte:</strong></label>
          <textarea id="corte-notas" class="form-control" rows="2" placeholder="Observaciones del cierre de caja..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btn-confirmar-corte">
          <i class="fa fa-lock"></i> Confirmar Corte de Caja
        </button>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
  $(document).ready(function() {
    $("#btn-update-balance").on("click", function(e) {
      e.preventDefault();
      var balance = $("#opening-balance").val();
      if (!balance) {
        alert("Please, Input opening balance");
        return false;
      }
      var passData = {
        'balance': balance
      };
      $.ajax({
        url: window.baseUrl+"/_inc/ajax.php?type=UPDATEOPENINGBALANCE",
        dataType: "JSON",
        type: "POST",
        data: passData,
        beforeSend: function() {
          // $(element).button('loading');
        },
        complete: function() {
          // $(element).button('reset');
        },
        success: function(res) {
          window.swal("Success!", res.msg, "success")
          .then(function() {
              window.location = window.location;
              // window.toastr.success(res.msg);
          });
        },
        error: function(xhr, ajaxOptions, thrownError) {
          alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
      });
    });

    // Corte de Caja
    $("#btn-corte-caja").on("click", function(e) {
      e.preventDefault();
      $.ajax({
        url: window.baseUrl + "/_inc/ajax.php?type=GETCASHBOOKSUMMARY",
        dataType: "JSON",
        type: "GET",
        success: function(res) {
          $("#corte-apertura").text(res.opening_balance);
          $("#corte-ingreso").text(res.today_income);
          $("#corte-efectivo").text(res.ingreso_efectivo);
          $("#corte-credito").text(res.tarjeta_credito);
          $("#corte-debito").text(res.tarjeta_debito);
          $("#corte-gastos").text(res.total_expense);
          $("#corte-saldo-final").text(res.saldo_final);
          $("#corte-efectivo-contado").val('');
          $("#corte-diferencia-row").hide();
          $("#modal-corte-caja").modal('show');
        },
        error: function() {
          $("#modal-corte-caja").modal('show');
        }
      });
    });

    $("#corte-efectivo-contado").on("input", function() {
      var contado = parseFloat($(this).val()) || 0;
      var saldoText = $("#corte-saldo-final").text().replace(/,/g, '');
      var saldo = parseFloat(saldoText) || 0;
      var diferencia = contado - saldo;
      var clase = diferencia >= 0 ? 'text-success' : 'text-danger';
      $("#corte-diferencia").removeClass('text-success text-danger').addClass(clase)
        .text((diferencia >= 0 ? '+' : '') + diferencia.toFixed(2));
      $("#corte-diferencia-row").show();
    });

    $("#btn-confirmar-corte").on("click", function() {
      var notas = $("#corte-notas").val();
      var saldoText = $("#corte-saldo-final").text().replace(/,/g, '');
      var efectivoContado = $("#corte-efectivo-contado").val();
      // Si no se ingresó efectivo contado, usar el saldo calculado del sistema
      if (!efectivoContado || parseFloat(efectivoContado) === 0) {
        efectivoContado = saldoText;
      }
      $.ajax({
        url: window.baseUrl + "/_inc/ajax.php?type=CORTEDECAJA",
        dataType: "JSON",
        type: "POST",
        data: { efectivo_contado: efectivoContado, notas: notas, saldo_final: saldoText },
        success: function(res) {
          $("#modal-corte-caja").modal('hide');
          window.swal("Corte Registrado", res.msg, "success")
            .then(function() {
              window.printContent('cashbook-summary', {
                title: 'Corte de Caja - <?php echo date('d/m/Y'); ?>',
                headline: 'Corte de Caja - <?php echo date('d/m/Y'); ?>',
                screenSize: 'fullScreen'
              });
            });
        },
        error: function(xhr) {
          try {
            var err = JSON.parse(xhr.responseText);
            window.swal("Error", err.errorMsg, "error");
          } catch(e) {
            alert(xhr.responseText);
          }
        }
      });
    });
  });
</script>

<?php include ("footer.php"); ?>