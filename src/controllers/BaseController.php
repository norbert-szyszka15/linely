<?php
declare(strict_types=1);

abstract class BaseController
{
    protected UsersRepository $users;
    protected TreesRepository $trees;
    protected PeopleRepository $people;

    public function __construct(protected PDO $db)
    {
        $this->users = new UsersRepository($db);
        $this->trees = new TreesRepository($db);
        $this->people = new PeopleRepository($db);
    }

    protected function currentUser(): ?array
    {
        $userId = Auth::userIdFromRequest();
        if (!$userId) {
            return null;
        }

        return $this->users->findById($userId);
    }

    protected function requireLogin(): array
    {
        $user = $this->currentUser();
        if (!$user) {
            redirect('/?page=login');
        }

        return $user;
    }

    protected function requireTree(int $treeId, array $user): array
    {
        $tree = $this->trees->findVisible($treeId, $user);
        if (!$tree) {
            flash('Nie znaleziono drzewa albo nie masz do niego dostępu.', 'error');
            redirect('/?page=dashboard');
        }

        return $tree;
    }

    protected function personData(array $tree, ?array $base = null): array
    {
        return [
            'tree_id' => $tree['id'],
            'first_name' => trim((string) ($_POST['first_name'] ?? ($base['first_name'] ?? ''))),
            'last_name' => value_or_null('last_name'),
            'maiden_name' => value_or_null('maiden_name'),
            'gender' => $_POST['gender'] ?? 'unknown',
            'birth_date' => value_or_null('birth_date'),
            'birth_place' => value_or_null('birth_place'),
            'death_date' => value_or_null('death_date'),
            'death_place' => value_or_null('death_place'),
            'is_living' => isset($_POST['is_living']) ? 1 : 0,
            'occupation' => value_or_null('occupation'),
            'notes' => value_or_null('notes'),
            'avatar_color' => value_or_null('avatar_color') ?: '#5f8f86',
        ];
    }
}
