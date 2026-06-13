<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/TreeLayout.php';

final class TestFailure extends RuntimeException
{
}

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $test): void
    {
        try {
            $test($this);
            $this->passed++;
            echo "PASS {$name}\n";
        } catch (Throwable $exception) {
            $this->failed++;
            echo "FAIL {$name}\n";
            echo "     " . $exception->getMessage() . "\n";
        }
    }

    public function assertTrue(bool $condition, string $message = 'Expected condition to be true.'): void
    {
        if (!$condition) {
            throw new TestFailure($message);
        }
    }

    public function assertFalse(bool $condition, string $message = 'Expected condition to be false.'): void
    {
        if ($condition) {
            throw new TestFailure($message);
        }
    }

    public function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $details = 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.';
            throw new TestFailure($message ? "{$message} {$details}" : $details);
        }
    }

    public function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new TestFailure($message ?: "Expected response to contain {$needle}.");
        }
    }

    public function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            throw new TestFailure($message ?: "Expected response not to contain {$needle}.");
        }
    }

    public function finish(): int
    {
        echo "\n{$this->passed} passed, {$this->failed} failed.\n";
        return $this->failed > 0 ? 1 : 0;
    }
}

final class HttpResponse
{
    public function __construct(
        public int $status,
        public array $headers,
        public string $body
    ) {
    }

    public function header(string $name): ?string
    {
        $name = strtolower($name);
        foreach ($this->headers as $header) {
            if (strtolower(substr($header, 0, strlen($name) + 1)) === $name . ':') {
                return trim(substr($header, strlen($name) + 1));
            }
        }

        return null;
    }
}

final class HttpClient
{
    private array $cookies = [];

    public function __construct(private string $baseUrl)
    {
    }

    public function get(string $path, bool $followRedirect = true): HttpResponse
    {
        return $this->request('GET', $path, [], $followRedirect);
    }

    public function post(string $path, array $data, bool $followRedirect = false): HttpResponse
    {
        return $this->request('POST', $path, $data, $followRedirect);
    }

    private function request(string $method, string $path, array $data = [], bool $followRedirect = true, int $redirects = 0): HttpResponse
    {
        $url = $this->baseUrl . $path;
        $headers = ["User-Agent: LinelyTest/1.0"];
        if ($this->cookies) {
            $cookieHeader = [];
            foreach ($this->cookies as $name => $value) {
                $cookieHeader[] = $name . '=' . $value;
            }
            $headers[] = 'Cookie: ' . implode('; ', $cookieHeader);
        }

        $body = null;
        if ($method === 'POST') {
            $body = http_build_query($data);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Content-Length: ' . strlen($body);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $responseBody = file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new TestFailure("Request failed: {$method} {$url}");
        }

        $responseHeaders = $http_response_header ?? [];
        $status = $this->statusCode($responseHeaders);
        $this->storeCookies($responseHeaders);

        $response = new HttpResponse($status, $responseHeaders, $responseBody);
        if ($followRedirect && in_array($status, [301, 302, 303], true) && $redirects < 5) {
            $location = $response->header('Location');
            if ($location) {
                $path = str_starts_with($location, 'http') ? parse_url($location, PHP_URL_PATH) . '?' . parse_url($location, PHP_URL_QUERY) : $location;
                return $this->get($path, true);
            }
        }

        return $response;
    }

    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function storeCookies(array $headers): void
    {
        foreach ($headers as $header) {
            if (!preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $matches)) {
                continue;
            }

            if ($matches[2] === '') {
                unset($this->cookies[$matches[1]]);
                continue;
            }

            $this->cookies[$matches[1]] = $matches[2];
        }
    }
}

function extract_csrf(string $html): string
{
    if (!preg_match('/name="csrf"\s+value="([^"]+)"/', $html, $matches)) {
        throw new TestFailure('CSRF token was not found.');
    }

    return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
}

