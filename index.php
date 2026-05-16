<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/Routing.php';

try {
    (new Routing())->dispatch();
} catch (Throwable $exception) {
    http_response_code(500);
    $title = 'Błąd';
    $user = null;
    $flash = null;
    $message = $exception->getMessage();

    require __DIR__ . '/public/views/partials/header.php';
    require __DIR__ . '/public/views/error.php';
    require __DIR__ . '/public/views/partials/footer.php';
}
