<style id="styles" type="text/css">
<?php 
$template_id = get_preference('receipt_template') ? get_preference('receipt_template') : 1;
echo html_entity_decode(get_the_postemplate($template_id,'template_css'));
?>
</style>
<?php
include DIR_VENDOR.'parser/lex/lib/Lex/ArrayableInterface.php';
include DIR_VENDOR.'parser/lex/lib/Lex/ArrayableObjectExample.php';
include DIR_VENDOR.'parser/lex/lib/Lex/Parser.php';
include DIR_VENDOR.'parser/lex/lib/Lex/ParsingException.php';
$data = get_postemplate_data($invoice_id);
$parser = new Lex\Parser();
$template = html_entity_decode(get_the_postemplate($template_id,'template_content'));
echo $parser->parse($template, $data);
?>

<div class="table-responsive footer-actions">
  <table class="table">
    <tbody>
      <tr class="no-print">
        <td colspan="2">
          <button onclick="printToTicketPrinter('<?php echo htmlspecialchars($invoice_id, ENT_QUOTES); ?>')" class="btn btn-info btn-block" id="btn-ticket-print">
            <span class="fa fa-fw fa-print"></span>
            <?php echo trans('button_print'); ?>
          </button>
        </td>
      </tr>
      <script>
      function printToTicketPrinter(invoiceId) {
        var btn = document.getElementById('btn-ticket-print');
        var originalLabel = '<span class="fa fa-fw fa-print"></span> <?php echo trans('button_print'); ?>';
        btn.disabled = true;
        btn.innerHTML = '<span class="fa fa-fw fa-spinner fa-spin"></span> Imprimiendo...';

        function resetBtn() {
          btn.disabled = false;
          btn.innerHTML = originalLabel;
        }

        if (typeof qz === 'undefined') {
          if (window.toastr) toastr.error('QZ Tray no está cargado. Verifica que qz-tray.js esté instalado.', 'Error');
          resetBtn();
          return;
        }

        // Unsigned mode — for internal/private POS use
        qz.security.setCertificatePromise(function(resolve) { resolve(); });
        qz.security.setSignaturePromise(function() { return function(resolve) { resolve(); }; });

        $.get(baseUrl + '/_inc/print_invoice.php', { invoice_id: invoiceId, mode: 'data' })
          .done(function(response) {
            if (!response.success || !response.data) {
              if (window.toastr) toastr.error('No se recibieron datos de impresión.', 'Error');
              resetBtn();
              return;
            }

            var printData = [{ type: 'raw', format: 'base64', data: response.data }];

            qz.websocket.connect()
              .then(function() { return qz.printers.find(response.printer_name); })
              .then(function(qzPrinter) {
                return qz.print(qz.configs.create(qzPrinter), printData);
              })
              .then(function() {
                if (window.toastr) toastr.success('Impreso correctamente', '');
                resetBtn();
              })
              .catch(function(err) {
                var msg = (err && err.message) ? err.message : String(err);
                if (msg.indexOf('Unable to establish connection') !== -1 || msg.indexOf('connect') !== -1) {
                  msg = 'QZ Tray no está ejecutándose. Ábrelo en tu PC e intenta de nuevo.';
                }
                if (window.toastr) toastr.error(msg, 'Error de impresión');
                resetBtn();
              })
              .finally(function() {
                if (qz.websocket.isActive()) qz.websocket.disconnect();
              });
          })
          .fail(function(xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.errorMsg)
              ? xhr.responseJSON.errorMsg
              : (xhr.responseText ? xhr.responseText.substring(0, 300) : 'Error al imprimir');
            if (window.toastr) toastr.error(msg, 'Error');
            else alert(msg);
            resetBtn();
          });
      }
      </script>
      <?php if ((user_group_id() == 1 || has_permission('access', 'sms_sell_invoice')) && get_preference('sms_alert')):?>
        <tr class="no-print">
          <td colspan="2">
            <button id="sms-btn" data-invoiceid="<?php echo $invoice_id; ?>" class="btn btn-danger btn-block">
              <span class="fa fa-fw fa-comment-o"></span> 
              <?php echo trans('button_send_sms'); ?>
            </button>
          </td>
        </tr>
      <?php endif; ?>
      <?php if ((user_group_id() == 1 || has_permission('access', 'email_sell_invoice'))):?>
        <tr class="no-print">
          <td colspan="2">
            <button id="email-btn" data-customerName="<?php echo $invoice_info['customer_name']; ?>" data-invoiceid="<?php echo $invoice_id;?>" class="btn btn-success btn-block">
              <span class="fa fa-fw fa-envelope-o"></span> 
              <?php echo trans('button_send_email'); ?>
            </button>
          </td>
        </tr>
      <?php endif;?>
      <tr class="no-print">
        <td colspan="2">
          <a class="btn btn-default btn-block" href="pos.php">
            &larr; <?php echo trans('button_back_to_pos'); ?>
          </a>
        </td>
      </tr>
      <tr class="text-center">
        <td colspan="2">
          <br>
          <p class="powered-by">
            <small>&copy; ITsolution24.com</small>
          </p>
        </td>
      </tr>
    </tbody>
  </table>
</div>