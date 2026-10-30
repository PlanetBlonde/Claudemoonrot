<?php
// Remaining stock per item, so the page can mark sold-out stones.
require __DIR__ . '/lib/shop.php';
header('Cache-Control: no-store');
json_reply(200, ['open' => stripe_config() !== null, 'stock' => read_stock()]);
