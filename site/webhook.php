<?php
// Stripe calls this after a payment. It checks Stripe's signature, counts
// stock down, and emails the order to the shop inbox.
require __DIR__ . '/lib/shop.php';

$cfg = stripe_config();
$payload = file_get_contents('php://input');
$header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
if (!$cfg || empty($cfg['webhook'])) json_reply(503, ['ok' => false]);

// Stripe-Signature: t=<timestamp>,v1=<hmac>[,v1=...]
$t = null; $sigs = [];
foreach (explode(',', $header) as $part) {
    [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
    if ($k === 't') $t = (int)$v;
    if ($k === 'v1') $sigs[] = $v;
}
$expected = hash_hmac('sha256', $t . '.' . $payload, $cfg['webhook']);
$valid = $t && abs(time() - $t) < 300;
$valid = $valid && array_filter($sigs, fn($s) => hash_equals($expected, $s));
if (!$valid) json_reply(400, ['ok' => false]);

$event = json_decode($payload, true);
if (($event['type'] ?? '') !== 'checkout.session.completed') json_reply(200, ['ok' => true]);

$session = $event['data']['object'];
// Stripe retries webhooks; remember handled sessions so stock only drops once.
$seenFile = private_dir() . '/orders-seen.txt';
if (is_file($seenFile) && in_array($session['id'], file($seenFile, FILE_IGNORE_NEW_LINES), true)) {
    json_reply(200, ['ok' => true]);
}

$lines = [];
foreach (array_filter(explode(',', $session['metadata']['cart'] ?? '')) as $pair) {
    [$id, $qty] = explode(':', $pair);
    $lines[$id] = (int)$qty;
}
update_stock(function (array $stock) use ($lines) {
    foreach ($lines as $id => $qty) {
        if (isset($stock[$id])) $stock[$id] = max(0, $stock[$id] - $qty);
    }
    return $stock;
});
file_put_contents($seenFile, $session['id'] . "\n", FILE_APPEND | LOCK_EX);

$catalog = catalog();
$items = '';
foreach ($lines as $id => $qty) $items .= "  {$qty} x " . ($catalog[$id]['name'] ?? $id) . "\n";
$ship = $session['shipping_details'] ?? $session['collected_information']['shipping_details'] ?? [];
$addr = $ship['address'] ?? [];
$to = trim(($ship['name'] ?? '') . "\n" . ($addr['line1'] ?? '') . ' ' . ($addr['line2'] ?? '')
    . "\n" . ($addr['city'] ?? '') . ', ' . ($addr['state'] ?? '') . ' ' . ($addr['postal_code'] ?? ''));
$total = number_format(($session['amount_total'] ?? 0) / 100, 2);
@mail(
    'moon@foxandmoonrot.com',
    "New order: \${$total}",
    "Paid \${$total}.\n\nItems:\n{$items}\nShip to:\n{$to}\n\nCustomer email: " . ($session['customer_details']['email'] ?? '') . "\n",
    "From: Fox & Moonrot <moon@foxandmoonrot.com>\r\nContent-Type: text/plain; charset=utf-8"
);
json_reply(200, ['ok' => true]);
