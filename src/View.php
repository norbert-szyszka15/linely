<?php
declare(strict_types=1);

final class View
{
    public static function render(string $template, array $data = [], ?array $user = null, string $title = 'Linely'): void
    {
        extract($data, EXTR_SKIP);
        $viewPath = dirname(__DIR__) . '/public/views/' . $template . '.php';
        $flash = flash();

        require dirname(__DIR__) . '/public/views/partials/header.php';
        require $viewPath;
        require dirname(__DIR__) . '/public/views/partials/footer.php';
    }
}
