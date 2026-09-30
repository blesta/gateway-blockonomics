# Blockonomics for Blesta

Accept crypto payments in Blesta using Blockonomics. When a client pays, the **crypto goes directly into your wallet**. No KYC. Currently supports Bitcoin (BTC).

## Requirements

- Blesta **6.0** or later (PHP 8.1+)
- Blockonomics API Key

## Configure Blockonomics

1. Create a [Blockonomics account](https://www.blockonomics.co/register).
2. Open [Dashboard → Wallets](https://www.blockonomics.co/dashboard#/wallet) and add a BTC wallet: enter a name and xPub. When prompted, paste a sample receiving address from the same wallet.
  Note: Never enter a seed phrase or private key, Blockonomics never asks you to.
3. Open [Dashboard → Stores](https://www.blockonomics.co/dashboard#/store), click Add a store, enter a name, enable BTC, and select the wallet. You can initially leave Callback URL empty.

## Install and connect Blesta

4. Install the gateway with composer:

    ```
    composer require blesta/blockonomics
    ```

    Or upload the source code to `/components/gateways/nonmerchant/blockonomics/` within your Blesta installation.
5. Log in to your Blesta admin account, open Settings → Payment Gateways, find Blockonomics under Non-Merchant and click Install.
6. On the Manage page, copy the Callback URL shown there.
7. Return to [Blockonomics → Stores](https://www.blockonomics.co/dashboard#/store), paste the Callback URL into your Store (added in step 3), and click Update Store.
8. Copy the API Key from the [Blockonomics Stores page](https://www.blockonomics.co/dashboard#/store) and paste it into the API Key field in Blesta.
9. Tick the Accepted Currencies for your store.
10. Hit **Test Setup** to make sure everything is connected. Setup is complete when it shows `BTC ✔`.

### How payments work
The client sees a Bitcoin address, amount and QR code on the payment page. The invoice is marked paid after 2 confirmations. An underpayment is recorded as a declined transaction and the invoice stays unpaid. An overpayment is applied for the invoice total only.

### Uninstall
Uninstalling the gateway drops its `blockonomics_orders` table.

### Optional Integration Testing
Enable Testmode on the Blockonomics Store to simulate checkout and callback processing without making a real payment. [Here](https://help.blockonomics.co/support/solutions/articles/33000287720-how-to-use-testmode-in-blockonomics) is a comprehensive guide about this.


## Support

- Issues and feature requests: [GitHub issues](https://github.com/blesta/gateway-blockonomics/issues)
- Reach out [here](https://help.blockonomics.co/support/home) for human support


## Licensing

This gateway is distributed under the [Blesta License Agreement](http://www.blesta.com/license/).