function e2e_base_url(): string
{
    $configured = getenv('LINELY_E2E_BASE_URL');
    if (is_string($configured) && $configured !== '') {
        return rtrim($configured, '/');
    }

    return file_exists('/.dockerenv') ? 'https://server' : 'https://localhost:8443';
}

function db(): PDO
{
    return Database::connection();
}

function prepare_e2e_data(): void
{
    $db = db();
    $db->prepare("DELETE FROM family_trees WHERE name LIKE 'E2E_%'")->execute();
    $db->prepare("DELETE FROM users WHERE email LIKE 'e2e_%@example.com'")->execute();
    $db->prepare("DELETE FROM persons WHERE tree_id = 1 AND first_name LIKE 'E2E_%'")->execute();
    $db->exec('DELETE FROM login_attempts');
    $db->exec('DELETE FROM login_audit');
    $db->prepare('UPDATE users SET password_hash = :hash WHERE email = :email')->execute([
        'email' => 'user@example.com',
        'hash' => Auth::hashPassword('User123456!'),
    ]);
    $db->prepare('UPDATE users SET password_hash = :hash WHERE email = :email')->execute([
        'email' => 'admin@example.com',
        'hash' => Auth::hashPassword('Admin123456!'),
    ]);
}

function create_e2e_user(string $email, string $name, string $role = 'user', string $password = 'User123456!'): int
{
    $stmt = db()->prepare(
        'INSERT INTO users (email, password_hash, name, role)
         VALUES (:email, :password_hash, :name, :role)
         RETURNING id'
    );
    $stmt->execute([
        'email' => $email,
        'password_hash' => Auth::hashPassword($password),
        'name' => $name,
        'role' => $role,
    ]);

    return (int) $stmt->fetchColumn();
}

function create_e2e_tree(int $userId, string $name): int
{
    $stmt = db()->prepare(
        'INSERT INTO family_trees (user_id, name, description)
         VALUES (:user_id, :name, :description)
         RETURNING id'
    );
    $stmt->execute([
        'user_id' => $userId,
        'name' => $name,
        'description' => 'Created by e2e test.',
    ]);

    return (int) $stmt->fetchColumn();
}

function create_e2e_person(int $treeId, string $firstName, string $lastName = 'Tester', int $x = 210, int $y = 252): int
{
    $stmt = db()->prepare(
        'INSERT INTO persons (
             tree_id, first_name, last_name, gender, birth_date, is_living,
             occupation, avatar_color, x_position, y_position
         )
         VALUES (
             :tree_id, :first_name, :last_name, :gender, :birth_date, TRUE,
             :occupation, :avatar_color, :x_position, :y_position
         )
         RETURNING id'
    );
    $stmt->execute([
        'tree_id' => $treeId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'gender' => 'unknown',
        'birth_date' => '1990-01-01',
        'occupation' => 'tester',
        'avatar_color' => '#5f8f86',
        'x_position' => $x,
        'y_position' => $y,
    ]);

    return (int) $stmt->fetchColumn();
}

function person(array $data): array
{
    return [
        'id' => $data['id'],
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'] ?? '',
        'birth_date' => $data['birth_date'] ?? null,
        'death_date' => $data['death_date'] ?? null,
        'is_living' => $data['is_living'] ?? true,
        'x_position' => $data['x_position'] ?? null,
        'y_position' => $data['y_position'] ?? null,
    ];
}

