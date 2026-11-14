<?php
/**
 * MakeCommerce return, cancel and notification handler.
 *
 * @author MB Bluejuice <https://bluejuice.pro>
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../makecommerce/Api.php';

use Bluejuice\MakeCommerce\Api;
use WHMCS\Database\Capsule;

App::load_function('gateway');
App::load_function('invoice');

$gateway = getGatewayVariables('makecommerce');
if (empty($gateway['type'])) {
    die('Module not activated');
}

$type = isset($_GET['type']) ? $_GET['type'] : 'return';
$isNotification = ($type === 'notify');
$systemUrl = rtrim($gateway['systemurl'], '/');

// Server notifications get a plain status line, browsers get sent back to the invoice.
$finish = function ($invoiceId, $paid) use ($isNotification, $systemUrl) {
    if ($isNotification) {
        http_response_code(200);
        echo 'OK';
        exit;
    }
    $url = $invoiceId
        ? $systemUrl . '/viewinvoice.php?id=' . (int) $invoiceId . ($paid ? '&paymentsuccess=true' : '&paymentfailed=true')
        : $systemUrl . '/clientarea.php?action=invoices';
    header('Location: ' . $url);
    exit;
};

$json = isset($_REQUEST['json']) ? (string) $_REQUEST['json'] : '';
$mac = isset($_REQUEST['mac']) ? (string) $_REQUEST['mac'] : '';
// WHMCS may add slashes to request input on older setups.
if ($json !== '' && json_decode($json) === null) {
    $json = stripslashes($json);
}

$api = Api::fromParams($gateway);
if ($json === '' || !$api->verifyMac($json, $mac)) {
    logTransaction($gateway['paymentmethod'], ['type' => $type, 'json' => $json], 'Invalid MAC');
    if ($isNotification) {
        http_response_code(400);
        exit('Invalid MAC');
    }
    $finish(0, false);
}

$message = json_decode($json, true);
$merchantData = isset($message['merchant_data']) ? json_decode($message['merchant_data'], true) : null;
$invoiceId = isset($merchantData['invoice_id']) ? (int) $merchantData['invoice_id'] : (int) (isset($message['reference']) ? $message['reference'] : 0);
$transactionId = isset($message['transaction']) ? (string) $message['transaction'] : '';
$status = isset($message['status']) ? $message['status'] : '';

if ($status !== 'COMPLETED' || !$invoiceId || $transactionId === '') {
    logTransaction($gateway['paymentmethod'], $message, 'Not paid (' . $status . ')');
    $finish($invoiceId, false);
}

$invoiceId = checkCbInvoiceID($invoiceId, $gateway['name']);

// Return and notification both arrive for the same payment; record it once.
$recorded = Capsule::table('tblaccounts')->where('transid', $transactionId)->exists();
if (!$recorded) {
    addInvoicePayment($invoiceId, $transactionId, (float) $message['amount'], 0.00, $gateway['paymentmethod']);
    logTransaction($gateway['paymentmethod'], $message, 'Successful');
}

$finish($invoiceId, true);
