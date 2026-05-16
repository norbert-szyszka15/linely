<?php
declare(strict_types=1);

final class TreeLayout
{
    public static function full(array $people, array $parentLinks): array
    {
        if (self::hasSavedPositions($people)) {
            return self::fromSavedPositions($people);
        }

        return self::automatic($people, $parentLinks);
    }

    public static function lineage(array $people, array $parentLinks, array $partnerships, int $rootId): array
    {
        $byId = self::byId($people);
        $parents = self::parentsMap($people, $parentLinks);
        $children = self::childrenMap($people, $parentLinks);
        $lineal = [$rootId => 0];

        self::walkAncestors($rootId, -1, $parents, $lineal);
        self::walkDescendants($rootId, 1, $children, $lineal);

        $partnerIds = [];
        foreach ($partnerships as $partnership) {
            $person1 = (int) $partnership['person1_id'];
            $person2 = (int) $partnership['person2_id'];
            if (isset($lineal[$person1]) && isset($byId[$person2])) {
                $partnerIds[$person2] = ['generation' => $lineal[$person1], 'anchor' => $person1];
            }
            if (isset($lineal[$person2]) && isset($byId[$person1])) {
                $partnerIds[$person1] = ['generation' => $lineal[$person2], 'anchor' => $person2];
            }
        }

        $visible = $lineal + $partnerIds;
        [$positions, $width, $height] = self::positionsFromLineage($byId, $lineal, $partnerIds, $rootId);

        return [$positions, $width, $height, array_keys($visible)];
    }

    private static function walkAncestors(int $personId, int $generation, array $parents, array &$lineal): void
    {
        foreach ($parents[$personId] ?? [] as $parentId) {
            if (!isset($lineal[$parentId]) || $generation < $lineal[$parentId]) {
                $lineal[$parentId] = $generation;
                self::walkAncestors($parentId, $generation - 1, $parents, $lineal);
            }
        }
    }

    private static function walkDescendants(int $personId, int $generation, array $children, array &$lineal): void
    {
        foreach ($children[$personId] ?? [] as $childId) {
            if (!isset($lineal[$childId]) || $generation > $lineal[$childId]) {
                $lineal[$childId] = $generation;
                self::walkDescendants($childId, $generation + 1, $children, $lineal);
            }
        }
    }

    private static function positionsFromLineage(array $byId, array $lineal, array $partnerIds, int $rootId): array
    {
        $groups = [];
        foreach ($lineal as $id => $generation) {
            $groups[$generation][] = $id;
        }
        ksort($groups);

        $minGeneration = $groups ? min(array_keys($groups)) : 0;
        $positions = [];
        $centerX = 520;
        $startY = 120 + (abs($minGeneration) * 245);
        $maxX = 0;
        $maxY = 0;

        foreach ($groups as $generation => $ids) {
            usort($ids, function ($a, $b) use ($rootId, $byId) {
                if ($a === $rootId) {
                    return -1;
                }
                if ($b === $rootId) {
                    return 1;
                }
                return strcmp(person_name($byId[$a]), person_name($byId[$b]));
            });

            $count = count($ids);
            foreach ($ids as $index => $id) {
                $x = $centerX + (($index - (($count - 1) / 2)) * 310);
                $y = $startY + ($generation * 245);
                $positions[$id] = ['x' => (int) round($x), 'y' => (int) round($y)];
                $maxX = max($maxX, $x + 300);
                $maxY = max($maxY, $y + 190);
            }
        }

        foreach ($partnerIds as $partnerId => $meta) {
            if (!isset($byId[$partnerId]) || isset($positions[$partnerId])) {
                continue;
            }

            $generation = (int) $meta['generation'];
            $anchorId = (int) $meta['anchor'];
            $anchor = $anchorId && isset($positions[$anchorId])
                ? $positions[$anchorId]
                : ['x' => $centerX, 'y' => $startY + ($generation * 245)];
            $positions[$partnerId] = ['x' => $anchor['x'] + 310, 'y' => $anchor['y']];
            $maxX = max($maxX, $anchor['x'] + 610);
            $maxY = max($maxY, $anchor['y'] + 190);
        }

        return [$positions, max(1200, (int) $maxX + 240), max(760, (int) $maxY + 220)];
    }

