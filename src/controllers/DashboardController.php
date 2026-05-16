<?php
declare(strict_types=1);

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $this->requireLogin();
        View::render('dashboard', [
            'trees' => $this->trees->visibleFor($user),
        ], $user, 'Moje drzewa');
    }

    public function createTree(): void
    {
        verify_csrf();
        $user = $this->requireLogin();

        $this->trees->create(
            (int) $user['id'],
            trim((string) $_POST['name']),
            value_or_null('description')
        );

        flash('Utworzono nowe drzewo.');
        redirect('/?page=dashboard');
    }
}
