<?php
declare(strict_types=1);

final class Auth
{
    private const COOKIE_NAME = 'linely_auth';
    private const TOKEN_TTL = 604800;

    public static function issue(array $user): string
    {
        $now = time();
        return self::encode([
            'sub' => (int) $user['id'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL,
        ]);
    }

    public static function setCookie(string $token): void
    {
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + self::TOKEN_TTL,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function userIdFromRequest(): ?int
    {
        $payload = self::decode((string) ($_COOKIE[self::COOKIE_NAME] ?? ''));
        if (!$payload || empty($payload['sub'])) {
            return null;
        }

        return (int) $payload['sub'];
    }

    public static function passwordErrors(string $password): array
    {
        $errors = [];
        if (strlen($password) < 12) {
            $errors[] = 'minimum 12 znaków';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'mała litera';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'wielka litera';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'cyfra';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'znak specjalny';
        }

        return $errors;
    }

    public static function hashPassword(string $password): string
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $hash = password_hash($password, $algorithm);
        if (!is_string($hash)) {
            throw new RuntimeException('Nie udało się zabezpieczyć hasła.');
        }

        return $hash;
    }

    public static function passwordRequirementsText(): string
    {
        return 'Hasło musi mieć minimum 12 znaków, małą i wielką literę, cyfrę oraz znak specjalny.';
    }

    private static function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, self::secret(), true);

        return $headerEncoded . '.' . $payloadEncoded . '.' . self::base64UrlEncode($signature);
    }

    private static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, self::secret(), true));
        if (!hash_equals($expected, $signatureEncoded)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadEncoded);
        if ($payloadJson === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    private static function secret(): string
    {
        return getenv('JWT_SECRET') ?: 'linely-local-development-jwt-secret-change-me';
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string|false
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
