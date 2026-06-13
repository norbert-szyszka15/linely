<?php
declare(strict_types=1);

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/src/View.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/TreeLayout.php';
require_once __DIR__ . '/src/repositories/UsersRepository.php';
require_once __DIR__ . '/src/repositories/LoginAttemptsRepository.php';
require_once __DIR__ . '/src/repositories/TreesRepository.php';
require_once __DIR__ . '/src/repositories/PeopleRepository.php';
require_once __DIR__ . '/src/controllers/BaseController.php';
require_once __DIR__ . '/src/controllers/SecurityController.php';
require_once __DIR__ . '/src/controllers/DashboardController.php';
require_once __DIR__ . '/src/controllers/AdminController.php';
require_once __DIR__ . '/src/controllers/TreeController.php';

final class Routing
{
    public function dispatch(): void
    {
        $db = Database::connection();
        $action = $this->postAction();
        $page = $this->queryValue('page');

        if ($action) {
            $this->dispatchAction($action, $db);
            return;
        }

        $this->dispatchPage($page, $db);
    }

    private function postAction(): ?string
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return null;
        }

        return $this->stringValue($_POST['action'] ?? null);
    }

    private function queryValue(string $key): ?string
    {
        return $this->stringValue($_GET[$key] ?? null);
    }

    private function stringValue(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? null : $value;
    }

    private function dispatchAction(string $action, PDO $db): void
    {
        match ($action) {
            'login' => (new SecurityController($db))->authenticate(),
            'register' => (new SecurityController($db))->register(),
            'logout' => (new SecurityController($db))->logout(),
            'create_tree' => (new DashboardController($db))->createTree(),
            'delete_tree' => (new AdminController($db))->deleteTree(),
            'delete_user' => (new AdminController($db))->deleteUser(),
            'save_person' => (new TreeController($db))->savePerson(),
            'add_partner' => (new TreeController($db))->addPartner(),
            'connect_child' => (new TreeController($db))->connectChild(),
            'update_position' => (new TreeController($db))->updatePosition(),
            'delete_person' => (new TreeController($db))->deletePerson(),
            default => $this->notFound($db),
        };
    }

    private function dispatchPage(?string $page, PDO $db): void
    {
        $page ??= Auth::userIdFromRequest() ? 'dashboard' : 'login';

        match ($page) {
            'login' => (new SecurityController($db))->login(),
            'dashboard' => (new DashboardController($db))->index(),
            'tree' => (new TreeController($db))->full(),
            'descendants' => (new TreeController($db))->descendants(),
            'people' => (new TreeController($db))->people(),
            'admin' => (new AdminController($db))->index(),
            default => $this->notFound($db),
        };
    }

    private function notFound(PDO $db): void
    {
        http_response_code(404);
        $controller = new class($db) extends BaseController {
            public function show(): void
            {
                View::render('404', [], $this->currentUser(), 'Nie znaleziono');
            }
        };
        $controller->show();
    }
}
