<?php
/**
 * Blockonomics Gateway
 *
 * Accepts Bitcoin payments through the Blockonomics API. Payments go directly
 * to the merchant's own wallet.
 *
 * The Blockonomics API documentation can be found at:
 * https://www.blockonomics.co/views/api.html
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.blockonomics
 * @copyright Copyright (c) 2024, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Blockonomics extends NonmerchantGateway
{
    /**
     * Number of confirmations required before a payment is applied
     */
    const CONFIRMATIONS = 2;

    /**
     * Seconds after which an unpaid quote is refreshed with the current price
     */
    const QUOTE_TTL = 600;

    /**
     * Seconds after which an order stuck at 0 confirmations is abandoned
     */
    const UNCONFIRMED_TTL = 14400;

    /**
     * Satoshis per BTC
     */
    const SATOSHI = 100000000;

    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * @var BlockonomicsOrders The orders store
     */
    private $orders;

    /**
     * @var string The ISO 4217 currency code of the payment being processed
     */
    private $currency;

    /**
     * Construct a new non-merchant gateway
     */
    public function __construct()
    {
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'blockonomics_api.php');
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'blockonomics_orders.php');

        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        Loader::loadComponents($this, ['Input']);

        Language::loadLang('blockonomics', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(?array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Performs any necessary bootstraping actions
     */
    public function install()
    {
        $this->getOrders()->createTable();
    }

    /**
     * Performs migration of data from $current_version (the current installed version)
     * to the given file set version
     *
     * @param string $current_version The current installed version of this gateway
     */
    public function upgrade($current_version)
    {
        if (version_compare($current_version, '2.1.0', '<')) {
            $this->getOrders()->createTable();
        }
    }

    /**
     * Performs any necessary cleanup actions
     *
     * @param int $gateway_id The ID of the gateway being uninstalled
     * @param bool $last_instance True if $gateway_id is the last instance across all companies for
     *  this gateway, false otherwise
     */
    public function uninstall($gateway_id, $last_instance)
    {
        if ($last_instance) {
            $this->getOrders()->dropTable();
        } else {
            $this->getOrders()->deleteByGateway($gateway_id);
        }
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(?array $meta = null)
    {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Blesta replaces all meta on save, so the secret is kept in a hidden form field
        if (empty($meta['callback_secret'])) {
            $meta['callback_secret'] = sha1(random_bytes(20));
        }

        $this->view->set('meta', $meta);
        $this->view->set('callback_url', $this->getCallbackUrl($meta['callback_secret']));
        $this->view->set('test_setup_url', $this->getGatewayUrl(['action' => 'test_setup']));
        $this->view->set('api_key_url', 'https://www.blockonomics.co/dashboard#/store');

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        $meta['api_key'] = trim($meta['api_key'] ?? '');

        if (empty($meta['callback_secret']) || !preg_match('/^[a-f0-9]{40}$/', $meta['callback_secret'])) {
            $meta['callback_secret'] = sha1(random_bytes(20));
        }

        $rules = [
            'api_key' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Blockonomics.!error.api_key.empty', true)
                ],
                'valid' => [
                    'rule' => function ($api_key) {
                        if ($api_key === '') {
                            return true;
                        }

                        return $this->getApi($api_key)->getWallets()['code'] != 401;
                    },
                    'message' => Language::_('Blockonomics.!error.api_key.valid', true)
                ]
            ]
        ];
        $this->Input->setRules($rules);
        $this->Input->validates($meta);

        return [
            'api_key' => $meta['api_key'],
            'callback_secret' => $meta['callback_secret']
        ];
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['api_key', 'callback_secret'];
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Returns all HTML markup required to render the payment form
     *
     * @param array $contact_info An array of contact info
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     * @return string HTML markup required to render the payment form
     */
    public function buildProcess(array $contact_info, $amount, ?array $invoice_amounts = null, ?array $options = null)
    {
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));
        Loader::loadHelpers($this, ['Html', 'CurrencyFormat']);

        $this->view->set('confirmations', self::CONFIRMATIONS);
        $this->view->set('assets_url', WEBDIR . 'components/gateways/nonmerchant/blockonomics/views/default/');
        $this->view->set('status_url', $this->getGatewayUrl(['action' => 'status']));
        $this->view->set('return_url', $options['return_url'] ?? null);

        if (empty($this->meta['api_key']) || empty($this->meta['callback_secret'])) {
            $this->view->set('error', Language::_('Blockonomics.!error.process.unconfigured', true));
            return $this->view->fetch();
        }

        $payment = [
            'client_id' => (int) $contact_info['client_id'],
            'amount' => (float) $amount,
            'currency' => $this->currency,
            'invoices' => $this->serializeInvoices($invoice_amounts ?? [])
        ];
        $payment_key = $this->getPaymentKey($payment);
        $previous = $this->getOrders()->getLatest($payment_key, true);

        try {
            $order = $this->getOrder($this->getOrders()->getLatest($payment_key), $payment);
        } catch (Exception $e) {
            $this->view->set('error', $e->getMessage());
            return $this->view->fetch();
        }

        $btc_amount = $this->formatBtc($order->expected_units);
        $this->view->set('order', $order);
        $this->view->set('awaiting', $order->status !== null);
        $this->view->set('underpaid', $previous && $previous->id != $order->id && $this->isUnderpaid($previous));
        $this->view->set('btc_amount', $btc_amount);
        $this->view->set('fiat_amount', $this->CurrencyFormat->format($order->amount, $order->currency));
        $this->view->set('rate', $this->CurrencyFormat->format(
            (float) $btc_amount > 0 ? (float) $order->amount / (float) $btc_amount : 0,
            $order->currency
        ));
        $this->view->set('uri', 'bitcoin:' . $order->address . '?amount=' . $btc_amount);

        return $this->view->fetch();
    }

    /**
     * Handles requests to this gateway's callback URL: Blockonomics payment notifications,
     * the checkout status poll, and the admin Test Setup check
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     */
    public function validate(array $get, array $post)
    {
        switch ($get['action'] ?? null) {
            case 'test_setup':
                $this->requireStaff();
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                    $this->respondJson([], 405);
                }
                $this->respondJson($this->testSetup());
                break;
            case 'status':
                $this->respondJson($this->paymentStatus($post));
                break;
        }

        return $this->processCallback($get);
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     */
    public function success(array $get, array $post)
    {
        $order = isset($get['order_id']) ? $this->getOrders()->get((int) $get['order_id']) : null;

        if (!$order || (int) $order->gateway_id !== (int) $this->gateway_id
            || (int) $order->client_id !== (int) ($get['client_id'] ?? 0)
        ) {
            return null;
        }

        return [
            'client_id' => $order->client_id,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'invoices' => $this->unserializeInvoices($order->invoices),
            'status' => $this->isPaid($order) ? 'approved' : 'pending',
            'transaction_id' => $this->getTransactionId($order)
        ];
    }

    /**
     * Refund a payment
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param float $amount The amount to refund this transaction
     * @param string $notes Notes about the refund that may be sent to the client by the gateway
     * @return array An array of transaction data
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * Void a payment or authorization.
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param string $notes Notes about the void that may be sent to the client by the gateway
     * @return array An array of transaction data
     */
    public function void($reference_id, $transaction_id, $notes = null)
    {
        $this->Input->setErrors($this->getCommonError('unsupported'));
    }

    /**
     * Returns the order to show for a payment, creating one when there is none to resume
     *
     * @param stdClass|false $order The latest order for this payment, if any
     * @param array $payment The payment (client_id, amount, currency, invoices)
     * @return stdClass The order
     * @throws Exception If Blockonomics could not supply a price or address
     */
    private function getOrder($order, array $payment)
    {
        $orders = $this->getOrders();

        if ($order && ($this->isAwaitingConfirmation($order) || $this->isPaid($order))) {
            return $order;
        }

        if ($order && $order->status === null && !$this->isTestAddress($order->address)) {
            if (time() - strtotime($order->date_updated) > self::QUOTE_TTL) {
                $orders->edit($order->id, [
                    'expected_units' => $this->toSatoshi($payment['amount'], $this->fetchPrice($payment['currency']))
                ]);
                $order = $orders->get($order->id);
            }

            return $order;
        }

        $price = $this->fetchPrice($payment['currency']);
        $order_id = $orders->add([
            'gateway_id' => $this->gateway_id,
            'client_id' => $payment['client_id'],
            'payment_key' => $this->getPaymentKey($payment),
            'currency' => $payment['currency'],
            'amount' => $payment['amount'],
            'invoices' => $payment['invoices'],
            'address' => $this->fetchNewAddress(),
            'expected_units' => $this->toSatoshi($payment['amount'], $price)
        ]);

        return $orders->get($order_id);
    }

    /**
     * Reports the payment progress of an order to the checkout page
     *
     * @param array $post The POST data including order_id
     * @return array The status
     */
    private function paymentStatus(array $post)
    {
        Loader::loadComponents($this, ['Session']);

        $order = isset($post['order_id']) ? $this->getOrders()->get((int) $post['order_id']) : null;
        if (!$order || (int) $order->gateway_id !== (int) $this->gateway_id
            || (int) $order->client_id !== (int) $this->Session->read('blesta_client_id')
        ) {
            return ['error' => Language::_('Blockonomics.!error.process.session', true)];
        }

        return [
            'status' => $order->status === null ? null : (int) $order->status,
            'txid' => (string) $order->txid,
            'paid' => $this->isPaid($order)
                && $this->hasTransaction($order),
            'underpaid' => $this->isUnderpaid($order)
        ];
    }

    /**
     * Processes a payment notification from Blockonomics
     *
     * @param array $get The GET data including secret, addr, status, value, txid
     * @return array|null The transaction data for Blesta, or null (with an error set) to ignore the notification
     */
    private function processCallback(array $get)
    {
        $this->log('callback', json_encode(array_diff_key($get, ['secret' => 1])), 'input', true);

        if (empty($this->meta['callback_secret'])
            || !hash_equals((string) $this->meta['callback_secret'], (string) ($get['secret'] ?? ''))
        ) {
            return $this->ignoreCallback('Invalid callback secret');
        }

        $address = (string) ($get['addr'] ?? '');
        $status = isset($get['status']) && is_numeric($get['status']) ? (int) $get['status'] : null;
        if ($address === '' || $status === null || $status < 0) {
            return $this->ignoreCallback('Missing address or invalid status');
        }

        $orders = $this->getOrders();
        $order = $orders->getByAddress($address);
        if (!$order || (int) $order->gateway_id !== (int) $this->gateway_id) {
            return $this->ignoreCallback('No order found for address ' . $address);
        }

        $current = $order->status === null ? null : (int) $order->status;
        if ($current !== null && $status < $current) {
            return $this->ignoreCallback('Stale callback ignored');
        }

        $txid = (string) ($get['txid'] ?? '');
        if ($current !== null && $current >= self::CONFIRMATIONS && $status >= self::CONFIRMATIONS
            && $this->hasTransaction($order)
        ) {
            return $this->ignoreCallback('Duplicate callback ignored');
        }

        $orders->advance($order->id, $status, max(0, (int) ($get['value'] ?? 0)), $txid);
        $order = $orders->get($order->id);
        if ((int) $order->status !== $status || $order->txid !== $txid) {
            return $this->ignoreCallback('Stale callback ignored');
        }

        $response = [
            'client_id' => $order->client_id,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'invoices' => $this->unserializeInvoices($order->invoices),
            'status' => 'pending',
            'reference_id' => $order->address,
            'transaction_id' => $this->getTransactionId($order),
            'parent_transaction_id' => null
        ];

        $expected = $this->formatBtc($order->expected_units);
        $received = $this->formatBtc($order->received_units);

        if ($status < self::CONFIRMATIONS) {
            $response['amount'] = $this->receivedFiat($order);
        } elseif ($this->isPaid($order)) {
            $response['status'] = 'approved';
            $response['message'] = (int) $order->received_units > (int) $order->expected_units
                ? Language::_('Blockonomics.transaction.overpaid', true, $received, $expected)
                : Language::_('Blockonomics.transaction.paid', true, $received);
        } else {
            $response['status'] = 'declined';
            $response['amount'] = $this->receivedFiat($order);
            $response['message'] = Language::_('Blockonomics.transaction.underpaid', true, $expected, $received);
        }

        $this->log('callback', json_encode($response), 'output', $response['status'] !== 'declined');

        return $response;
    }

    /**
     * Logs why a callback is ignored and sets it as an error, which tells Blesta not to record a transaction
     *
     * @param string $reason Why the callback is ignored
     * @return null
     */
    private function ignoreCallback($reason)
    {
        $this->log('callback', $reason, 'output', false);
        $this->Input->setErrors(['callback' => ['ignored' => $reason]]);

        return null;
    }

    /**
     * Whether a payment was detected for the order and is still confirming
     *
     * @param stdClass $order The order
     * @return bool
     */
    private function isAwaitingConfirmation($order)
    {
        return $order->status !== null
            && (int) $order->status < self::CONFIRMATIONS
            && !((int) $order->status === 0 && time() - strtotime($order->date_updated) > self::UNCONFIRMED_TTL);
    }

    /**
     * Whether the order is confirmed and paid in full
     *
     * @param stdClass $order The order
     * @return bool
     */
    private function isPaid($order)
    {
        return $order->status !== null
            && (int) $order->status >= self::CONFIRMATIONS
            && (int) $order->expected_units > 0
            && (int) $order->received_units >= (int) $order->expected_units;
    }

    /**
     * Whether the order is confirmed but short of the quoted amount
     *
     * @param stdClass $order The order
     * @return bool
     */
    private function isUnderpaid($order)
    {
        return $order->status !== null
            && (int) $order->status >= self::CONFIRMATIONS
            && (int) $order->received_units < (int) $order->expected_units;
    }

    /**
     * Whether the address was issued by a Blockonomics store in test mode
     *
     * @param string $address The address
     * @return bool
     */
    private function isTestAddress($address)
    {
        return strpos($address, '1TestBTCAddress') === 0;
    }

    /**
     * Returns the fiat value of what was received, capped at the amount due
     *
     * @param stdClass $order The order
     * @return float The fiat amount
     */
    private function receivedFiat($order)
    {
        if ((int) $order->expected_units <= 0) {
            return 0;
        }

        Loader::loadHelpers($this, ['CurrencyFormat']);

        return (float) $this->CurrencyFormat->cast(
            min((int) $order->received_units / (int) $order->expected_units * (float) $order->amount, (float) $order->amount),
            $order->currency
        );
    }

    /**
     * Whether Blesta has recorded a transaction for the order's payment
     *
     * @param stdClass $order The order
     * @return bool
     */
    private function hasTransaction($order)
    {
        Loader::loadModels($this, ['Transactions']);

        return (bool) $this->Transactions->getByTransactionId($this->getTransactionId($order), null, $this->gateway_id);
    }

    /**
     * Returns the Blesta transaction ID for an order
     *
     * @param stdClass $order The order
     * @return string|null The transaction ID
     */
    private function getTransactionId($order)
    {
        return (string) $order->txid === '' ? null : $order->txid;
    }

    /**
     * Fetches the BTC price for a fiat currency
     *
     * @param string $currency The fiat currency code
     * @return float The price of one BTC
     * @throws Exception If the price could not be fetched
     */
    private function fetchPrice($currency)
    {
        $response = $this->getApi($this->meta['api_key'])->getPrice($currency);

        if ($response['code'] != 200 || empty($response['body']->price) || (float) $response['body']->price <= 0) {
            $this->log('price', $this->apiErrorMessage($response), 'output', false);
            throw new Exception(Language::_('Blockonomics.!error.process.price', true));
        }

        return (float) $response['body']->price;
    }

    /**
     * Requests a new payment address from Blockonomics
     *
     * @return string The address
     * @throws Exception If no address could be issued
     */
    private function fetchNewAddress()
    {
        $callback_url = $this->getCallbackUrl($this->meta['callback_secret']);
        $response = $this->getApi($this->meta['api_key'])->getNewAddress($callback_url);

        if ($response['code'] != 200 || empty($response['body']->address)) {
            $this->log('new_address', $this->apiErrorMessage($response), 'output', false);
            throw new Exception(Language::_('Blockonomics.!error.process.address', true));
        }

        return (string) $response['body']->address;
    }

    /**
     * Converts a fiat amount to satoshis, rounded down
     *
     * @param float $amount The fiat amount
     * @param float $price The price of one BTC in that fiat currency
     * @return int The amount in satoshis
     * @throws Exception If the amount is below one satoshi
     */
    private function toSatoshi($amount, $price)
    {
        $satoshi = (int) floor(self::SATOSHI * (float) $amount / (float) $price);
        if ($satoshi <= 0) {
            throw new Exception(Language::_('Blockonomics.!error.process.price', true));
        }

        return $satoshi;
    }

    /**
     * Formats satoshis as a BTC amount without trailing zeros
     *
     * @param int $satoshi The amount in satoshis
     * @return string The BTC amount
     */
    private function formatBtc($satoshi)
    {
        $formatted = rtrim(rtrim(number_format((int) $satoshi / self::SATOSHI, 8, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * Builds the key that identifies a payment (gateway, client, amount, currency, and invoices)
     *
     * @param array $payment The payment
     * @return string The key
     */
    private function getPaymentKey(array $payment)
    {
        return sha1(implode('|', [
            $this->gateway_id,
            $payment['client_id'],
            $payment['currency'],
            number_format((float) $payment['amount'], 4, '.', ''),
            $payment['invoices']
        ]));
    }

    /**
     * Serializes an array of invoice info into a string
     *
     * @param array $invoices A numerically indexed array of invoices, each containing id and amount
     * @return string A string in the format id1=amount1|id2=amount2
     */
    private function serializeInvoices(array $invoices)
    {
        // Blesta pages pass the same amount in different formats (49.00, 49.0000), so normalize
        $pairs = [];
        foreach ($invoices as $invoice) {
            $pairs[(int) $invoice['id']] = (int) $invoice['id'] . '=' . number_format((float) $invoice['amount'], 4, '.', '');
        }
        ksort($pairs);

        return implode('|', $pairs);
    }

    /**
     * Unserializes a string of invoice info into an array
     *
     * @param string $str A string in the format id1=amount1|id2=amount2
     * @return array A numerically indexed array of invoices, each containing id and amount
     */
    private function unserializeInvoices($str)
    {
        $invoices = [];
        foreach (explode('|', (string) $str) as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) == 2) {
                $invoices[] = ['id' => $parts[0], 'amount' => $parts[1]];
            }
        }

        return $invoices;
    }

    /**
     * Checks the saved API key and Blockonomics store configuration
     *
     * @return array The test result including:
     *  - status "ok" if setup is complete, "setup" if a setup step is missing, or "error"
     *  - message The message to show when status is not "ok"
     */
    private function testSetup()
    {
        $setup = function ($message) {
            return ['status' => 'setup', 'message' => Language::_('Blockonomics.test_setup.' . $message, true)];
        };
        $error = function ($message, $response = null) {
            return [
                'status' => 'error',
                'message' => Language::_('Blockonomics.!error.test_setup.' . $message, true, $response ? $this->apiErrorMessage($response) : '')
            ];
        };

        if (empty($this->meta['api_key']) || empty($this->meta['callback_secret'])) {
            return $error('unsaved');
        }

        $callback_url = $this->getCallbackUrl($this->meta['callback_secret']);
        $api = $this->getApi($this->meta['api_key']);

        $wallets = $api->getWallets();
        if ($wallets['code'] == 401) {
            return $error('api_key');
        }
        if ($wallets['code'] != 200 || !isset($wallets['body']->data) || !is_array($wallets['body']->data)) {
            return $error('api', $wallets);
        }
        if (!$this->hasBtcWallet($wallets['body']->data)) {
            return $setup('no_wallet');
        }

        $stores = $api->getStores();
        if ($stores['code'] != 200 || !isset($stores['body']->data) || !is_array($stores['body']->data)) {
            return $error('api', $stores);
        }
        $matches = array_values(array_filter($stores['body']->data, function ($store) use ($callback_url) {
            return ($store->http_callback ?? null) === $callback_url;
        }));
        if (empty($matches)) {
            return $setup('no_store');
        }
        if (count($matches) > 1) {
            return $setup('duplicate_store');
        }
        if (!$this->hasBtcWallet($matches[0]->wallets ?? [])) {
            return $setup('no_store_wallet');
        }

        $response = $api->getNewAddress($callback_url);
        if ($response['code'] != 200 || empty($response['body']->address)) {
            return $error('address', $response);
        }

        return ['status' => 'ok'];
    }

    /**
     * Whether a list of Blockonomics wallets includes a BTC wallet
     *
     * @param array $wallets The wallets
     * @return bool
     */
    private function hasBtcWallet($wallets)
    {
        foreach ((array) $wallets as $wallet) {
            if (strtoupper($wallet->crypto ?? '') === 'BTC') {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts a human readable error message from an API response
     *
     * @param array $response The API response
     * @return string The error message
     */
    private function apiErrorMessage(array $response)
    {
        if (!empty($response['error'])) {
            return $response['error'];
        }

        $body = $response['body'];
        if (isset($body->error->message)) {
            return (string) $body->error->message;
        }
        if (isset($body->error) && is_string($body->error)) {
            return $body->error;
        }
        if (isset($body->message)) {
            return (string) $body->message;
        }

        return 'HTTP ' . $response['code'];
    }

    /**
     * Builds the callback URL to configure on the Blockonomics store
     *
     * @param string $secret The callback secret
     * @return string The callback URL
     */
    private function getCallbackUrl($secret)
    {
        return $this->getGatewayUrl(['secret' => $secret]);
    }

    /**
     * Builds a URL to this gateway's callback endpoint
     *
     * @param array $params Query string parameters
     * @return string The URL
     */
    private function getGatewayUrl(array $params = [])
    {
        return Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id') . '/blockonomics/'
            . (empty($params) ? '' : '?' . http_build_query($params));
    }

    /**
     * Ends the request unless a staff member of this company is logged in
     */
    private function requireStaff()
    {
        Loader::loadComponents($this, ['Session']);

        if (!$this->Session->read('blesta_staff_id')
            || $this->Session->read('blesta_company_id') != Configure::get('Blesta.company_id')
        ) {
            $this->respondJson(['status' => 'error', 'message' => Language::_('Blockonomics.!error.test_setup.forbidden', true)], 403);
        }
    }

    /**
     * Sends a JSON response and ends the request
     *
     * @param array $data The data to encode
     * @param int $status The HTTP status code
     */
    private function respondJson(array $data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Loads the API
     *
     * @param string $api_key The API key to authenticate
     * @return BlockonomicsApi An instance of the Blockonomics API
     */
    private function getApi($api_key)
    {
        return new BlockonomicsApi($api_key);
    }

    /**
     * Loads the orders store
     *
     * @return BlockonomicsOrders
     */
    private function getOrders()
    {
        if (!isset($this->orders)) {
            $this->orders = new BlockonomicsOrders();
        }

        return $this->orders;
    }
}
