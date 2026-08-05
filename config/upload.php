<?php

declare(strict_types=1);

return [
    'room_images' => [
        'allowed_mime_extensions' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ],
        'maximum_file_size_bytes' => 5 * 1024 * 1024,
        'minimum_width_pixels' => 640,
        'minimum_height_pixels' => 480,
        'maximum_width_pixels' => 6000,
        'maximum_height_pixels' => 6000,
        'maximum_images_per_room' => 10,
        'filesystem_root' => 'public/uploads/rooms',
        'database_path_prefix' => 'uploads/rooms/',
    ],
    'payment_proofs' => [
        'allowed_mime_extensions' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'application/pdf' => ['pdf'],
        ],
        'maximum_file_size_bytes' => 5_242_880,
        'filesystem_root' => 'storage/private/payment-proofs',
        'database_path_prefix' => 'payment-proofs/',
    ],
    'gallery_images' => [
        'allowed_formats_environment_key' => 'GALLERY_IMAGE_FORMATS',
        'maximum_file_size_environment_key' => 'GALLERY_IMAGE_MAX_BYTES',
        'minimum_width_environment_key' => 'GALLERY_IMAGE_MIN_WIDTH',
        'minimum_height_environment_key' => 'GALLERY_IMAGE_MIN_HEIGHT',
        'maximum_width_environment_key' => 'GALLERY_IMAGE_MAX_WIDTH',
        'maximum_height_environment_key' => 'GALLERY_IMAGE_MAX_HEIGHT',
        'require_decodability_environment_key' => 'GALLERY_IMAGE_REQUIRE_DECODABILITY',
        'filesystem_root' => 'storage/private/gallery',
        'database_path_prefix' => 'gallery/',
    ],
    'content_media' => [
        'allowed_mime_extensions' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ],
        'maximum_file_size_bytes' => 5 * 1024 * 1024,
        'minimum_width_pixels' => 320,
        'minimum_height_pixels' => 180,
        'maximum_width_pixels' => 6000,
        'maximum_height_pixels' => 6000,
        'require_decodability' => true,
        'filesystem_root' => 'public/uploads/content',
        'database_path_prefix' => 'uploads/content/',
    ],
];
