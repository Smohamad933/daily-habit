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
    'background_color' => '#f4f7f1',
    'theme_color' => '#7652d6',
    'icons' => [[
        'src' => 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="192" height="192"><rect width="192" height="192" rx="40" fill="#7652d6"/><g fill="none" stroke="#fff" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"><rect x="43" y="50" width="106" height="98" rx="16"/><path d="M43 80h106M72 38v28M120 38v28M72 108h1M96 108h1M120 108h1M72 130h1M96 130h1"/></g></svg>'),
        'sizes' => '192x192', 'type' => 'image/svg+xml',
    ]],
], JSON_UNESCAPED_UNICODE);
