<?php
declare(strict_types=1);

define('HC_ROOT', dirname(__DIR__));
$GLOBALS['hc_config'] = require HC_ROOT . '/config/config.php';

function cfg(string $key, $default = null)
{
    $value = $GLOBALS['hc_config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set((string) cfg('app.timezone', 'UTC'));

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('hostelcare');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
} elseif (PHP_SAPI === 'cli' && !isset($_SESSION)) {
    $_SESSION = [];
}

require HC_ROOT . '/includes/helpers.php';
require HC_ROOT . '/includes/db.php';
require HC_ROOT . '/includes/csrf.php';
require HC_ROOT . '/includes/auth.php';
require HC_ROOT . '/includes/sms.php';
require HC_ROOT . '/includes/mailer.php';
require HC_ROOT . '/includes/hostels.php';
require HC_ROOT . '/includes/forms.php';
require HC_ROOT . '/includes/notifications.php';
require HC_ROOT . '/includes/complaints.php';
require HC_ROOT . '/includes/layout.php';

if (PHP_SAPI !== 'cli' && !defined('HC_SKIP_INSTALL_CHECK') && !db_installed()) {
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        redirect('install.php');
    }
}
