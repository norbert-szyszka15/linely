<?php
declare(strict_types=1);

final class TreesRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function visibleFor(array $user): array
    {
        if ($user['role'] === 'admin') {
            $stmt = $this->db->prepare(
                'SELECT ft.*, u.name AS owner_name
                 FROM family_trees ft
                 JOIN users u ON u.id = ft.user_id
                 WHERE u.deleted_at IS NULL
                 ORDER BY ft.updated_at DESC'
            );
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT ft.*, u.name AS owner_name
             FROM family_trees ft
             JOIN users u ON u.id = ft.user_id
             WHERE ft.user_id = :user_id
             ORDER BY ft.updated_at DESC'
        );
        $stmt->execute(['user_id' => $user['id']]);
        return $stmt->fetchAll();
    }

    public function findVisible(int $treeId, array $user): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ft.*, u.name AS owner_name
             FROM family_trees ft
             JOIN users u ON u.id = ft.user_id
             WHERE ft.id = :id AND u.deleted_at IS NULL'
        );
        $stmt->execute(['id' => $treeId]);
        $tree = $stmt->fetch() ?: null;

        if (!$tree || ($user['role'] !== 'admin' && (int) $tree['user_id'] !== (int) $user['id'])) {
            return null;
        }

        return $tree;
    }

    public function create(int $userId, string $name, ?string $description): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO family_trees (user_id, name, description)
             VALUES (:user_id, :name, :description)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
        ]);
    }

    public function delete(int $treeId): void
    {
        $stmt = $this->db->prepare('DELETE FROM family_trees WHERE id = :id');
        $stmt->execute(['id' => $treeId]);
    }

    public function setRootIfEmpty(int $treeId, int $personId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE family_trees
             SET root_person_id = :person_id
             WHERE id = :tree_id AND root_person_id IS NULL'
        );
        $stmt->execute(['person_id' => $personId, 'tree_id' => $treeId]);
    }
}
