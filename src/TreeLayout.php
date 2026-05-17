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
            $status = (string) ($partnership['status'] ?? '');
            if (
                isset($lineal[$person1], $byId[$person2])
                && self::shouldShowLineagePartner($person1, $person2, $rootId, $lineal, $parents, $children)
            ) {
                $partnerIds[$person2] = ['generation' => $lineal[$person1], 'anchor' => $person1, 'status' => $status];
            }
            if (
                isset($lineal[$person2], $byId[$person1])
                && self::shouldShowLineagePartner($person2, $person1, $rootId, $lineal, $parents, $children)
            ) {
                $partnerIds[$person1] = ['generation' => $lineal[$person2], 'anchor' => $person2, 'status' => $status];
            }
        }

        $visible = $lineal + $partnerIds;
        [$positions, $width, $height] = self::positionsFromLineage($byId, $lineal, $partnerIds, $rootId);

        return [$positions, $width, $height, array_keys($visible)];
    }

    private static function shouldShowLineagePartner(int $anchorId, int $partnerId, int $rootId, array $lineal, array $parents, array $children): bool
    {
        if ($anchorId === $rootId) {
            return true;
        }

        foreach ($children[$anchorId] ?? [] as $childId) {
            if (!isset($lineal[$childId])) {
                continue;
            }

            if (in_array($partnerId, $parents[$childId] ?? [], true)) {
                return true;
            }
        }

        return false;
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
        $occupiedByGeneration = [];
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
                $occupiedByGeneration[$generation][] = (int) round($x);
                $maxX = max($maxX, $x + 300);
                $maxY = max($maxY, $y + 190);
            }
        }

        $partnersByAnchor = [];
        foreach ($partnerIds as $partnerId => $meta) {
            if (!isset($byId[$partnerId]) || isset($positions[$partnerId])) {
                continue;
            }

            $generation = (int) $meta['generation'];
            $anchorId = (int) $meta['anchor'];
            $partnersByAnchor[$generation . ':' . $anchorId][] = [
                'id' => $partnerId,
                'generation' => $generation,
                'anchor' => $anchorId,
                'status' => (string) ($meta['status'] ?? ''),
            ];
        }

        foreach ($partnersByAnchor as $partners) {
            usort($partners, function (array $a, array $b) use ($byId) {
                $statusOrder = self::partnershipStatusWeight($a['status']) <=> self::partnershipStatusWeight($b['status']);
                if ($statusOrder !== 0) {
                    return $statusOrder;
                }

                return strcmp(person_name($byId[$a['id']]), person_name($byId[$b['id']]));
            });

            foreach ($partners as $partner) {
                $partnerId = (int) $partner['id'];
                $generation = (int) $partner['generation'];
                $anchorId = (int) $partner['anchor'];
                $occupiedByGeneration[$generation] ??= [];

                $anchor = $anchorId && isset($positions[$anchorId])
                    ? $positions[$anchorId]
                    : ['x' => $centerX, 'y' => $startY + ($generation * 245)];

                $slot = 1;
                $x = $anchor['x'] + (310 * $slot);
                while (self::isSlotOccupied($occupiedByGeneration[$generation], $x)) {
                    $slot++;
                    $x = $anchor['x'] + (310 * $slot);
                }

                $positions[$partnerId] = ['x' => (int) round($x), 'y' => (int) $anchor['y']];
                $occupiedByGeneration[$generation][] = (int) round($x);
                $maxX = max($maxX, $x + 300);
                $maxY = max($maxY, $anchor['y'] + 190);
            }
        }

        return [$positions, max(1200, (int) $maxX + 240), max(760, (int) $maxY + 220)];
    }

    private static function isSlotOccupied(array $occupiedXs, int|float $x): bool
    {
        foreach ($occupiedXs as $occupiedX) {
            if (abs((int) $occupiedX - (int) round($x)) < 290) {
                return true;
            }
        }

        return false;
    }

    private static function partnershipStatusWeight(string $status): int
    {
        return match ($status) {
            'current', 'spouse' => 0,
            'former' => 1,
            default => 2,
        };
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
