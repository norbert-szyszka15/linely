<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}

function abort_http(int $statusCode, string $message, ?array $user = null): never
{
    http_response_code($statusCode);
    View::render('error', ['message' => $message], $user, 'Błąd');
    exit;
}

function value_or_null(string $key): ?string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return $value === '' ? null : $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $submittedToken = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
    $sessionToken = is_string($_SESSION['csrf'] ?? null) ? $_SESSION['csrf'] : '';

    if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        abort_http(400, 'Sesja formularza wygasła. Spróbuj ponownie.');
    }
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function person_name(array $person): string
{
    return trim($person['first_name'] . ' ' . ($person['last_name'] ?? ''));
}

function person_years(array $person): string
{
    $birth = $person['birth_date'] ? substr($person['birth_date'], 0, 4) : '?';
    $death = $person['is_living'] ? '' : (' - ' . ($person['death_date'] ? substr($person['death_date'], 0, 4) : '?'));
    return $birth . $death;
}

function person_initial(array $person): string
{
    return substr((string) $person['first_name'], 0, 1);
}
