<?php
/**
 * MakeCommerce payment gateway for WHMCS.
 *
 * @author    MB Bluejuice <https://bluejuice.pro>
 * @copyright MB Bluejuice
 * @license   MIT
 * @version   1.0.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/makecommerce/Api.php';

use Bluejuice\MakeCommerce\Api;

function makecommerce_MetaData()
{
    return [
        'DisplayName' => 'MakeCommerce',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

function makecommerce_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'MakeCommerce (bank links, cards)',
        ],
        'shopId' => [
            'FriendlyName' => 'Shop ID',
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Merchant Portal → Settings → API',
        ],
        'secretKey' => [
            'FriendlyName' => 'Secret key',
            'Type' => 'password',
            'Size' => '70',
        ],
        'publishableKey' => [
            'FriendlyName' => 'Publishable key',
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Not used by redirect payments, kept with the other shop keys',
        ],
        'testMode' => [
            'FriendlyName' => 'Test mode',
            'Type' => 'yesno',
            'Description' => 'Use the test environment (needs test shop credentials)',
        ],
        'defaultCountry' => [
            'FriendlyName' => 'Default country',
            'Type' => 'dropdown',
            'Options' => ['lt' => 'Lithuania', 'lv' => 'Latvia', 'ee' => 'Estonia', 'fi' => 'Finland'],
            'Description' => 'Preselected when the client country is not supported',
        ],
    ];
}

/**
 * Pay button on the invoice. The transaction itself is created only
 * after the click, so viewing an invoice never calls the API.
 */
function makecommerce_link($params)
{
    $invoiceId = (int) $params['invoiceid'];
    $action = rtrim($params['systemurl'], '/') . '/modules/gateways/makecommerce/redirect.php';

    return sprintf(
        '<form method="post" action="%s">'
        . '<input type="hidden" name="invoice" value="%d">'
        . '<input type="hidden" name="token" value="%s">'
        . '<button type="submit" class="btn btn-primary">%s</button>'
        . '</form>',
        htmlspecialchars($action, ENT_QUOTES),
        $invoiceId,
        Api::invoiceToken($invoiceId, $params['secretKey']),
        htmlspecialchars($params['langpaynow'], ENT_QUOTES)
    );
}

function makecommerce_refund($params)
{
    $api = Api::fromParams($params);

    try {
        $refund = $api->refund($params['transid'], $params['amount'], 'WHMCS invoice #' . $params['invoiceid']);
    } catch (\Exception $e) {
        return ['status' => 'error', 'rawdata' => $e->getMessage()];
    }

    return [
        'status' => 'success',
        'rawdata' => $refund,
        'transid' => isset($refund['id']) ? $refund['id'] : $params['transid'],
    ];
}