    private static function automatic(array $people, array $parentLinks): array
    {
        $byId = self::byId($people);
        $parents = [];
        $children = self::childrenMap($people, $parentLinks);

        foreach ($byId as $id => $_person) {
            $parents[$id] = [];
        }

        foreach ($parentLinks as $link) {
            $parentId = (int) $link['parent_id'];
            $childId = (int) $link['child_id'];
            if (isset($byId[$parentId], $byId[$childId])) {
                $parents[$childId][] = $parentId;
            }
        }

        $generation = [];
        $queue = [];
        foreach ($byId as $id => $_person) {
            if (!$parents[$id]) {
                $generation[$id] = 0;
                $queue[] = $id;
            }
        }

        if (!$queue && $byId) {
            $first = array_key_first($byId);
            $generation[$first] = 0;
            $queue[] = $first;
        }

        while ($queue) {
            $current = array_shift($queue);
            foreach ($children[$current] as $childId) {
                $nextGeneration = ($generation[$current] ?? 0) + 1;
                if (!isset($generation[$childId]) || $nextGeneration > $generation[$childId]) {
                    $generation[$childId] = $nextGeneration;
                    $queue[] = $childId;
                }
            }
        }

        foreach ($byId as $id => $_person) {
            $generation[$id] ??= 0;
        }

        return self::positionsFromGenerations($byId, $generation, 130, 100);
    }

    private static function hasSavedPositions(array $people): bool
    {
        foreach ($people as $person) {
            if ($person['x_position'] === null || $person['y_position'] === null) {
                return false;
            }
        }

        return $people !== [];
    }

    private static function fromSavedPositions(array $people): array
    {
        $positions = [];
        $maxX = 0;
        $maxY = 0;

        foreach ($people as $person) {
            $x = (int) $person['x_position'];
            $y = (int) $person['y_position'];
            $positions[(int) $person['id']] = ['x' => $x, 'y' => $y];
            $maxX = max($maxX, $x + 270);
            $maxY = max($maxY, $y + 190);
        }

        return [$positions, max(1200, $maxX + 260), max(760, $maxY + 220)];
    }

    private static function positionsFromGenerations(array $byId, array $generation, int $startX, int $startY): array
    {
        $groups = [];
        foreach ($generation as $id => $gen) {
            $groups[$gen][] = $id;
        }
        ksort($groups);

        $positions = [];
        $maxX = 0;
        foreach ($groups as $gen => $ids) {
            usort($ids, fn ($a, $b) => strcmp(person_name($byId[$a]), person_name($byId[$b])));
            foreach (array_values($ids) as $index => $id) {
                $x = $startX + ($index * 310);
                $y = $startY + ($gen * 245);
                $positions[$id] = ['x' => $x, 'y' => $y];
                $maxX = max($maxX, $x + 270);
            }
        }

        $maxGeneration = $groups ? max(array_keys($groups)) : 0;
        return [$positions, max(1100, $maxX + 220), max(680, 190 + (($maxGeneration + 1) * 245))];
    }

    private static function byId(array $people): array
    {
        $byId = [];
        foreach ($people as $person) {
            $byId[(int) $person['id']] = $person;
        }
        return $byId;
    }

    private static function childrenMap(array $people, array $parentLinks): array
    {
        $children = [];
        foreach ($people as $person) {
            $children[(int) $person['id']] = [];
        }

        foreach ($parentLinks as $link) {
            $parentId = (int) $link['parent_id'];
            $childId = (int) $link['child_id'];
            if (isset($children[$parentId])) {
                $children[$parentId][] = $childId;
            }
        }

        return $children;
    }

    private static function parentsMap(array $people, array $parentLinks): array
    {
        $parents = [];
        foreach ($people as $person) {
            $parents[(int) $person['id']] = [];
        }

        foreach ($parentLinks as $link) {
            $parentId = (int) $link['parent_id'];
            $childId = (int) $link['child_id'];
            if (isset($parents[$childId])) {
                $parents[$childId][] = $parentId;
            }
        }

        return $parents;
    }
}
