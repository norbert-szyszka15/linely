<?php
declare(strict_types=1);

final class TreeLayout
{
    private const NODE_WIDTH = 250;
    private const NODE_HEIGHT = 150;
    private const NODE_SLOT = 330;
    private const CLUSTER_GAP = 100;
    private const ROW_GAP = 245;
    private const CANVAS_PADDING_X = 920;
    private const CANVAS_PADDING_Y = 560;

    public static function full(array $people, array $parentLinks, array $partnerships): array
    {
        if (self::hasSavedPositions($people)) {
            return self::fromSavedPositions($people);
        }

        return self::automatic($people, $parentLinks, $partnerships);
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
        [$positions, $width, $height] = self::positionsFromLineage($byId, $lineal, $partnerIds, $partnerships, $rootId);

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

    private static function positionsFromLineage(array $byId, array $lineal, array $partnerIds, array $partnerships, int $rootId): array
    {
        $generation = $lineal;
        $anchorHints = [];
        foreach ($partnerIds as $partnerId => $meta) {
            if (!isset($byId[$partnerId])) {
                continue;
            }

            $generation[$partnerId] = (int) $meta['generation'];
            $anchorHints[$partnerId] = (int) $meta['anchor'];
        }

        return self::positionsFromRelationshipLayout($byId, $generation, $partnerships, 160, 120, $rootId, $anchorHints);
    }

    private static function partnershipStatusWeight(string $status): int
    {
        return match ($status) {
            'current', 'spouse' => 0,
            'former' => 1,
            default => 2,
        };
    }

    private static function automatic(array $people, array $parentLinks, array $partnerships): array
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

        self::alignPartnerGenerations($byId, $parentLinks, $partnerships, $generation);

        return self::positionsFromRelationshipLayout($byId, $generation, $partnerships, 150, 100);
    }

    private static function alignPartnerGenerations(array $byId, array $parentLinks, array $partnerships, array &$generation): void
    {
        $changed = true;
        $passes = 0;
        while ($changed && $passes < 20) {
            $changed = false;
            $passes++;

            foreach ($partnerships as $partnership) {
                $person1 = (int) $partnership['person1_id'];
                $person2 = (int) $partnership['person2_id'];
                if (!isset($byId[$person1], $byId[$person2])) {
                    continue;
                }

                $targetGeneration = max((int) ($generation[$person1] ?? 0), (int) ($generation[$person2] ?? 0));
                if (($generation[$person1] ?? 0) !== $targetGeneration) {
                    $generation[$person1] = $targetGeneration;
                    $changed = true;
                }
                if (($generation[$person2] ?? 0) !== $targetGeneration) {
                    $generation[$person2] = $targetGeneration;
                    $changed = true;
                }
            }

            foreach ($parentLinks as $link) {
                $parentId = (int) $link['parent_id'];
                $childId = (int) $link['child_id'];
                if (!isset($byId[$parentId], $byId[$childId])) {
                    continue;
                }

                $targetGeneration = ((int) ($generation[$parentId] ?? 0)) + 1;
                if (($generation[$childId] ?? 0) < $targetGeneration) {
                    $generation[$childId] = $targetGeneration;
                    $changed = true;
                }
            }
        }
    }

    private static function positionsFromRelationshipLayout(
        array $byId,
        array $generation,
        array $partnerships,
        int $startX,
        int $startY,
        ?int $rootId = null,
        array $anchorHints = []
    ): array {
        if (!$byId || !$generation) {
            return [[], 1200, 760];
        }

        $groups = [];
        foreach ($generation as $id => $gen) {
            if (isset($byId[$id])) {
                $groups[(int) $gen][] = (int) $id;
            }
        }
        ksort($groups);

        $partnersByAnchor = self::partnersByAnchor($byId, $generation, $partnerships, $anchorHints);
        $partnerAssigned = [];
        foreach ($partnersByAnchor as $partners) {
            foreach ($partners as $partner) {
                $partnerAssigned[(int) $partner['id']] = true;
            }
        }

        $positions = [];
        $rowBounds = [];
        $minGeneration = min(array_keys($groups));

        foreach ($groups as $gen => $ids) {
            $anchors = array_values(array_filter($ids, fn ($id) => !isset($partnerAssigned[(int) $id])));
            usort($anchors, function ($a, $b) use ($rootId, $byId) {
                if ($rootId !== null) {
                    if ($a === $rootId) {
                        return -1;
                    }
                    if ($b === $rootId) {
                        return 1;
                    }
                }

                return strcmp(person_name($byId[$a]), person_name($byId[$b]));
            });

            $cursorX = $startX;
            $y = $startY + (($gen - $minGeneration) * self::ROW_GAP);
            foreach ($anchors as $anchorId) {
                $partners = $partnersByAnchor[$anchorId] ?? [];
                usort($partners, function (array $a, array $b) use ($byId) {
                    $statusOrder = self::partnershipStatusWeight($a['status']) <=> self::partnershipStatusWeight($b['status']);
                    if ($statusOrder !== 0) {
                        return $statusOrder;
                    }

                    return strcmp(person_name($byId[$a['id']]), person_name($byId[$b['id']]));
                });

                $slots = [0 => $anchorId];
                foreach ($partners as $index => $partner) {
                    $slots[self::partnerSlot($index)] = (int) $partner['id'];
                }

                $minSlot = min(array_keys($slots));
                $maxSlot = max(array_keys($slots));
                $anchorX = $cursorX + (abs($minSlot) * self::NODE_SLOT);
                foreach ($slots as $slot => $personId) {
                    $positions[(int) $personId] = [
                        'x' => (int) round($anchorX + ($slot * self::NODE_SLOT)),
                        'y' => (int) $y,
                    ];
                }

                $cursorX += (($maxSlot - $minSlot + 1) * self::NODE_SLOT) + self::CLUSTER_GAP;
            }

            $rowBounds[$gen] = [
                'left' => $startX,
                'right' => max($startX, $cursorX - self::CLUSTER_GAP),
            ];
        }

        $maxRowWidth = 0;
        foreach ($rowBounds as $bounds) {
            $maxRowWidth = max($maxRowWidth, $bounds['right'] - $bounds['left']);
        }

        foreach ($rowBounds as $gen => $bounds) {
            $shift = ($maxRowWidth - ($bounds['right'] - $bounds['left'])) / 2;
            if ($shift <= 0) {
                continue;
            }

            foreach ($groups[$gen] as $id) {
                if (!isset($positions[$id])) {
                    continue;
                }

                $positions[$id]['x'] = (int) round($positions[$id]['x'] + $shift);
            }
        }

        return self::centeredCanvas($positions);
    }

