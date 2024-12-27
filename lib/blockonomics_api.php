<?php
use Blesta\Core\Util\Common\Traits\Container;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'blockonomics_response.php';

/**
 * Blockonomics API
 *
 * @package blesta
 * @subpackage blesta.components.modules.blockonomics
 * @copyright Copyright (c) 2024, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class BlockonomicsApi
{
    // Load traits
    use Container;

    /**
     * @var string The Blockonomics API URL
     */
    private $api_url = 'https://www.blockonomics.co/api';

    /**
     * @var string The Blockonomics API Key
     */
    private $api_key = '';

    /**
     * @var array The data sent with the last request served by this API
     */
    private $last_request = [];

    /**
     * Initializes the request parameter
     */
    public function __construct($api_key)
    {
        $this->api_key = $api_key;

        // Initialize logger
        $logger = $this->getFromContainer('logger');
        $this->logger = $logger;
    }

    /**
     * Send an API request to Blockonomics
     *
     * @param string $route The path to the API method
     * @param string $method Data transfer method (POST, GET, PUT, DELETE)
     * @param array $params The data to be sent
     * @return BlockonomicsResponse
     */
    public function apiRequest(string $route, string $method, array $params = [])
    {
        $url = $this->api_url . '/' . ltrim($route, '/');
        $curl = curl_init();

        switch (strtoupper($method)) {
            case 'DELETE':
                // Set data using get parameters
            case 'GET':
                $url .= empty($params) ? '' : '?' . http_build_query($params);
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
                // Use the default behavior to set data fields
            default:
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
                break;
        }

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSLVERSION, 1);

        if (Configure::get('Blesta.curl_verify_ssl')) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        // Set authorization header
        $headers = ['Authorization: Bearer ' . $this->api_key];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $this->last_request = ['content' => $params, 'headers' => $headers];
        $result = curl_exec($curl);

        if ($result == false) {
            $this->logger->error(curl_error($curl));
        }

        if (curl_errno($curl)) {
            $error = [
                'error' => 'Curl Error',
                'message' => 'An internal error occurred, or the server did not respond to the request.',
                'status' => 500
            ];

            return new BlockonomicsResponse(['content' => json_encode($error), 'headers' => []]);
        }
        curl_close($curl);

        // Return request response
        return new BlockonomicsResponse($result);
    }

    /**
     * Get balance for multiple addresses/xpubs
     *
     * @param array $addresses List of Bitcoin addresses or xpubs
     * @return BlockonomicsResponse Response from Blockonomics API
     */
    public function getBalance($addresses)
    {
        $data = ['addr' => implode(' ', $addresses)];

        return $this->apiRequest('/balance', 'POST', $data);
    }

    /**
     * Get transaction history for multiple addresses/xpubs
     *
     * @param array $addresses List of Bitcoin addresses or xpubs
     * @return BlockonomicsResponse Response from Blockonomics API
     */
    public function getTransactionHistory($addresses)
    {
        $data = ['addr' => implode(' ', $addresses)];

        return $this->apiRequest('/searchhistory', 'POST', $data);
    }

    /**
     * Get details of a specific transaction
     *
     * @param string $txid Transaction ID
     * @return BlockonomicsResponse Response from Blockonomics API
     */
    public function getTransactionDetail($txid)
    {
        return $this->apiRequest('/tx_detail?txid=' . $txid, 'GET');
    }

    /**
     * Get transaction receipt for a given transaction ID and address
     *
     * @param string $txid Transaction ID
     * @param string $address User address
     * @return BlockonomicsResponse Transaction receipt URL
     */
    public function getTransactionReceipt($txid, $address)
    {
        return $this->apiRequest('/tx?txid=' . $txid . '&addr=' . $address, 'GET');
    }

    /**
     * Get order
     *
     * @param string $txid Transaction ID
     * @param string $address User address
     * @return BlockonomicsResponse Transaction receipt URL
     */
    public function getOrder($uuid)
    {
        return $this->apiRequest('/merchant_order/' . $uuid, 'GET');
    }

    /**
     * Create a new wallet
     *
     * @param string $name Wallet name
     * @param string $xpub Xpub address
     * @return BlockonomicsResponse Response from Blockonomics API
     */
    public function createWallet($name, $xpub)
    {
        $url = '/v2/wallets';
        $data = ['name' => $name, 'address' => $xpub, 'crypto' => 'BTC'];

        return $this->apiRequest($url, 'POST', $data);
    }

    /**
     * Update an existing wallet
     *
     * @param int $id Wallet ID
     * @param string $name New wallet name
     * @param int $gap_limit New gap limit
     * @return BlockonomicsResponse Response from Blockonomics API
     */
    public function updateWallet($id, $name, $gap_limit)
    {
        $url = '/v2/wallets/' . $id;
        $data = ['name' => $name, 'gap_limit' => $gap_limit];

        return $this->apiRequest($url, 'POST', $data);
    }

    /**
     * Delete an existing wallet
     *
     * @param int $id Wallet ID
     * @return BlockonomicsResponse Response from Blockonomics API
     */
    public function deleteWallet($id)
    {
        $url = '/v2/wallets/' . $id;

        return $this->apiRequest($url, 'DELETE');
    }

    /**
     * Creates a new address
     *
     * @param int $reset Set to 1 to reset the index
     * @return BlockonomicsResponse Response from Blockonomics API
     */
    public function newAddress(array $params = [], int $reset = null)
    {
        $url = '/new_address' . (!is_null($reset) ? '?reset=' . $reset : '');

        if (!empty($params)) {
            if (!is_null($reset)) {
                $url .= http_build_query($params);
            } else {
                $url .= '?' . http_build_query($params);
            }
        }

        return $this->apiRequest($url, 'POST');
    }

    /**
     * Fetches the current BTC price in a given currency
     *
     * @param string $currency The currency to fetch the BTC price
     * @return BlockonomicsResponse Response from Blockonomics API
     */
    public function price(string $currency)
    {
        $url = '/price';

        return $this->apiRequest($url, 'GET', ['currency' => $currency]);
    }

    /**
     * Creates a new temporary product
     *
     * @param string $parent_uid The parent UID of the main product/button
     * @param array $product The new temporary product to create, must include:
     *  - product_name The name of the product
     *  - product_description The description of the product (optional)
     *  - value The price of the product in BTC
     *  - extra_data Custom data
     * @return BlockonomicsResponse Response from Blockonomics API
     */
    public function createTemporaryProduct($parent_uid, array $product)
    {
        $url = '/create_temp_product';
        $data = ['parent_uid' => $parent_uid] + $product;

        return $this->apiRequest($url, 'POST', $data);
    }
}