function run_unit_tests(TestRunner $runner): void
{
    $runner->test('password policy reports missing requirements', function (TestRunner $t): void {
        $errors = Auth::passwordErrors('short');
        $t->assertTrue(in_array('minimum 12 znaków', $errors, true));
        $t->assertTrue(in_array('wielka litera', $errors, true));
        $t->assertTrue(in_array('cyfra', $errors, true));
        $t->assertTrue(in_array('znak specjalny', $errors, true));
        $t->assertSame([], Auth::passwordErrors('ValidPass123!'));
    });

    $runner->test('auth token resolves user id from cookie', function (TestRunner $t): void {
        $_COOKIE['linely_auth'] = Auth::issue(['id' => 42]);
        $t->assertSame(42, Auth::userIdFromRequest());
        unset($_COOKIE['linely_auth']);
    });

    $runner->test('helper escapes html and formats person data', function (TestRunner $t): void {
        $person = ['first_name' => 'Jan', 'last_name' => 'Kowalski', 'birth_date' => '1970-01-01', 'death_date' => null, 'is_living' => true];
        $t->assertSame('Jan Kowalski', person_name($person));
        $t->assertSame('1970', person_years($person));
        $t->assertSame('&lt;script&gt;', h('<script>'));
    });

    $runner->test('full layout reuses saved positions', function (TestRunner $t): void {
        [$positions, $width, $height] = TreeLayout::full([
            person(['id' => 1, 'first_name' => 'A', 'x_position' => 84, 'y_position' => 126]),
            person(['id' => 2, 'first_name' => 'B', 'x_position' => 420, 'y_position' => 252]),
        ], [], []);

        $t->assertSame(['x' => 84, 'y' => 126], $positions[1]);
        $t->assertSame(['x' => 420, 'y' => 252], $positions[2]);
        $t->assertTrue($width >= 1200);
        $t->assertTrue($height >= 760);
    });

    $runner->test('lineage layout keeps ancestors, descendants and required partners only', function (TestRunner $t): void {
        $people = [
            person(['id' => 1, 'first_name' => 'Grandpa']),
            person(['id' => 2, 'first_name' => 'Grandma']),
            person(['id' => 3, 'first_name' => 'Parent']),
            person(['id' => 4, 'first_name' => 'Root']),
            person(['id' => 5, 'first_name' => 'Child']),
            person(['id' => 6, 'first_name' => 'Partner']),
            person(['id' => 7, 'first_name' => 'Sibling']),
        ];
        $links = [
            ['parent_id' => 1, 'child_id' => 3],
            ['parent_id' => 2, 'child_id' => 3],
            ['parent_id' => 3, 'child_id' => 4],
            ['parent_id' => 3, 'child_id' => 7],
            ['parent_id' => 4, 'child_id' => 5],
            ['parent_id' => 6, 'child_id' => 5],
        ];
        $partnerships = [['person1_id' => 4, 'person2_id' => 6, 'status' => 'current']];

        [, , , $visibleIds] = TreeLayout::lineage($people, $links, $partnerships, 4);
        sort($visibleIds);

        $t->assertSame([1, 2, 3, 4, 5, 6], $visibleIds);
        $t->assertFalse(in_array(7, $visibleIds, true), 'Sibling should not be visible in a direct lineage view.');
    });
}

