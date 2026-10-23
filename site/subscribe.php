<?php
// Collects launch-letter signups into a CSV that lives one level above
// public_html, so it can never be downloaded from the website.
header('Content-Type: application/json');

function reply(bool $ok, string $message, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(false, 'Nothing to see here.', 405);
}

// Bots fill in the hidden "website" field; people never see it.
if (!empty($_POST['website'])) {
    reply(true, "You're on the list.");
}

$email = trim($_POST['email'] ?? '');
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reply(false, "That email doesn't look right. Check it and try again.", 422);
}

$dir = dirname(__DIR__) . '/private';
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    reply(false, 'Something went sideways. Try again in a minute.', 500);
}

$file = $dir . '/subscribers.csv';
$fh = fopen($file, 'c+');
if ($fh === false || !flock($fh, LOCK_EX)) {
    reply(false, 'Something went sideways. Try again in a minute.', 500);
}

$needle = strtolower($email);
while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
    if (isset($row[0]) && strtolower($row[0]) === $needle) {
        flock($fh, LOCK_UN);
        fclose($fh);
        reply(true, "You're already on the list. We'll write when the doors open.");
    }
}

fseek($fh, 0, SEEK_END);
fputcsv($fh, [$email, gmdate('c')], ',', '"', '');
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);

reply(true, "You're on the list. We'll write when the doors open.");
