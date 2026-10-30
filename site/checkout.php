<?php
// Turns the shopping bag into a Stripe Checkout page and returns its URL.
// Prices come from catalog.json on the server, never from the browser.
require __DIR__ . '/lib/shop.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_reply(405, ['ok' => false]);

$cfg = stripe_config();
if (!$cfg) json_reply(503, ['ok' => false, 'message' => "Checkout opens soon. Leave your email at the bottom of the page and we'll tell you the minute it does."]);

$bag = json_decode(file_get_contents('php://input'), true)['items'] ?? [];
if (!is_array($bag) || !$bag || count($bag) > 30) json_reply(422, ['ok' => false, 'message' => 'Your bag is empty.']);

$catalog = catalog();
$stock = read_stock();
$params = [
    'mode' => 'payment',
    'success_url' => SITE_URL . '/thanks.html',
    'cancel_url' => SITE_URL . '/#shop',
    'shipping_address_collection[allowed_countries][0]' => 'US',
    'shipping_options[0][shipping_rate_data][display_name]' => 'Flat US shipping',
    'shipping_options[0][shipping_rate_data][type]' => 'fixed_amount',
    'shipping_options[0][shipping_rate_data][fixed_amount][amount]' => SHIPPING_CENTS,
    'shipping_options[0][shipping_rate_data][fixed_amount][currency]' => 'usd',
];
$cart = [];
foreach (array_values($bag) as $n => $line) {
    $id = (string)($line['id'] ?? '');
    $qty = (int)($line['qty'] ?? 0);
    if (!isset($catalog[$id]) || $qty < 1) json_reply(422, ['ok' => false, 'message' => 'Something in your bag is no longer listed. Refresh and try again.']);
    if ($qty > $stock[$id]) {
        $left = $stock[$id];
        json_reply(409, ['ok' => false, 'message' => $left
            ? "Only {$left} {$catalog[$id]['name']} left. Adjust your bag and try again."
            : "The {$catalog[$id]['name']} just sold. Remove it and try again."]);
    }
    $item = $catalog[$id];
    $p = "line_items[$n]";
    $params["{$p}[quantity]"] = $qty;
    $params["{$p}[price_data][currency]"] = 'usd';
    $params["{$p}[price_data][unit_amount]"] = (int)round($item['price'] * 100);
    $params["{$p}[price_data][product_data][name]"] = $item['name'];
    if ($item['image']) $params["{$p}[price_data][product_data][images][0]"] = SITE_URL . '/img/' . $item['image'];
    $cart[] = "$id:$qty";
}
// The webhook reads this back to count stock down after payment.
$params['metadata[cart]'] = implode(',', $cart);

[$code, $session] = stripe_post($cfg['secret'], 'checkout/sessions', $params);
if ($code !== 200 || empty($session['url'])) {
    error_log('Stripe checkout failed: ' . $code . ' ' . ($session['error']['message'] ?? ''));
    json_reply(502, ['ok' => false, 'message' => 'Checkout hiccuped. Try again in a minute.']);
}
json_reply(200, ['ok' => true, 'url' => $session['url']]);
