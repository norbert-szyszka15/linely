<?php
declare(strict_types=1);

final class UsersRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email AND deleted_at IS NULL');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function allWithTreeCount(): array
    {
        return $this->db->query(
            'SELECT u.*,
                    (SELECT COUNT(*) FROM family_trees ft WHERE ft.user_id = u.id) AS trees_count
             FROM users u
             ORDER BY u.deleted_at NULLS FIRST, u.created_at DESC'
        )->fetchAll();
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
