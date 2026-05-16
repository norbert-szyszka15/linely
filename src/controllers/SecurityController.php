<?php
declare(strict_types=1);

final class SecurityController extends BaseController
{
    public function login(): void
    {
        View::render('login', [], $this->currentUser(), 'Logowanie');
    }

    public function authenticate(): void
    {
        $user = $this->users->findByEmail(trim((string) ($_POST['email'] ?? '')));

        if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            flash('Zalogowano pomyślnie.');
            redirect('/?page=dashboard');
        }

        flash('Nieprawidłowy email lub hasło.', 'error');
        redirect('/?page=login');
    }

    public function logout(): void
    {
        session_destroy();
        redirect('/?page=login');
    }
}