    private static function centeredCanvas(array $positions): array
    {
        if (!$positions) {
            return [[], 1200, 760];
        }

        $minX = PHP_INT_MAX;
        $minY = PHP_INT_MAX;
        $maxX = 0;
        $maxY = 0;
        foreach ($positions as $position) {
            $minX = min($minX, (int) $position['x']);
            $minY = min($minY, (int) $position['y']);
            $maxX = max($maxX, (int) $position['x'] + self::NODE_WIDTH);
            $maxY = max($maxY, (int) $position['y'] + self::NODE_HEIGHT);
        }

        $contentWidth = $maxX - $minX;
        $contentHeight = $maxY - $minY;
        $width = max(1200, $contentWidth + (self::CANVAS_PADDING_X * 2));
        $height = max(760, $contentHeight + (self::CANVAS_PADDING_Y * 2));
        $shiftX = (int) round((($width - $contentWidth) / 2) - $minX);
        $shiftY = (int) round((($height - $contentHeight) / 2) - $minY);

        foreach ($positions as &$position) {
            $position['x'] = (int) $position['x'] + $shiftX;
            $position['y'] = (int) $position['y'] + $shiftY;
        }
        unset($position);

        return [$positions, $width, $height];
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
            $maxX = max($maxX, $x + self::NODE_WIDTH);
            $maxY = max($maxY, $y + self::NODE_HEIGHT);
        }

        return [
            $positions,
            max(1200, $maxX + self::CANVAS_PADDING_X),
            max(760, $maxY + self::CANVAS_PADDING_Y),
        ];
    }

    private static function partnersByAnchor(array $byId, array $generation, array $partnerships, array $anchorHints): array
    {
        $degree = [];
        foreach ($partnerships as $partnership) {
            $person1 = (int) $partnership['person1_id'];
            $person2 = (int) $partnership['person2_id'];
            if (!isset($byId[$person1], $byId[$person2], $generation[$person1], $generation[$person2])) {
                continue;
            }

            if ((int) $generation[$person1] !== (int) $generation[$person2]) {
                continue;
            }

            $degree[$person1] = ($degree[$person1] ?? 0) + 1;
            $degree[$person2] = ($degree[$person2] ?? 0) + 1;
        }

        $partnersByAnchor = [];
        foreach ($partnerships as $partnership) {
            $person1 = (int) $partnership['person1_id'];
            $person2 = (int) $partnership['person2_id'];
            if (!isset($byId[$person1], $byId[$person2], $generation[$person1], $generation[$person2])) {
                continue;
            }

            if ((int) $generation[$person1] !== (int) $generation[$person2]) {
                continue;
            }

            if (($anchorHints[$person1] ?? null) === $person2) {
                [$anchorId, $partnerId] = [$person2, $person1];
            } elseif (($anchorHints[$person2] ?? null) === $person1) {
                [$anchorId, $partnerId] = [$person1, $person2];
            } elseif (($degree[$person1] ?? 0) === ($degree[$person2] ?? 0)) {
                [$anchorId, $partnerId] = $person1 < $person2 ? [$person1, $person2] : [$person2, $person1];
            } else {
                [$anchorId, $partnerId] = ($degree[$person1] ?? 0) > ($degree[$person2] ?? 0)
                    ? [$person1, $person2]
                    : [$person2, $person1];
            }

            $partnersByAnchor[$anchorId][] = [
                'id' => $partnerId,
                'status' => (string) ($partnership['status'] ?? ''),
            ];
        }

        return $partnersByAnchor;
    }

    private static function partnerSlot(int $index): int
    {
        $distance = intdiv($index, 2) + 1;

        return $index % 2 === 0 ? $distance : -$distance;
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
