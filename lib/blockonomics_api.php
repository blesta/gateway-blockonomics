<?php
/**
 * Blockonomics API client
 *
 * Thin wrapper over the Blockonomics REST API used by the gateway.
 * API documentation: https://www.blockonomics.co/views/api.html
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.blockonomics
 * @copyright Copyright (c) 2024, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class BlockonomicsApi
{
    /**
     * @var string The Blockonomics base URL
     */
    private $base_url = 'https://www.blockonomics.co';

    /**
     * @var string The Blockonomics API key
     */
    private $api_key;

    /**
     * @param string $api_key The Blockonomics API key
     */
    public function __construct($api_key)
    {
        $this->api_key = trim((string) $api_key);
    }

    /**
     * Fetches the wallets configured on the Blockonomics account
     *
     * @return array The response (see BlockonomicsApi::request())
     */
    public function getWallets()
    {
        return $this->request('GET', '/api/v2/wallets');
    }

    /**
     * Fetches the stores configured on the Blockonomics account, including their wallets
     *
     * @return array The response (see BlockonomicsApi::request())
     */
    public function getStores()
    {
        return $this->request('GET', '/api/v2/stores', ['wallets' => 'true']);
    }

    /**
     * Fetches the current BTC price in the given fiat currency
     *
     * @param string $currency The ISO 4217 fiat currency code
     * @return array The response (see BlockonomicsApi::request())
     */
    public function getPrice($currency)
    {
        return $this->request('GET', '/api/price', ['currency' => $currency, 'crypto' => 'BTC'], false);
    }

    /**
     * Requests a new BTC address from the store whose callback URL matches the given URL.
     * Never passes reset=1: doing so resets the xpub index on the Blockonomics side.
     *
     * @param string $callback_url The callback URL configured on the Blockonomics store
     * @return array The response (see BlockonomicsApi::request())
     */
    public function getNewAddress($callback_url)
    {
        return $this->request('POST', '/api/new_address', ['match_callback' => $callback_url]);
    }

    /**
     * Sends a request to the Blockonomics API
     *
     * @param string $method The HTTP method (GET or POST)
     * @param string $path The API path, e.g. /api/price
     * @param array $query Query string parameters
     * @param bool $auth Whether to send the Authorization header
     * @return array The response including:
     *  - code The HTTP status code (0 on a transport error)
     *  - body The JSON-decoded response body (stdClass), or null
     *  - error A transport error message, if any
     */
    private function request($method, $path, array $query = [], $auth = true)
    {
        $url = $this->base_url . $path . (empty($query) ? '' : '?' . http_build_query($query));

        $headers = ['Accept: application/json'];
        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $this->api_key;
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if (strtoupper($method) === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, '');
        }

        if (Configure::get('Blesta.curl_verify_ssl')) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        $raw = curl_exec($curl);
        $error = curl_errno($curl) ? curl_error($curl) : null;
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $body = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw);
            $body = is_object($decoded) ? $decoded : null;
        }

        return ['code' => $error ? 0 : $code, 'body' => $body, 'error' => $error];
    }
}
