<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateway
    |--------------------------------------------------------------------------
    |
    | Kilat Print settles every transaction through a payment provider. The
    | provider supports QRIS (scanned by any e-wallet or mobile banking app) and
    | bank transfer with an automatically confirmed virtual account, so no
    | customer uploaded proof and no admin verification are required.
    |
    | Supported drivers:
    |   - `midtrans`  Midtrans Snap/Core API (QRIS + virtual account)
    |   - `null`      Refuses to create transactions. Use it to keep the site
    |                 reachable while credentials are still being provisioned.
    |
    */

    'driver' => env('PAYMENT_PROVIDER', env('PAYMENT_DRIVER', 'null')),

    /*
     | The server key is the only credential the Core API driver needs. It is
     | sent in the `Authorization` header of server-to-server requests and must
     | never reach Blade, JavaScript, a log line, or version control.
     */
    'server_key' => env('PAYMENT_SERVER_KEY'),

    /*
     | Client key and merchant id are not required by the Core API charge and
     | notification flow. They are stored so an account can be identified in
     | diagnostics and so a future Snap.js frontend has what it needs, but they
     | are never a substitute for the server key.
     */
    'client_key' => env('PAYMENT_CLIENT_KEY'),

    'merchant_id' => env('PAYMENT_MERCHANT_ID'),

    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),

    /*
     | Sandbox mode routes every API call to the provider test environment. It
     | must be true on any environment that is not a live production account.
     */
    'sandbox' => (bool) env('PAYMENT_SANDBOX', true),

    /*
     | Number of hours a pending transaction stays payable before the local
     | expiry sweep marks it EXPIRED.
     */
    'expiry_hours' => (int) env('PAYMENT_EXPIRY_HOURS', 24),

    /*
     | Bank code used when the customer chooses bank transfer. Midtrans maps this
     | to an automatically confirmed virtual account.
     */
    'bank_transfer_bank' => env('PAYMENT_BANK_TRANSFER_BANK', 'bca'),

    'currency' => env('PAYMENT_CURRENCY', 'IDR'),

    /*
     | Public base URL of this deployment. Midtrans must be able to reach the
     | webhook path from the internet, so `localhost` is not a valid value here.
     */
    'public_url' => env('APP_URL'),

];
