<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $configPath = __DIR__ . '/config.php';
            $config = file_exists($configPath) ? require $configPath : [];

            $host = $config['db_host'] ?? 'db';
            $port = $config['db_port'] ?? '5432';
            $name = $config['db_name'] ?? 'db';
            $user = $config['db_user'] ?? 'docker';
            $password = $config['db_password'] ?? 'docker';

            self::$pdo = new PDO("pgsql:host={$host};port={$port};dbname={$name}", $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$pdo;
    }
}
