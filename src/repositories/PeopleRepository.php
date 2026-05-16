<?php
declare(strict_types=1);

final class PeopleRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function forTree(int $treeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM persons
             WHERE tree_id = :tree_id
             ORDER BY last_name NULLS LAST, first_name'
        );
        $stmt->execute(['tree_id' => $treeId]);
        return $stmt->fetchAll();
    }

    public function find(int $personId, int $treeId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM persons WHERE id = :id AND tree_id = :tree_id');
        $stmt->execute(['id' => $personId, 'tree_id' => $treeId]);
        return $stmt->fetch() ?: null;
    }

    public function partnerships(int $treeId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM partnerships WHERE tree_id = :tree_id');
        $stmt->execute(['tree_id' => $treeId]);
        return $stmt->fetchAll();
    }

    public function parentLinks(int $treeId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM parent_child WHERE tree_id = :tree_id');
        $stmt->execute(['tree_id' => $treeId]);
        return $stmt->fetchAll();
    }

    public function save(array $data, ?int $personId = null): int
    {
        if ($personId) {
            $data['id'] = $personId;
            $this->db->prepare(
                'UPDATE persons
                 SET first_name = :first_name, last_name = :last_name, maiden_name = :maiden_name,
                     gender = :gender, birth_date = :birth_date, birth_place = :birth_place,
                     death_date = :death_date, death_place = :death_place, is_living = :is_living,
                     occupation = :occupation, notes = :notes, avatar_color = :avatar_color
                 WHERE id = :id AND tree_id = :tree_id'
            )->execute($data);
            return $personId;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO persons (
                 tree_id, first_name, last_name, maiden_name, gender, birth_date, birth_place,
                 death_date, death_place, is_living, occupation, notes, avatar_color, x_position, y_position
             )
             VALUES (
                 :tree_id, :first_name, :last_name, :maiden_name, :gender, :birth_date, :birth_place,
                 :death_date, :death_place, :is_living, :occupation, :notes, :avatar_color, :x_position, :y_position
             )
             RETURNING id'
        );
        $stmt->execute($data + ['x_position' => 2604, 'y_position' => 1680]);
        return (int) $stmt->fetchColumn();
    }

    public function addParentChild(int $treeId, int $parentId, int $childId, string $relationType = 'biological'): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO parent_child (tree_id, parent_id, child_id, relation_type)
             VALUES (:tree_id, :parent_id, :child_id, :relation_type)
             ON CONFLICT (parent_id, child_id) DO NOTHING'
        );
        $stmt->execute([
            'tree_id' => $treeId,
            'parent_id' => $parentId,
            'child_id' => $childId,
            'relation_type' => $relationType,
        ]);
    }

    public function addPartnership(array $data): void
    {
        $this->db->prepare(
            'INSERT INTO partnerships (tree_id, person1_id, person2_id, status, start_date, end_date, notes)
             VALUES (:tree_id, :person1_id, :person2_id, :status, :start_date, :end_date, :notes)'
        )->execute($data);
    }

    public function updatePosition(int $treeId, int $personId, int $x, int $y): void
    {
        $stmt = $this->db->prepare(
            'UPDATE persons
             SET x_position = :x, y_position = :y
             WHERE id = :person_id AND tree_id = :tree_id'
        );
        $stmt->execute([
            'x' => $x,
            'y' => $y,
            'person_id' => $personId,
            'tree_id' => $treeId,
        ]);
    }

}
