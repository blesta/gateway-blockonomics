<?php
/**
 * Blockonomics orders
 *
 * Persists one row per payment attempt: the address issued for it, the quoted amount,
 * and the payment progress reported by Blockonomics callbacks.
 *
 * @package blesta
 * @subpackage blesta.components.gateways.nonmerchant.blockonomics
 * @copyright Copyright (c) 2024, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class BlockonomicsOrders
{
    /**
     * The table name
     */
    const TABLE = 'blockonomics_orders';

    /**
     * @var Record The record component
     */
    public $Record;

    /**
     * Initializes the record component
     */
    public function __construct()
    {
        Loader::loadComponents($this, ['Record']);
    }

    /**
     * Creates the orders table if it does not exist
     */
    public function createTable()
    {
        $this->Record
            ->setField('id', ['type' => 'int', 'size' => 10, 'unsigned' => true, 'auto_increment' => true])
            ->setField('gateway_id', ['type' => 'int', 'size' => 10, 'unsigned' => true])
            ->setField('client_id', ['type' => 'int', 'size' => 10, 'unsigned' => true])
            ->setField('payment_key', ['type' => 'char', 'size' => 40])
            ->setField('currency', ['type' => 'varchar', 'size' => 3])
            ->setField('amount', ['type' => 'decimal', 'size' => '19,4'])
            ->setField('invoices', ['type' => 'text'])
            ->setField('address', ['type' => 'varchar', 'size' => 255])
            ->setField('txid', ['type' => 'varchar', 'size' => 255, 'is_null' => true, 'default' => null])
            ->setField('expected_units', ['type' => 'bigint', 'size' => 20, 'unsigned' => true, 'default' => 0])
            ->setField('received_units', ['type' => 'bigint', 'size' => 20, 'unsigned' => true, 'default' => 0])
            ->setField('status', ['type' => 'tinyint', 'size' => 2, 'is_null' => true, 'default' => null])
            ->setField('date_added', ['type' => 'datetime'])
            ->setField('date_updated', ['type' => 'datetime'])
            ->setKey(['id'], 'primary')
            ->setKey(['payment_key'], 'index')
            ->setKey(['address'], 'index')
            ->create(self::TABLE, true);
    }

    /**
     * Drops the orders table
     */
    public function dropTable()
    {
        $this->Record->drop(self::TABLE, true);
    }

    /**
     * Deletes all orders that belong to the given gateway instance
     *
     * @param int $gateway_id The gateway ID
     */
    public function deleteByGateway($gateway_id)
    {
        $this->Record->from(self::TABLE)->where('gateway_id', '=', $gateway_id)->delete();
    }

    /**
     * Adds an order
     *
     * @param array $vars The order fields
     * @return int The new order ID
     */
    public function add(array $vars)
    {
        $vars['date_added'] = $vars['date_updated'] = date('Y-m-d H:i:s');
        $this->Record->insert(self::TABLE, $vars);

        return $this->Record->lastInsertId();
    }

    /**
     * Updates an order
     *
     * @param int $id The order ID
     * @param array $vars The fields to update
     */
    public function edit($id, array $vars)
    {
        $vars['date_updated'] = date('Y-m-d H:i:s');
        $this->Record->where('id', '=', $id)->update(self::TABLE, $vars);
    }

    /**
     * Records a payment callback on an order, unless a later status was already recorded
     *
     * @param int $id The order ID
     * @param int $status The callback status
     * @param int $received_units The satoshis received
     * @param string $txid The transaction ID
     */
    public function advance($id, $status, $received_units, $txid)
    {
        $this->Record->where('id', '=', $id)
            ->open()->where('status', '=', null)->orWhere('status', '<=', $status)->close()
            ->update(self::TABLE, [
                'status' => $status,
                'received_units' => $received_units,
                'txid' => $txid,
                'date_updated' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * Fetches an order by ID
     *
     * @param int $id The order ID
     * @return stdClass|false The order, or false if not found
     */
    public function get($id)
    {
        return $this->Record->select()->from(self::TABLE)->where('id', '=', $id)->fetch();
    }

    /**
     * Fetches the latest order for a payment
     *
     * @param string $payment_key The payment key
     * @param bool $paid_only True to only consider orders that have received a payment
     * @return stdClass|false The order, or false if not found
     */
    public function getLatest($payment_key, $paid_only = false)
    {
        $this->Record->select()->from(self::TABLE)->where('payment_key', '=', $payment_key);
        if ($paid_only) {
            $this->Record->where('status', '!=', null);
        }

        return $this->Record->order(['id' => 'DESC'])->limit(1)->fetch();
    }

    /**
     * Fetches the latest order issued for an address
     *
     * @param string $address The payment address
     * @return stdClass|false The order, or false if not found
     */
    public function getByAddress($address)
    {
        return $this->Record->select()->from(self::TABLE)
            ->where('address', '=', $address)
            ->order(['id' => 'DESC'])->limit(1)->fetch();
    }
}