function run_e2e_tests(TestRunner $runner): void
{
    prepare_e2e_data();

    $runner->test('registration validates input, creates account and logout clears session', function (TestRunner $t): void {
        $client = new HttpClient(e2e_base_url());
        $login = $client->get('/?page=login');

        $invalid = $client->post('/?page=login', [
            'csrf' => extract_csrf($login->body),
            'action' => 'register',
            'name' => 'E2E Invalid',
            'email' => 'e2e_invalid@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);
        $t->assertSame(302, $invalid->status);
        $afterInvalid = $client->get('/?page=login');
        $t->assertContains('Hasło musi mieć minimum 12 znaków', $afterInvalid->body);

        $email = 'e2e_register_' . bin2hex(random_bytes(3)) . '@example.com';
        $valid = $client->post('/?page=login', [
            'csrf' => extract_csrf($afterInvalid->body),
            'action' => 'register',
            'name' => 'E2E Register',
            'email' => $email,
            'password' => 'Register123!',
            'password_confirmation' => 'Register123!',
        ]);
        $t->assertSame(302, $valid->status);

        $dashboard = $client->get('/?page=dashboard');
        $t->assertContains('Zalogowano jako E2E Register.', $dashboard->body);

        $logout = $client->post('/', [
            'csrf' => extract_csrf($dashboard->body),
            'action' => 'logout',
        ]);
        $t->assertSame(302, $logout->status);
        $afterLogout = $client->get('/?page=dashboard');
        $t->assertSame(401, $afterLogout->status);

        db()->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
    });

    $runner->test('login throttling locks repeated invalid attempts', function (TestRunner $t): void {
        db()->exec('DELETE FROM login_attempts');
        $email = 'e2e_lock_' . bin2hex(random_bytes(3)) . '@example.com';
        create_e2e_user($email, 'E2E Lock Target');
        $client = new HttpClient(e2e_base_url());

        try {
            for ($i = 0; $i < 5; $i++) {
                $login = $client->get('/?page=login');
                $response = $client->post('/?page=login', [
                    'csrf' => extract_csrf($login->body),
                    'action' => 'login',
                    'email' => $email,
                    'password' => 'WrongPassword123!',
                ]);
                $t->assertSame(302, $response->status);
            }

            $lockedLogin = $client->get('/?page=login');
            $locked = $client->post('/?page=login', [
                'csrf' => extract_csrf($lockedLogin->body),
                'action' => 'login',
                'email' => $email,
                'password' => 'WrongPassword123!',
            ]);
            $t->assertSame(302, $locked->status);

            $attempt = db()->query('SELECT attempts, locked_until FROM login_attempts ORDER BY last_attempt_at DESC LIMIT 1')->fetch();
            $t->assertTrue((int) $attempt['attempts'] >= 5, 'Failed login attempts should be counted.');
            $t->assertTrue(!empty($attempt['locked_until']), 'Repeated invalid logins should set locked_until.');
            $t->assertTrue(strtotime((string) $attempt['locked_until']) > time(), 'Lock should be active.');
        } finally {
            db()->exec('DELETE FROM login_attempts');
            db()->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
    });

    $runner->test('user can log in and open dashboard', function (TestRunner $t): void {
        $client = new HttpClient(e2e_base_url());
        $login = $client->get('/?page=login');
        $t->assertSame(200, $login->status);

        $response = $client->post('/?page=login', [
            'csrf' => extract_csrf($login->body),
            'action' => 'login',
            'email' => 'user@example.com',
            'password' => 'User123456!',
        ]);

        $t->assertSame(302, $response->status);
        $dashboard = $client->get('/?page=dashboard');
        $t->assertContains('Rodzina Kowalskich', $dashboard->body);
        $t->assertContains('Wyloguj', $dashboard->body);
    });

    $runner->test('csrf token is required for mutating actions', function (TestRunner $t): void {
        $client = logged_in_client();
        $response = $client->post('/', [
            'csrf' => 'invalid-token',
            'action' => 'create_tree',
            'name' => 'E2E Invalid CSRF',
            'description' => '',
        ]);

        $t->assertSame(400, $response->status);
        $t->assertContains('Sesja formularza wygasła.', $response->body);
    });

    $runner->test('user can create and delete own tree', function (TestRunner $t): void {
        $client = logged_in_client();
        $dashboard = $client->get('/?page=dashboard');
        $treeName = 'E2E_tree_' . bin2hex(random_bytes(3));

        $create = $client->post('/', [
            'csrf' => extract_csrf($dashboard->body),
            'action' => 'create_tree',
            'name' => $treeName,
            'description' => 'Temporary test tree.',
        ]);
        $t->assertSame(302, $create->status);

        $afterCreate = $client->get('/?page=dashboard');
        $t->assertContains($treeName, $afterCreate->body);

        $treeId = (int) db()->query('SELECT id FROM family_trees WHERE name = ' . db()->quote($treeName))->fetchColumn();
        $t->assertTrue($treeId > 0, 'Created tree id should exist.');

        $delete = $client->post('/', [
            'csrf' => extract_csrf($afterCreate->body),
            'action' => 'delete_tree',
            'tree_id' => (string) $treeId,
        ]);
        $t->assertSame(302, $delete->status);

        $afterDelete = $client->get('/?page=dashboard');
        $t->assertNotContains($treeName, $afterDelete->body);
    });

    $runner->test('regular user cannot access another user tree', function (TestRunner $t): void {
        $otherUserId = create_e2e_user('e2e_owner_' . bin2hex(random_bytes(3)) . '@example.com', 'E2E Owner');
        $otherTreeId = create_e2e_tree($otherUserId, 'E2E_private_' . bin2hex(random_bytes(3)));
        create_e2e_person($otherTreeId, 'E2E_Private');

        $client = logged_in_client();
        $tree = $client->get('/?page=tree&tree_id=' . $otherTreeId);

        $t->assertSame(404, $tree->status);
        $t->assertContains('Nie znaleziono zasobu albo nie masz do niego dostępu.', $tree->body);
    });

    $runner->test('tree view exposes search, people list link and no lineage back action', function (TestRunner $t): void {
        $client = logged_in_client();
        $tree = $client->get('/?page=tree&tree_id=1');

        $t->assertSame(200, $tree->status);
        $t->assertContains('data-tree-search', $tree->body);
        $t->assertContains('data-tree-mode="full"', $tree->body);
        $t->assertContains('/?page=people&tree_id=1', $tree->body);
        $t->assertContains('tree/search.js', $tree->body);

        $lineage = $client->get('/?page=descendants&tree_id=1&root_id=4');
        $t->assertContains('Pełne drzewo', $lineage->body);
        $t->assertNotContains('back=1', $lineage->body);
        $t->assertNotContains('>Wstecz<', $lineage->body);
    });

    $runner->test('person can be created, found on people list, moved and deleted', function (TestRunner $t): void {
        $client = logged_in_client();
        $unique = 'E2E_' . bin2hex(random_bytes(4));
        $tree = $client->get('/?page=tree&tree_id=1');

        $create = $client->post('/', [
            'csrf' => extract_csrf($tree->body),
            'action' => 'save_person',
            'tree_id' => '1',
            'person_id' => '0',
            'parent_id' => '0',
            'x_position' => '210',
            'y_position' => '252',
            'first_name' => $unique,
            'last_name' => 'Tester',
            'maiden_name' => '',
            'gender' => 'unknown',
            'birth_date' => '1990-01-01',
            'birth_place' => 'Testowo',
            'death_date' => '',
            'death_place' => '',
            'occupation' => 'tester',
            'avatar_color' => '#5f8f86',
            'is_living' => 'on',
            'notes' => 'Created by e2e test.',
        ]);
        $t->assertSame(302, $create->status);

        $people = $client->get('/?page=people&tree_id=1');
        $t->assertContains('data-people-list', $people->body);
        $t->assertContains('data-page-size="40"', $people->body);
        $t->assertContains($unique . ' Tester', $people->body);
        $t->assertContains('delete_person', $people->body);
        $t->assertContains('people/list.js', $people->body);

        $personId = (int) db()->query("SELECT id FROM persons WHERE tree_id = 1 AND first_name = " . db()->quote($unique))->fetchColumn();
        $t->assertTrue($personId > 0, 'Created person id should exist.');

        $move = $client->post('/', [
            'csrf' => extract_csrf($people->body),
            'action' => 'update_position',
            'tree_id' => '1',
            'person_id' => (string) $personId,
            'x_position' => '101',
            'y_position' => '143',
        ]);
        $t->assertSame(200, $move->status);
        $t->assertContains('"ok":true', $move->body);

        $position = db()->query('SELECT x_position, y_position FROM persons WHERE id = ' . $personId)->fetch();
        $t->assertSame(84, (int) $position['x_position']);
        $t->assertSame(126, (int) $position['y_position']);

        $delete = $client->post('/', [
            'csrf' => extract_csrf($people->body),
            'action' => 'delete_person',
            'tree_id' => '1',
            'person_id' => (string) $personId,
            'return_to' => 'people',
        ]);
        $t->assertSame(302, $delete->status);

        $afterDelete = $client->get('/?page=people&tree_id=1');
        $t->assertNotContains($unique . ' Tester', $afterDelete->body);
    });

    $runner->test('person can be edited through save form action', function (TestRunner $t): void {
        $client = logged_in_client();
        $firstName = 'E2E_Edit_' . bin2hex(random_bytes(3));
        $personId = create_e2e_person(1, $firstName);
        $tree = $client->get('/?page=tree&tree_id=1');

        $edit = $client->post('/', [
            'csrf' => extract_csrf($tree->body),
            'action' => 'save_person',
            'tree_id' => '1',
            'person_id' => (string) $personId,
            'parent_id' => '0',
            'x_position' => '210',
            'y_position' => '252',
            'first_name' => $firstName,
            'last_name' => 'Updated',
            'maiden_name' => '',
            'gender' => 'other',
            'birth_date' => '1991-02-03',
            'birth_place' => 'Updated Place',
            'death_date' => '',
            'death_place' => '',
            'occupation' => 'updated tester',
            'avatar_color' => '#123456',
            'is_living' => 'on',
            'notes' => 'Updated by e2e.',
        ]);
        $t->assertSame(302, $edit->status);

        $row = db()->query('SELECT last_name, gender, occupation, avatar_color FROM persons WHERE id = ' . $personId)->fetch();
        $t->assertSame('Updated', $row['last_name']);
        $t->assertSame('other', $row['gender']);
        $t->assertSame('updated tester', $row['occupation']);
        $t->assertSame('#123456', $row['avatar_color']);
    });

    $runner->test('partner and child relationships can be created', function (TestRunner $t): void {
        $client = logged_in_client();
        $suffix = bin2hex(random_bytes(3));
        $parentId = create_e2e_person(1, 'E2E_Parent_' . $suffix, 'One', 210, 252);
        $partnerId = create_e2e_person(1, 'E2E_Partner_' . $suffix, 'Two', 462, 252);
        $childId = create_e2e_person(1, 'E2E_Child_' . $suffix, 'Three', 336, 504);
        $tree = $client->get('/?page=tree&tree_id=1');

        $partner = $client->post('/', [
            'csrf' => extract_csrf($tree->body),
            'action' => 'add_partner',
            'tree_id' => '1',
            'person_id' => (string) $parentId,
            'partner_id' => (string) $partnerId,
            'status' => 'current',
        ]);
        $t->assertSame(302, $partner->status);

        $child = $client->post('/', [
            'csrf' => extract_csrf($tree->body),
            'action' => 'connect_child',
            'tree_id' => '1',
            'parent_id' => (string) $parentId,
            'child_id' => (string) $childId,
            'co_parent_id' => (string) $partnerId,
            'relation_type' => 'biological',
        ]);
        $t->assertSame(302, $child->status);

        $partnershipCount = (int) db()->query(
            'SELECT COUNT(*) FROM partnerships WHERE tree_id = 1 AND person1_id = ' . $parentId . ' AND person2_id = ' . $partnerId
        )->fetchColumn();
        $linkCount = (int) db()->query(
            'SELECT COUNT(*) FROM parent_child WHERE tree_id = 1 AND child_id = ' . $childId . ' AND parent_id IN (' . $parentId . ', ' . $partnerId . ')'
        )->fetchColumn();

        $t->assertSame(1, $partnershipCount);
        $t->assertSame(2, $linkCount);

        $lineage = $client->get('/?page=descendants&tree_id=1&root_id=' . $parentId);
        $t->assertContains('E2E_Child_' . $suffix, $lineage->body);
        $t->assertContains('E2E_Partner_' . $suffix, $lineage->body);
    });

    $runner->test('deleting a person removes its relationships', function (TestRunner $t): void {
        $client = logged_in_client();
        $suffix = bin2hex(random_bytes(3));
        $parentId = create_e2e_person(1, 'E2E_DeleteParent_' . $suffix);
        $childId = create_e2e_person(1, 'E2E_DeleteChild_' . $suffix);
        db()->prepare('INSERT INTO parent_child (tree_id, parent_id, child_id) VALUES (1, :parent_id, :child_id)')->execute([
            'parent_id' => $parentId,
            'child_id' => $childId,
        ]);
        $people = $client->get('/?page=people&tree_id=1');

        $delete = $client->post('/', [
            'csrf' => extract_csrf($people->body),
            'action' => 'delete_person',
            'tree_id' => '1',
            'person_id' => (string) $parentId,
            'return_to' => 'people',
        ]);
        $t->assertSame(302, $delete->status);

        $personExists = (int) db()->query('SELECT COUNT(*) FROM persons WHERE id = ' . $parentId)->fetchColumn();
        $linkExists = (int) db()->query('SELECT COUNT(*) FROM parent_child WHERE parent_id = ' . $parentId . ' OR child_id = ' . $parentId)->fetchColumn();
        $childExists = (int) db()->query('SELECT COUNT(*) FROM persons WHERE id = ' . $childId)->fetchColumn();

        $t->assertSame(0, $personExists);
        $t->assertSame(0, $linkExists);
        $t->assertSame(1, $childExists);
    });

    $runner->test('admin-only page is denied for regular user', function (TestRunner $t): void {
        $client = logged_in_client();
        $admin = $client->get('/?page=admin');

        $t->assertSame(403, $admin->status);
        $t->assertContains('Nie masz uprawnień do tej części aplikacji.', $admin->body);
    });

    $runner->test('admin can open panel and delete tree and user', function (TestRunner $t): void {
        $admin = logged_in_client('admin@example.com', 'Admin123456!');
        $email = 'e2e_admin_target_' . bin2hex(random_bytes(3)) . '@example.com';
        $userId = create_e2e_user($email, 'E2E Admin Target');
        $treeName = 'E2E_admin_tree_' . bin2hex(random_bytes(3));
        $treeId = create_e2e_tree($userId, $treeName);

        $panel = $admin->get('/?page=admin');
        $t->assertSame(200, $panel->status);
        $t->assertContains($email, $panel->body);
        $t->assertContains($treeName, $panel->body);

        $deleteTree = $admin->post('/', [
            'csrf' => extract_csrf($panel->body),
            'action' => 'delete_tree',
            'tree_id' => (string) $treeId,
        ]);
        $t->assertSame(302, $deleteTree->status);
        $treeExists = (int) db()->query('SELECT COUNT(*) FROM family_trees WHERE id = ' . $treeId)->fetchColumn();
        $t->assertSame(0, $treeExists);

        $panelAfterTreeDelete = $admin->get('/?page=admin');
        $deleteUser = $admin->post('/', [
            'csrf' => extract_csrf($panelAfterTreeDelete->body),
            'action' => 'delete_user',
            'user_id' => (string) $userId,
        ]);
        $t->assertSame(302, $deleteUser->status);
        $userExists = (int) db()->query('SELECT COUNT(*) FROM users WHERE id = ' . $userId)->fetchColumn();
        $t->assertSame(0, $userExists);
    });

    prepare_e2e_data();
}

function logged_in_client(string $email = 'user@example.com', string $password = 'User123456!'): HttpClient
{
    $client = new HttpClient(e2e_base_url());
    $login = $client->get('/?page=login');
    $client->post('/?page=login', [
        'csrf' => extract_csrf($login->body),
        'action' => 'login',
        'email' => $email,
        'password' => $password,
    ]);

    return $client;
}

$suite = $argv[1] ?? 'all';
$runner = new TestRunner();

if ($suite === 'unit' || $suite === 'all') {
    echo "Unit tests\n";
    run_unit_tests($runner);
}

if ($suite === 'e2e' || $suite === 'all') {
    echo "\nE2E tests\n";
    run_e2e_tests($runner);
}

if (!in_array($suite, ['unit', 'e2e', 'all'], true)) {
    fwrite(STDERR, "Unknown suite: {$suite}. Use unit, e2e or all.\n");
    exit(2);
}

exit($runner->finish());
