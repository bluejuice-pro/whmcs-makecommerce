<?php
/**
 * Minimal MakeCommerce API client.
 *
 * @author MB Bluejuice <https://bluejuice.pro>
 */

namespace Bluejuice\MakeCommerce;

class Api
{
    const VERSION = '1.0.0';
    const LIVE_URL = 'https://api.maksekeskus.ee/v1';
    const TEST_URL = 'https://api.test.maksekeskus.ee/v1';

    private $shopId;
    private $secretKey;
    private $baseUrl;

    public function __construct($shopId, $secretKey, $testMode = false)
    {
        $this->shopId = trim($shopId);
        $this->secretKey = trim($secretKey);
        $this->baseUrl = $testMode ? self::TEST_URL : self::LIVE_URL;
    }

    public static function fromParams(array $params)
    {
        return new self($params['shopId'], $params['secretKey'], !empty($params['testMode']));
    }

    /** Ties the pay form to one invoice so the redirect script cannot be called for arbitrary ids. */
    public static function invoiceToken($invoiceId, $secretKey)
    {
        return hash_hmac('sha256', 'invoice:' . (int) $invoiceId, $secretKey);
    }

    public function createTransaction(array $data)
    {
        return $this->request('POST', '/transactions', $data);
    }

    public function refund($transactionId, $amount, $comment)
    {
        return $this->request('POST', '/transactions/' . rawurlencode($transactionId) . '/refunds', [
            'amount' => number_format((float) $amount, 2, '.', ''),
            'comment' => $comment,
        ]);
    }

    /** MAC = UPPERCASE(HEX(SHA-512(json + secret key))). */
    public function verifyMac($json, $mac)
    {
        $expected = strtoupper(hash('sha512', $json . $this->secretKey));

        return is_string($mac) && hash_equals($expected, strtoupper($mac));
    }

    /** URL of the hosted payment method selection page. */
    public static function paymentUrl(array $transaction)
    {
        $other = isset($transaction['payment_methods']['other']) ? $transaction['payment_methods']['other'] : [];
        foreach ($other as $method) {
            if (isset($method['name'], $method['url']) && $method['name'] === 'redirect') {
                return $method['url'];
            }
        }

        return null;
    }

    private function request($method, $path, ?array $body = null)
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->shopId . ':' . $this->secretKey,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('MakeCommerce request failed: ' . $error);
        }

        $data = json_decode($raw, true);
        if ($code < 200 || $code >= 300) {
            $message = isset($data['message']) ? $data['message'] : $raw;
            throw new \RuntimeException('MakeCommerce API error ' . $code . ': ' . $message);
        }

        return is_array($data) ? $data : [];
    }
}
