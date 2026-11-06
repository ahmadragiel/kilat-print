<?php

return [
    'bank_name' => env('BANK_NAME', 'Bank Digital Demo'),
    'delivery_fee' => env('DELIVERY_FEE'),
    'delivery_free' => (bool) env('DELIVERY_FREE', false),
    'design_max_kilobytes' => (int) env('DESIGN_MAX_KILOBYTES', 10240),
    'payment_max_kilobytes' => (int) env('PAYMENT_MAX_KILOBYTES', 5120),
    'custom_design_max_kilobytes' => (int) env('CUSTOM_DESIGN_MAX_KILOBYTES', 5120),
    'custom_design_asset_max_kilobytes' => (int) env('CUSTOM_DESIGN_ASSET_MAX_KILOBYTES', 5120),
    'custom_design_asset_disk' => env('CUSTOM_DESIGN_ASSET_DISK', 'local'),
    'custom_design_assets_per_draft' => (int) env('CUSTOM_DESIGN_ASSETS_PER_DRAFT', 50),

    /*
    |--------------------------------------------------------------------------
    | Product assets
    |--------------------------------------------------------------------------
    |
    | Catalog photos and design-editor backgrounds are separate files. The
    | editor background is keyed by catalog_key so products seeded before the
    | split keep using the cleaned asset without a database migration.
    |
    */
    'product_assets' => [
        'kertas-kado-custom' => [
            'catalog' => 'images/products/kertas-kado-custom.jpeg',
            'editor' => 'images/products/editor/kertas-kado-custom-editor.jpeg',
        ],
        'paper-bag-custom' => [
            'catalog' => 'images/products/paper-bag-custom.jpeg',
            'editor' => 'images/products/editor/paper-bag-custom-editor.jpeg',
        ],
        'gelas-mug-custom' => [
            'catalog' => 'images/products/gelas-mug-custom.jpeg',
            'editor' => 'images/products/editor/gelas-mug-custom-editor.jpeg',
        ],
        'topi-ultah-custom' => [
            'catalog' => 'images/products/topi-ultah-custom.jpeg',
            'editor' => 'images/products/editor/topi-ultah-custom-editor.jpeg',
        ],
    ],
];
