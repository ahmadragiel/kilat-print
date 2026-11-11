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

    /*
    |--------------------------------------------------------------------------
    | Auto-fit photo templates
    |--------------------------------------------------------------------------
    |
    | Products with a fixed design space and a repeatable photo frame get the
    | one-upload-to-all-frames editor. Slot coordinates live in the template's
    | own pixel space; the browser scales them to the host and exports at 2x.
    |
    */
    'photo_templates' => [
        'kertas-kado-custom' => [
            'key' => 'kertas-kado-custom',
            'version' => 1,
            'width' => 1024,
            'height' => 1024,
            'background' => 'images/products/editor/kertas-kado-custom-editor.jpeg',
            'overlay' => 'images/products/editor/kertas-kado-custom-overlay.png',
            'face_padding' => 0.28,
            'overscan' => 1.4,
            'min_source_dimension' => 800,
            'paper_top' => 211,
            'slots' => [
                ['id' => 'dino-01', 'x' => 239.5, 'y' => 205.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'top'],
                ['id' => 'dino-02', 'x' => 505.5, 'y' => 205.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'top'],
                ['id' => 'dino-03', 'x' => 106.0, 'y' => 292.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-04', 'x' => 372.5, 'y' => 292.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-05', 'x' => 639.0, 'y' => 291.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-06', 'x' => 239.5, 'y' => 374.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-07', 'x' => 506.0, 'y' => 374.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-08', 'x' => 106.5, 'y' => 458.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-09', 'x' => 372.5, 'y' => 462.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-10', 'x' => 638.5, 'y' => 459.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-11', 'x' => 239.5, 'y' => 543.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-12', 'x' => 505.5, 'y' => 543.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-13', 'x' => 106.5, 'y' => 627.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-14', 'x' => 372.5, 'y' => 629.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-15', 'x' => 638.5, 'y' => 629.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-16', 'x' => 239.5, 'y' => 715.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-17', 'x' => 505.5, 'y' => 715.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-18', 'x' => 106.5, 'y' => 801.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-19', 'x' => 372.5, 'y' => 801.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-20', 'x' => 638.5, 'y' => 800.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-21', 'x' => 240.0, 'y' => 879.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-22', 'x' => 505.5, 'y' => 879.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-23', 'x' => 106.5, 'y' => 972.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-24', 'x' => 372.5, 'y' => 972.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-25', 'x' => 639.5, 'y' => 973.5, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'full'],
                ['id' => 'dino-26', 'x' => 240.0, 'y' => 1046.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'bottom'],
                ['id' => 'dino-27', 'x' => 506.5, 'y' => 1047.0, 'width' => 68, 'height' => 68, 'clip' => 'circle', 'rotation' => 0, 'kind' => 'bottom'],
            ],
        ],
    ],
];
