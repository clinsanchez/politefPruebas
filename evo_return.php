<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
require_once __DIR__.'/evo_config.php';
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/ripcord.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$orderId   = $_GET['order_id'] ?? '';
$invoiceId = $_GET['invoice_id'] ?? '';
$resultInd = $_GET['resultIndicator'] ?? '';

if (!$orderId || !isset($_SESSION['evo'][$orderId])) { header("Location: pagos.php?error=return_state"); exit; }

$entry = $_SESSION['evo'][$orderId];
$ok = ($resultInd && isset($entry['successIndicator']) && $resultInd === $entry['successIndicator']);

try { $order = evo_api_call('POST', "/order/{$orderId}", [ "apiOperation" => "RETRIEVE_ORDER" ]); }
catch (Exception $e) { $order = null; }

$gatewayStatus = $order['result'] ?? 'UNKNOWN';

if ($ok && $gatewayStatus === 'SUCCESS') {
    try {
        $odooUrl = $_ENV['ODOO_URL']; $odooDb  = $_ENV['ODOO_DB']; $odooUser= $_ENV['ODOO_USER']; $odooPass= $_ENV['ODOO_PASS'];
        $common = ripcord::client("{$odooUrl}common");
        $uid = $common->authenticate($odooDb, $odooUser, $odooPass, []);
        if (!$uid) throw new Exception("No se pudo autenticar en Odoo.");
        $models = ripcord::client("{$odooUrl}object");
        $inv = $models->execute_kw($odooDb, $uid, $odooPass, 'account.move', 'read', [[(int)$invoiceId]], ['fields'=>['id','state','amount_residual','name','move_type']]);
        if (!$inv) throw new Exception("Factura no encontrada.");
        if ($inv[0]['state'] != 'posted') { $models->execute_kw($odooDb, $uid, $odooPass, 'account.move', 'action_post', [[(int)$invoiceId]]); }
        $journalId = isset($_ENV['ODOO_PAYMENT_JOURNAL_ID']) ? (int)$_ENV['ODOO_PAYMENT_JOURNAL_ID'] : null;
        $methodId  = isset($_ENV['ODOO_PAYMENT_METHOD_ID']) ? (int)$_ENV['ODOO_PAYMENT_METHOD_ID'] : null;
        $ctx = ['active_model'=>'account.move','active_ids'=>[(int)$invoiceId]];
        $wiz_vals = ['amount'=>round((float)$entry['amount'], 2),'journal_id'=>$journalId,'group_payment'=>false];
        if ($methodId) $wiz_vals['payment_method_line_id'] = $methodId;
        $wiz_id = $models->execute_kw($odooDb, $uid, $odooPass, 'account.payment.register', 'create', [$wiz_vals], ['context'=>$ctx]);
        $models->execute_kw($odooDb, $uid, $odooPass, 'account.payment.register', 'action_create_payments', [[$wiz_id]], ['context'=>$ctx]);
        unset($_SESSION['evo'][$orderId]);
        header("Location: pagos.php?paid=1&invoice_id={$invoiceId}"); exit;
    } catch (Exception $e) {
        header("Location: pagos.php?paid=0&invoice_id={$invoiceId}&error_odoo=1"); exit;
    }
} else {
    header("Location: pagos.php?paid=0&invoice_id={$invoiceId}&error=bad_indicator"); exit;
}
