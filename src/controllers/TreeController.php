<?php
declare(strict_types=1);

final class TreeController extends BaseController
{
    public function full(): void
    {
        $user = $this->requireLogin();
        $tree = $this->requireTree((int) ($_GET['tree_id'] ?? 0), $user);
        $people = $this->people->forTree((int) $tree['id']);
        $parentLinks = $this->people->parentLinks((int) $tree['id']);
        $partnerships = $this->people->partnerships((int) $tree['id']);
        [$positions, $width, $height] = TreeLayout::full($people, $parentLinks);

        View::render('tree', [
            'mode' => 'full',
            'tree' => $tree,
            'people' => $people,
            'parentLinks' => $parentLinks,
            'partnerships' => $partnerships,
            'positions' => $positions,
            'width' => $width,
            'height' => $height,
            'rootId' => null,
        ], $user, 'Pełne drzewo');
    }

    public function descendants(): void
    {
        $user = $this->requireLogin();
        $tree = $this->requireTree((int) ($_GET['tree_id'] ?? 0), $user);
        $people = $this->people->forTree((int) $tree['id']);
        $parentLinks = $this->people->parentLinks((int) $tree['id']);
        $partnerships = $this->people->partnerships((int) $tree['id']);

        $rootId = (int) ($_GET['root_id'] ?? ($tree['root_person_id'] ?: ($people[0]['id'] ?? 0)));
        $root = $rootId ? $this->people->find($rootId, (int) $tree['id']) : null;
        if (!$root && $people) {
            $rootId = (int) $people[0]['id'];
            $root = $people[0];
        }

        $historyKey = 'desc_history_' . $tree['id'];
        $_SESSION[$historyKey] ??= [];
        if (isset($_GET['back'])) {
            array_pop($_SESSION[$historyKey]);
            $previous = end($_SESSION[$historyKey]) ?: ($tree['root_person_id'] ?: $rootId);
            redirect('/?page=descendants&tree_id=' . (int) $tree['id'] . '&root_id=' . (int) $previous);
        }
        if ($rootId && end($_SESSION[$historyKey]) !== $rootId) {
            $_SESSION[$historyKey][] = $rootId;
        }

        if ($rootId) {
            [$positions, $width, $height, $visibleIds] = TreeLayout::lineage($people, $parentLinks, $partnerships, $rootId);
            $visible = array_flip(array_map('intval', $visibleIds));
            $people = array_values(array_filter($people, fn ($person) => isset($visible[(int) $person['id']])));
            $parentLinks = array_values(array_filter($parentLinks, fn ($link) => isset($visible[(int) $link['parent_id']], $visible[(int) $link['child_id']])));
            $partnerships = array_values(array_filter($partnerships, fn ($partnership) => isset($visible[(int) $partnership['person1_id']], $visible[(int) $partnership['person2_id']])));
        } else {
            [$positions, $width, $height] = [[], 1100, 680];
            $partnerships = [];
        }

        View::render('tree', [
            'mode' => 'descendants',
            'tree' => $tree,
            'people' => $people,
            'parentLinks' => $parentLinks,
            'partnerships' => $partnerships,
            'positions' => $positions,
            'width' => $width,
            'height' => $height,
            'rootId' => $rootId,
            'root' => $root,
            'canGoBack' => count($_SESSION[$historyKey]) > 1,
        ], $user, 'Linia prosta');
    }

    public function savePerson(): void
    {
        verify_csrf();
        $user = $this->requireLogin();
        $tree = $this->requireTree((int) $_POST['tree_id'], $user);
        $personId = (int) ($_POST['person_id'] ?? 0);

        if ($personId && !$this->people->find($personId, (int) $tree['id'])) {
            flash('Nie znaleziono osoby do edycji.', 'error');
            redirect('/?page=tree&tree_id=' . $tree['id']);
        }

        $data = $this->personData($tree);
        if (!$personId) {
            $data['x_position'] = (int) ($_POST['x_position'] ?? 160);
            $data['y_position'] = (int) ($_POST['y_position'] ?? 120);
        }

        $savedId = $this->people->save($data, $personId ?: null);

        if (!$personId && !empty($_POST['parent_id'])) {
            $this->people->addParentChild(
                (int) $tree['id'],
                (int) $_POST['parent_id'],
                $savedId,
                $_POST['relation_type'] ?? 'biological'
            );
        }

        if (!$personId) {
            $this->trees->setRootIfEmpty((int) $tree['id'], $savedId);
        }

        flash($personId ? 'Zapisano zmiany osoby.' : 'Dodano osobę do drzewa.');
        redirect('/?page=tree&tree_id=' . $tree['id']);
    }

    public function addPartner(): void
    {
        verify_csrf();
        $user = $this->requireLogin();
        $tree = $this->requireTree((int) $_POST['tree_id'], $user);
        $person = $this->people->find((int) $_POST['person_id'], (int) $tree['id']);
        $partner = $this->people->find((int) $_POST['partner_id'], (int) $tree['id']);

        if (!$person || !$partner || (int) $person['id'] === (int) $partner['id']) {
            flash('Wybierz dwie różne osoby z tego samego drzewa.', 'error');
            redirect('/?page=tree&tree_id=' . $tree['id']);
        }

        $this->people->addPartnership([
            'tree_id' => $tree['id'],
            'person1_id' => $person['id'],
            'person2_id' => $partner['id'],
            'status' => $_POST['status'] ?? 'current',
            'start_date' => value_or_null('start_date'),
            'end_date' => value_or_null('end_date'),
            'notes' => value_or_null('notes'),
        ]);

        flash('Dodano relację partnerską.');
        redirect('/?page=tree&tree_id=' . $tree['id']);
    }

    public function connectChild(): void
    {
        verify_csrf();
        $user = $this->requireLogin();
        $tree = $this->requireTree((int) $_POST['tree_id'], $user);
        $parent = $this->people->find((int) $_POST['parent_id'], (int) $tree['id']);
        $child = $this->people->find((int) $_POST['child_id'], (int) $tree['id']);

        if (!$parent || !$child || (int) $parent['id'] === (int) $child['id']) {
            flash('Wybierz dwie różne osoby z tego samego drzewa.', 'error');
            redirect('/?page=tree&tree_id=' . $tree['id']);
        }

        $this->people->addParentChild(
            (int) $tree['id'],
            (int) $parent['id'],
            (int) $child['id'],
            $_POST['relation_type'] ?? 'biological'
        );

        flash('Dodano relację rodzic-dziecko.');
        redirect('/?page=tree&tree_id=' . $tree['id']);
    }

    public function updatePosition(): void
    {
        verify_csrf();
        $user = $this->requireLogin();
        $tree = $this->requireTree((int) $_POST['tree_id'], $user);
        $person = $this->people->find((int) $_POST['person_id'], (int) $tree['id']);

        if (!$person) {
            http_response_code(404);
            echo json_encode(['ok' => false]);
            return;
        }

        $this->people->updatePosition(
            (int) $tree['id'],
            (int) $person['id'],
            $this->snapToGrid(max(0, (int) $_POST['x_position'])),
            $this->snapToGrid(max(0, (int) $_POST['y_position']))
        );

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    private function snapToGrid(int $value): int
    {
        return (int) round($value / 42) * 42;
    }
}
