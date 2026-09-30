<?php
/**
 * en_us language for the Blockonomics gateway.
 */
// Basics
$lang['Blockonomics.name'] = 'Blockonomics';
$lang['Blockonomics.description'] = 'Accept Bitcoin payments. Each sale is deposited directly into your own wallet. No KYC documentation required.';


// Errors
$lang['Blockonomics.!error.api_key.empty'] = 'Please enter your Blockonomics API Key.';
$lang['Blockonomics.!error.api_key.valid'] = 'The API Key is incorrect.';
$lang['Blockonomics.!error.test_setup.unsaved'] = 'Enter your API Key and click Update Settings before running Test Setup.';
$lang['Blockonomics.!error.test_setup.forbidden'] = 'You must be logged in as a staff member to run Test Setup.';
$lang['Blockonomics.!error.test_setup.api_key'] = 'The API Key is incorrect.';
$lang['Blockonomics.!error.test_setup.api'] = 'Could not reach Blockonomics: %1$s'; // %1$s is the error returned by Blockonomics
$lang['Blockonomics.!error.test_setup.address'] = 'Could not generate a BTC address: %1$s'; // %1$s is the error returned by Blockonomics


// Test Setup
$lang['Blockonomics.test_setup.no_wallet'] = 'Please [add a Bitcoin wallet](https://www.blockonomics.co/dashboard#/wallet) on Blockonomics.';
$lang['Blockonomics.test_setup.no_store'] = 'Please add a [Blockonomics Store](https://www.blockonomics.co/dashboard#/store) with the Callback URL shown above.';
$lang['Blockonomics.test_setup.duplicate_store'] = 'Multiple Blockonomics [stores](https://www.blockonomics.co/dashboard#/store) use this Callback URL. Please remove the duplicate store.';
$lang['Blockonomics.test_setup.no_store_wallet'] = 'Please enable Bitcoin on your Blockonomics [store](https://www.blockonomics.co/dashboard#/store) and select your wallet.';
$lang['Blockonomics.test_setup.no_currency'] = 'Tick at least one Accepted Currency below and click Update Settings, or Blockonomics will not appear at checkout.';


// Settings
$lang['Blockonomics.api_key'] = 'API Key';
$lang['Blockonomics.callback_url'] = 'Callback URL';
$lang['Blockonomics.api_key_note'] = 'Get your API Key <a href="%1$s" target="_blank" rel="noopener">here</a>.'; // %1$s is the Blockonomics stores page URL
$lang['Blockonomics.copy'] = 'Copy';
$lang['Blockonomics.copied'] = 'Copied';
$lang['Blockonomics.test_setup'] = 'Test Setup';
$lang['Blockonomics.test_setup_network'] = 'Could not reach Blesta to run the test. Please try again.';


$lang['Blockonomics.!error.process.unconfigured'] = 'The Blockonomics gateway is not configured yet. Please contact the merchant.';
$lang['Blockonomics.!error.process.session'] = 'Your session has expired. Please reload the page and try again.';
$lang['Blockonomics.!error.process.price'] = 'Could not fetch the Bitcoin price from Blockonomics. Please reload the page to try again.';
$lang['Blockonomics.!error.process.address'] = 'Could not generate a new address (this may be a temporary error, please reload the page to try again). Note to webmaster: run Test Setup in your Blockonomics gateway settings to diagnose.';


// Process (client checkout)
$lang['Blockonomics.process.reload'] = 'Reload';
$lang['Blockonomics.process.bitcoin'] = 'Bitcoin';
$lang['Blockonomics.process.pay_with'] = 'Pay with';
$lang['Blockonomics.process.amount'] = 'Amount';
$lang['Blockonomics.process.address'] = 'Bitcoin address';
$lang['Blockonomics.process.copy_amount'] = 'Copy amount';
$lang['Blockonomics.process.copy_address'] = 'Copy address';
$lang['Blockonomics.process.open_wallet'] = 'Open in wallet';
$lang['Blockonomics.process.confirmations_note'] = 'The invoice is marked paid after %1$s confirmations.'; // %1$s is the number of confirmations
$lang['Blockonomics.process.txid'] = 'Txn ID:';

$lang['Blockonomics.process.bar_awaiting'] = 'Awaiting payment…';
$lang['Blockonomics.process.bar_detected'] = 'Payment detected — confirming…';
$lang['Blockonomics.process.bar_confirming'] = 'Confirming — %1$s of %2$s'; // %1$s is the current number of confirmations, %2$s the required number
$lang['Blockonomics.process.bar_paid'] = '✓ Payment confirmed';
$lang['Blockonomics.process.bar_underpaid'] = 'Payment received — amount was short';

$lang['Blockonomics.process.status_detected'] = 'Payment detected ✔. You can safely close this page, your invoice will be marked paid upon %1$s confirmations.'; // %1$s is the number of confirmations
$lang['Blockonomics.process.status_paid'] = 'Payment confirmed ✔. Your invoice has been paid, taking you to your receipt…';
$lang['Blockonomics.process.status_underpaid'] = 'Your payment confirmed, but it was below the required amount and did not settle this invoice. Please contact us regarding this payment.';
$lang['Blockonomics.process.banner_underpaid'] = 'Your previous Bitcoin payment was below the required amount and did not settle this invoice. Please contact us regarding the earlier payment.';
$lang['Blockonomics.process.status_stopped'] = 'Still pending — please check your invoice later.';
$lang['Blockonomics.process.connection'] = 'Connection issue — retrying…';


// Transaction messages (shown to staff on the transaction)
$lang['Blockonomics.transaction.paid'] = 'Received %1$s BTC'; // %1$s is the BTC amount
$lang['Blockonomics.transaction.overpaid'] = 'Overpayment: received %1$s BTC, expected %2$s BTC. Applied for the amount due only.'; // %1$s received, %2$s expected
$lang['Blockonomics.transaction.underpaid'] = 'Underpayment: expected %1$s BTC, received %2$s BTC. Not applied.'; // %1$s expected, %2$s received
