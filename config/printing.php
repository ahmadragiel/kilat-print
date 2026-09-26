<?php

return [
    'bank_name' => env('BANK_NAME', 'Bank Digital Demo'),
    'bank_account_number' => env('BANK_ACCOUNT_NUMBER', '0000-0000-0000'),
    'bank_account_name' => env('BANK_ACCOUNT_NAME', 'Kilat Print'),
    'delivery_fee' => (int) env('DELIVERY_FEE', 0),
    'design_max_kilobytes' => (int) env('DESIGN_MAX_KILOBYTES', 10240),
    'payment_max_kilobytes' => (int) env('PAYMENT_MAX_KILOBYTES', 5120),
    'custom_design_max_kilobytes' => (int) env('CUSTOM_DESIGN_MAX_KILOBYTES', 5120),
    'custom_design_asset_max_kilobytes' => (int) env('CUSTOM_DESIGN_ASSET_MAX_KILOBYTES', 5120),
    'custom_design_asset_disk' => env('CUSTOM_DESIGN_ASSET_DISK', 'local'),
    'custom_design_assets_per_draft' => (int) env('CUSTOM_DESIGN_ASSETS_PER_DRAFT', 50),
];
