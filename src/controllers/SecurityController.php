<?php
declare(strict_types=1);

final class SecurityController extends BaseController
{
    private const MAX_EMAIL_LENGTH = 255;
    private const MAX_NAME_LENGTH = 150;
    private const MAX_PASSWORD_LENGTH = 128;

    public function login(): void
    {
        View::render('login', [
            'loginError' => $_SESSION['login_error'] ?? null,
            'loginOld' => $_SESSION['login_old'] ?? [],
            'registerError' => $_SESSION['register_error'] ?? null,
            'registerOld' => $_SESSION['register_old'] ?? [],
        ], $this->currentUser(), 'Logowanie');
        unset($_SESSION['login_error'], $_SESSION['login_old'], $_SESSION['register_error'], $_SESSION['register_old']);
    }

    public function authenticate(): void
    {
        verify_csrf('/?page=login');

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $_SESSION['login_old'] = ['email' => $email];

        if ($email === '' || $password === '') {
            $_SESSION['login_error'] = [
                'message' => 'Podaj e-mail i hasło.',
                'fields' => [
                    'email' => $email === '',
                    'password' => $password === '',
                ],
            ];
            redirect('/?page=login');
        }

        if (!$this->hasValidLength($email, self::MAX_EMAIL_LENGTH) || !$this->hasValidLength($password, self::MAX_PASSWORD_LENGTH)) {
            $_SESSION['login_error'] = [
                'message' => 'Niepoprawny e-mail lub hasło.',
                'fields' => ['email' => true, 'password' => true],
            ];
            redirect('/?page=login');
        }

        if (!$this->isValidEmail($email)) {
            $_SESSION['login_error'] = [
                'message' => 'Niepoprawny e-mail lub hasło.',
                'fields' => ['email' => true],
            ];
            redirect('/?page=login');
        }

        $user = $this->users->findByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($_SESSION['login_old']);
            Auth::setCookie(Auth::issue($user));
            flash('Zalogowano pomyślnie.');
            redirect('/?page=dashboard');
        }

        $_SESSION['login_error'] = [
            'message' => 'Niepoprawny e-mail lub hasło.',
            'fields' => ['email' => true, 'password' => true],
        ];
        redirect('/?page=login');
    }

    public function register(): void
    {
        verify_csrf('/?page=login');

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $_SESSION['register_old'] = ['name' => $name, 'email' => $email];

        if ($name === '' || $email === '' || $password === '' || $passwordConfirmation === '') {
            $_SESSION['register_error'] = [
                'message' => 'Wypełnij wszystkie pola rejestracji.',
                'fields' => [
                    'name' => $name === '',
                    'email' => $email === '',
                    'password' => $password === '',
                    'password_confirmation' => $passwordConfirmation === '',
                ],
            ];
            redirect('/?page=login');
        }

        $lengthErrors = $this->inputLengthErrors($name, $email, $password, $passwordConfirmation);
        if ($lengthErrors) {
            $_SESSION['register_error'] = [
                'message' => 'Podane dane są za długie.',
                'fields' => $lengthErrors,
            ];
            redirect('/?page=login');
        }

        if (!$this->isValidEmail($email)) {
            $_SESSION['register_error'] = [
                'message' => 'Podaj poprawny adres e-mail.',
                'fields' => ['email' => true],
            ];
            redirect('/?page=login');
        }

        $passwordErrors = Auth::passwordErrors($password);
        if ($passwordErrors || $password !== $passwordConfirmation) {
            $_SESSION['register_error'] = [
                'message' => $passwordErrors ? Auth::passwordRequirementsText() : 'Hasła muszą być identyczne.',
                'fields' => ['password' => true, 'password_confirmation' => true],
            ];
            redirect('/?page=login');
        }

        if ($this->users->findByEmail($email)) {
            $_SESSION['register_error'] = [
                'message' => 'Nie można utworzyć konta dla podanych danych.',
                'fields' => ['email' => true],
            ];
            redirect('/?page=login');
        }

        $userId = $this->users->create($email, Auth::hashPassword($password), $name);
        $user = $this->users->findById($userId);
        unset($_SESSION['register_old']);

        Auth::setCookie(Auth::issue($user));
        flash('Konto zostało utworzone.');
        redirect('/?page=dashboard');
    }

    public function logout(): void
    {
        verify_csrf('/?page=login');

        Auth::clearCookie();
        session_destroy();
        redirect('/?page=login');
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function inputLengthErrors(string $name, string $email, string $password, string $passwordConfirmation): array
    {
        return array_filter([
            'name' => !$this->hasValidLength($name, self::MAX_NAME_LENGTH),
            'email' => !$this->hasValidLength($email, self::MAX_EMAIL_LENGTH),
            'password' => !$this->hasValidLength($password, self::MAX_PASSWORD_LENGTH),
            'password_confirmation' => !$this->hasValidLength($passwordConfirmation, self::MAX_PASSWORD_LENGTH),
        ]);
    }

    private function hasValidLength(string $value, int $maxLength): bool
    {
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        return $length <= $maxLength;
    }
}
