<?php
// PayPal webhook receiver. Register this address in the PayPal developer dashboard (your app > Webhooks) for the
// events INVOICING.INVOICE.PAID, INVOICING.INVOICE.CANCELLED, INVOICING.INVOICE.REFUNDED and INVOICING.INVOICE.UPDATED.
// It has no login and no CSRF token on purpose: PayPal calls it, not a browser. Trust comes from the signature check
// and from re-reading the invoice from PayPal (see includes/paypal.php).
require __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'message' => 'POST only']);
    exit;
}

$raw = (string) file_get_contents('php://input');
if (strlen($raw) > 200000) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'message' => 'Too large']);
    exit;
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_PAYPAL_')) {
        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
    }
}

try {
    [$status, $message] = paypal_handle_webhook($headers, $raw);
} catch (Throwable $e) {
    error_log('PayPal webhook failed: ' . $e->getMessage());
    [$status, $message] = [500, 'Server error'];
}
http_response_code($status);
echo json_encode(['ok' => $status < 300, 'message' => $message]);
