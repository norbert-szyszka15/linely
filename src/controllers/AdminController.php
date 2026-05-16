<?php
declare(strict_types=1);

final class AdminController extends BaseController
{
    public function index(): void
    {
        $user = $this->requireAdmin();
        View::render('admin', [
            'users' => $this->users->allWithTreeCount(),
            'trees' => $this->trees->visibleFor($user),
        ], $user, 'Panel administratora');
    }

    public function deleteTree(): void
    {
        verify_csrf();
        $user = $this->requireLogin();
        $tree = $this->requireTree((int) $_POST['tree_id'], $user);

        $this->trees->delete((int) $tree['id']);
        flash('Drzewo zostało usunięte.');
        redirect($user['role'] === 'admin' ? '/?page=admin' : '/?page=dashboard');
    }

    public function deleteUser(): void
    {
        verify_csrf();
        $user = $this->requireAdmin();
        $userId = (int) $_POST['user_id'];

        if ($userId === (int) $user['id']) {
            flash('Nie możesz usunąć własnego konta administratora.', 'error');
            redirect('/?page=admin');
        }

        $this->users->delete($userId);
        flash('Użytkownik został usunięty razem ze swoimi drzewami.');
        redirect('/?page=admin');
    }

    private function requireAdmin(): array
    {
        $user = $this->requireLogin();
        if ($user['role'] !== 'admin') {
            flash('Panel administratora jest dostępny tylko dla administratora.', 'error');
            redirect('/?page=dashboard');
        }

        return $user;
    }
}
