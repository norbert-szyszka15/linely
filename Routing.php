<?php
declare(strict_types=1);

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/src/View.php';
require_once __DIR__ . '/src/TreeLayout.php';
require_once __DIR__ . '/src/repositories/UsersRepository.php';
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
        $action = $_POST['action'] ?? $_GET['action'] ?? null;
        $page = $_GET['page'] ?? null;

        if ($action) {
            $this->dispatchAction($action, $db);
            return;
        }

        $this->dispatchPage($page, $db);
    }

    private function dispatchAction(string $action, PDO $db): void
    {
        match ($action) {
            'login' => (new SecurityController($db))->authenticate(),
            'logout' => (new SecurityController($db))->logout(),
            'create_tree' => (new DashboardController($db))->createTree(),
            'delete_tree' => (new AdminController($db))->deleteTree(),
            'delete_user' => (new AdminController($db))->deleteUser(),
            'save_person' => (new TreeController($db))->savePerson(),
            'add_partner' => (new TreeController($db))->addPartner(),
            'connect_child' => (new TreeController($db))->connectChild(),
            'update_position' => (new TreeController($db))->updatePosition(),
            default => $this->notFound($db),
        };
    }

    private function dispatchPage(?string $page, PDO $db): void
    {
        $page ??= empty($_SESSION['user_id']) ? 'login' : 'dashboard';

        match ($page) {
            'login' => (new SecurityController($db))->login(),
            'dashboard' => (new DashboardController($db))->index(),
            'tree' => (new TreeController($db))->full(),
            'descendants' => (new TreeController($db))->descendants(),
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
