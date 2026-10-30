<?php
// Shared helpers for checkout.php, webhook.php and stock.php.
// Secrets and live stock sit in ../private, one level above public_html,
// so neither can ever be downloaded from the website.

const SHIPPING_CENTS = 600;
const SITE_URL = 'https://foxandmoonrot.com';

function private_dir(): string {
    return dirname(__DIR__, 2) . '/private';
}

function json_reply(int $code, array $body): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

// Returns ['secret' => ..., 'webhook' => ...] or null when Stripe isn't set up yet.
function stripe_config(): ?array {
    $file = private_dir() . '/stripe.php';
    if (!is_file($file)) return null;
    $cfg = include $file;
    return (is_array($cfg) && !empty($cfg['secret'])) ? $cfg : null;
}

function catalog(): array {
    return json_decode(file_get_contents(dirname(__DIR__) . '/catalog.json'), true) ?: [];
}

// Live stock lives outside the site so a redeploy never resets sales.
// The first read seeds it from catalog.json; items added later get their catalog count.
function read_stock(): array {
    $saved = [];
    $file = private_dir() . '/stock.json';
    if (is_file($file)) $saved = json_decode(file_get_contents($file), true) ?: [];
    $stock = [];
    foreach (catalog() as $id => $item) {
        $stock[$id] = array_key_exists($id, $saved) ? (int)$saved[$id] : (int)$item['stock'];
    }
    return $stock;
}

// Applies $change(array $stock): array under an exclusive lock.
function update_stock(callable $change): void {
    $dir = private_dir();
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $fh = fopen($dir . '/stock.lock', 'c');
    flock($fh, LOCK_EX);
    $stock = $change(read_stock());
    file_put_contents($dir . '/stock.json', json_encode($stock, JSON_PRETTY_PRINT));
    flock($fh, LOCK_UN);
    fclose($fh);
}

function stripe_post(string $secret, string $path, array $params): array {
    $ch = curl_init('https://api.stripe.com/v1/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_USERPWD => $secret . ':',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode((string)$body, true) ?: []];
}
