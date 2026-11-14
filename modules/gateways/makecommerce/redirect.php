<?php
/**
 * Creates a MakeCommerce transaction for an invoice and sends the client to the payment page.
 *
 * @author MB Bluejuice <https://bluejuice.pro>
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/Api.php';

use Bluejuice\MakeCommerce\Api;

App::load_function('gateway');
App::load_function('invoice');

$gateway = getGatewayVariables('makecommerce');
if (empty($gateway['type'])) {
    die('Module not activated');
}

$invoiceId = isset($_POST['invoice']) ? (int) $_POST['invoice'] : 0;
$token = isset($_POST['token']) ? (string) $_POST['token'] : '';
$systemUrl = rtrim($gateway['systemurl'], '/');

if (!$invoiceId || !hash_equals(Api::invoiceToken($invoiceId, $gateway['secretKey']), $token)) {
    header('Location: ' . $systemUrl . '/clientarea.php?action=invoices');
    exit;
}

$invoiceUrl = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;

$invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
if ($invoice['result'] !== 'success' || $invoice['status'] !== 'Unpaid' || (float) $invoice['balance'] <= 0) {
    header('Location: ' . $invoiceUrl);
    exit;
}

$client = localAPI('GetClientsDetails', ['clientid' => $invoice['userid'], 'stats' => false]);

$locales = ['lithuanian' => 'lt', 'latvian' => 'lv', 'estonian' => 'et', 'russian' => 'ru', 'english' => 'en'];
$language = isset($client['language']) ? strtolower($client['language']) : '';
$country = isset($client['countrycode']) ? strtolower($client['countrycode']) : '';
if (!in_array($country, ['lt', 'lv', 'ee', 'fi'], true)) {
    $country = $gateway['defaultCountry'] ?: 'lt';
}

$callback = $systemUrl . '/modules/gateways/callback/makecommerce.php';
$api = Api::fromParams($gateway);

try {
    $transaction = $api->createTransaction([
        'transaction' => [
            'amount' => number_format((float) $invoice['balance'], 2, '.', ''),
            'currency' => isset($client['currency_code']) ? $client['currency_code'] : 'EUR',
            'reference' => (string) $invoiceId,
            'merchant_data' => json_encode(['invoice_id' => $invoiceId]),
            'transaction_url' => [
                'return_url' => ['url' => $callback . '?type=return', 'method' => 'POST'],
                'cancel_url' => ['url' => $callback . '?type=cancel', 'method' => 'POST'],
                'notification_url' => ['url' => $callback . '?type=notify', 'method' => 'POST'],
            ],
        ],
        'customer' => [
            'email' => isset($client['email']) ? $client['email'] : '',
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'country' => $country,
            'locale' => isset($locales[$language]) ? $locales[$language] : 'en',
        ],
        'app_info' => [
            'name' => 'bluejuice-whmcs-makecommerce',
            'version' => Api::VERSION,
            'platform' => 'WHMCS',
            'platform_version' => isset($CONFIG['Version']) ? $CONFIG['Version'] : '',
        ],
    ]);
    $url = Api::paymentUrl($transaction);
    if (!$url) {
        throw new \RuntimeException('No redirect URL in the transaction response');
    }
} catch (\Exception $e) {
    logTransaction($gateway['paymentmethod'], ['invoice' => $invoiceId, 'error' => $e->getMessage()], 'Transaction not created');
    header('Location: ' . $invoiceUrl . '&paymentfailed=true');
    exit;
}

header('Location: ' . $url);
exit;
