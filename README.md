# MakeCommerce for WHMCS

Payment gateway module for [MakeCommerce](https://makecommerce.net) (Maksekeskus): bank links, cards and other methods enabled in your MakeCommerce shop.

Author: MB Bluejuice — https://bluejuice.pro

## Requirements

- WHMCS 8.x
- PHP 7.4+ with cURL
- MakeCommerce shop (Shop ID, Secret key, Publishable key)

## Install

1. Copy the `modules` folder into the WHMCS root.
2. Go to **System Settings → Payment Gateways**, activate **MakeCommerce**.
3. Enter the Shop ID, Secret key and Publishable key from the MakeCommerce Merchant Portal (Settings → API).
   Payments only need the Shop ID and Secret key.

Return, cancel and notification URLs are sent with every transaction, nothing to configure in the portal.

## Test mode

Tick **Test mode** and use test shop credentials from https://merchant.test.maksekeskus.ee.

## How it works

1. The client clicks **Pay Now** on an invoice or in checkout page.
2. The module creates a transaction for the invoice balance and redirects to the MakeCommerce payment page.
3. MakeCommerce sends the result to `modules/gateways/callback/makecommerce.php`. The message signature is verified, then the payment is added to the invoice.

Refunds from the WHMCS admin area are sent to MakeCommerce.

## Files

```
modules/gateways/makecommerce.php            gateway definition, pay button, refunds
modules/gateways/makecommerce/Api.php        API client
modules/gateways/makecommerce/redirect.php   creates the transaction
modules/gateways/callback/makecommerce.php   return and notification handler
```

## License

MIT
Copyright (c) 2026 MB Bluejuice

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
