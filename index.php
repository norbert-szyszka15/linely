<?php
declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/Routing.php';

try {
    (new Routing())->dispatch();
} catch (Throwable $exception) {
    http_response_code(500);
    $title = 'Błąd';
    $user = null;
    $flash = null;
    $message = 'Wystąpił błąd aplikacji. Spróbuj ponownie później.';

    require __DIR__ . '/public/views/partials/header.php';
    require __DIR__ . '/public/views/error.php';
    require __DIR__ . '/public/views/partials/footer.php';
}
