<?php
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    'name' => content('site_name', APP_NAME),
    'short_name' => 'پلنر من',
    'start_url' => url('dashboard.php'),
    'display' => 'standalone',
    'dir' => 'rtl',
    'lang' => 'fa',
    'background_color' => '#0f1220',
    'theme_color' => '#7c5cff',
    'icons' => [[
        'src' => 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="192" height="192"><rect width="192" height="192" rx="40" fill="#7c5cff"/><text x="96" y="124" font-size="90" text-anchor="middle" fill="#fff">📅</text></svg>'),
        'sizes' => '192x192', 'type' => 'image/svg+xml',
    ]],
], JSON_UNESCAPED_UNICODE);
