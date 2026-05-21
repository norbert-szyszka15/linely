<?php
declare(strict_types=1);

final class UsersRepository
{
    private static ?self $instance = null;
    private const SAFE_COLUMNS = 'id, email, name, role, created_at, updated_at, deleted_at';

    private function __construct(private PDO $db)
    {
    }

    public static function instance(PDO $db): self
    {
        if (!self::$instance) {
            self::$instance = new self($db);
        }

        return self::$instance;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::SAFE_COLUMNS . '
             FROM users
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::SAFE_COLUMNS . '
             FROM users
             WHERE email = :email AND deleted_at IS NULL'
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findCredentialsByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, name, role, password_hash
             FROM users
             WHERE email = :email AND deleted_at IS NULL'
        );
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $email, string $passwordHash, string $name, string $role = 'user'): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password_hash, name, role)
             VALUES (:email, :password_hash, :name, :role)
             RETURNING id'
        );
        $stmt->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'name' => $name,
            'role' => $role,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function allWithTreeCount(): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.email, u.name, u.role, u.created_at, u.updated_at, u.deleted_at,
                    (SELECT COUNT(*) FROM family_trees ft WHERE ft.user_id = u.id) AS trees_count
             FROM users u
             ORDER BY u.deleted_at NULLS FIRST, u.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
