<?php
$byId = [];
foreach ($people as $person) {
    $byId[(int) $person['id']] = $person;
}
$isDescendants = $mode === 'descendants';
$heading = $isDescendants
    ? 'Linia prosta: ' . (isset($root) && $root ? person_name($root) : $tree['name'])
    : $tree['name'];
?>

<main class="tree-page">
    <section class="tree-toolbar glass-panel">
        <div>
            <p class="eyebrow"><?= $isDescendants ? 'Widok linii prostej' : 'Pełne drzewo' ?></p>
            <h1><?= h($heading) ?></h1>
            <p>
                <?= $isDescendants
                    ? 'Pokazani są rodzice, dziadkowie, dzieci, wnuki oraz partnerzy wybranej osoby, bez rodzeństwa i kuzynostwa.'
                    : 'Kliknij osobę, aby zobaczyć jej linię prostą. Osoby można przeciągać, canvas przesuwać środkowym przyciskiem myszy, a widok przybliżać i oddalać.' ?>
            </p>
        </div>
        <div class="toolbar-actions">
            <?php if ($isDescendants && !empty($canGoBack)): ?>
                <a class="button secondary" href="/?page=descendants&tree_id=<?= (int) $tree['id'] ?>&back=1">Wstecz</a>
            <?php endif; ?>
            <?php if (!$isDescendants): ?>
                <button class="button primary" type="button" data-modal-target="person-new">Dodaj osobę</button>
            <?php else: ?>
                <a class="button secondary" href="/?page=tree&tree_id=<?= (int) $tree['id'] ?>">Pełne drzewo</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$people): ?>
        <section class="empty-state glass-panel">
            <h2>To drzewo jest jeszcze puste.</h2>
            <button class="button primary" type="button" data-modal-target="person-new">Dodaj pierwszą osobę</button>
        </section>
    <?php else: ?>
        <section class="tree-workspace" data-tree-root data-tree-id="<?= (int) $tree['id'] ?>" data-csrf="<?= h(csrf_token()) ?>">
            <div class="tree-controls glass-panel">
                <button class="icon-button" type="button" data-zoom-out title="Oddal">−</button>
                <output data-zoom-label>100%</output>
                <button class="icon-button" type="button" data-zoom-in title="Przybliż">+</button>
                <button class="button secondary" type="button" data-zoom-reset>Reset</button>
            </div>

            <div class="tree-viewport" data-tree-viewport>
                <div class="tree-stage"
                     data-stage
                     data-base-width="<?= (int) $width ?>"
                     data-base-height="<?= (int) $height ?>"
                     style="width: <?= (int) $width ?>px; height: <?= (int) $height ?>px;">
                    <div class="tree-transform" data-tree-transform>
                        <svg class="tree-lines" width="<?= (int) $width ?>" height="<?= (int) $height ?>" viewBox="0 0 <?= (int) $width ?> <?= (int) $height ?>">
                            <?php foreach ($parentLinks as $link): ?>
                                <?php
                                $parentId = (int) $link['parent_id'];
                                $childId = (int) $link['child_id'];
                                if (!isset($positions[$parentId], $positions[$childId])) {
                                    continue;
                                }
                                ?>
                                <path class="line child-line" data-line="child" data-from="<?= $parentId ?>" data-to="<?= $childId ?>" />
                            <?php endforeach; ?>

                            <?php foreach ($partnerships as $partnership): ?>
                                <?php
                                $person1 = (int) $partnership['person1_id'];
                                $person2 = (int) $partnership['person2_id'];
                                if (!isset($positions[$person1], $positions[$person2])) {
                                    continue;
                                }
                                ?>
                                <line class="line partner-line <?= h($partnership['status']) ?>"
                                      data-line="partner"
                                      data-from="<?= $person1 ?>"
                                      data-to="<?= $person2 ?>" />
                            <?php endforeach; ?>
                        </svg>

                        <?php foreach ($positions as $id => $position): ?>
                            <?php $person = $byId[$id]; ?>
                            <article class="person-node <?= $rootId === $id ? 'is-root' : '' ?>"
                                     data-node
                                     data-person-id="<?= (int) $id ?>"
                                     data-draggable="<?= $isDescendants ? 'false' : 'true' ?>"
                                     style="left: <?= (int) $position['x'] ?>px; top: <?= (int) $position['y'] ?>px;">
                                <a class="person-main"
                                   href="/?page=descendants&tree_id=<?= (int) $tree['id'] ?>&root_id=<?= (int) $id ?>"
                                   <?= $isDescendants ? '' : 'data-drag-handle' ?>>
                                    <span class="avatar" style="background: <?= h($person['avatar_color']) ?>;">
                                        <?= h(person_initial($person)) ?>
                                    </span>
                                    <span>
                                        <strong><?= h(person_name($person)) ?></strong>
                                        <small><?= h(person_years($person)) ?><?= $person['occupation'] ? ' · ' . h($person['occupation']) : '' ?></small>
                                    </span>
                                </a>

                                <?php if (!$isDescendants): ?>
                                    <div class="node-actions">
                                        <button type="button" data-modal-target="person-edit-<?= (int) $id ?>">Edytuj</button>
                                        <button type="button" data-popover-target="child-<?= (int) $id ?>">Dziecko</button>
                                        <button type="button" data-popover-target="partner-<?= (int) $id ?>">Partner</button>
                                    </div>

                                    <aside class="node-popover" data-popover="partner-<?= (int) $id ?>">
                                        <div class="popover-head">
                                            <strong>Dodaj partnera</strong>
                                            <button type="button" data-popover-close>×</button>
                                        </div>
                                        <form method="post" class="stack compact-stack">
                                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="add_partner">
                                            <input type="hidden" name="tree_id" value="<?= (int) $tree['id'] ?>">
                                            <input type="hidden" name="person_id" value="<?= (int) $id ?>">
                                            <label>Osoba
                                                <select name="partner_id" required>
                                                    <?php foreach ($people as $candidate): ?>
                                                        <?php if ((int) $candidate['id'] === (int) $id) continue; ?>
                                                        <option value="<?= (int) $candidate['id'] ?>"><?= h(person_name($candidate)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>Status
                                                <select name="status">
                                                    <option value="current">Obecna relacja</option>
                                                    <option value="spouse">Małżeństwo</option>
                                                    <option value="former">Była relacja</option>
                                                </select>
                                            </label>
                                            <button class="button primary" type="submit">Dodaj</button>
                                        </form>
                                    </aside>

                                    <aside class="node-popover wider" data-popover="child-<?= (int) $id ?>">
                                        <div class="popover-head">
                                            <strong>Dodaj dziecko</strong>
                                            <button type="button" data-popover-close>×</button>
                                        </div>
                                        <div class="segmented" role="tablist">
                                            <button type="button" class="active" data-tab-target="new-child-<?= (int) $id ?>">Nowa osoba</button>
                                            <button type="button" data-tab-target="existing-child-<?= (int) $id ?>">Istniejąca</button>
                                        </div>
                                        <div data-tab-panel="new-child-<?= (int) $id ?>">
                                            <button class="button primary full-button" type="button" data-modal-target="person-child-<?= (int) $id ?>">Utwórz potomka</button>
                                        </div>
                                        <form method="post" class="stack compact-stack hidden" data-tab-panel="existing-child-<?= (int) $id ?>">
                                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="connect_child">
                                            <input type="hidden" name="tree_id" value="<?= (int) $tree['id'] ?>">
                                            <input type="hidden" name="parent_id" value="<?= (int) $id ?>">
                                            <label>Wybierz osobę
                                                <select name="child_id" required>
                                                    <?php foreach ($people as $candidate): ?>
                                                        <?php if ((int) $candidate['id'] === (int) $id) continue; ?>
                                                        <option value="<?= (int) $candidate['id'] ?>"><?= h(person_name($candidate)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>Typ relacji
                                                <select name="relation_type">
                                                    <option value="biological">Biologiczna</option>
                                                    <option value="adoptive">Adopcyjna</option>
                                                    <option value="step">Przybrana</option>
                                                    <option value="unknown">Nieznana</option>
                                                </select>
                                            </label>
                                            <button class="button primary" type="submit">Połącz</button>
                                        </form>
                                    </aside>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php if (!$isDescendants): ?>
    <div class="modal-layer" data-modal="person-new" aria-hidden="true">
        <div class="modal-backdrop" data-modal-close></div>
        <section class="modal-card glass-panel" role="dialog" aria-modal="true">
            <div class="modal-head">
                <div>
                    <p class="eyebrow">Dodawanie</p>
                    <h2>Nowa osoba</h2>
                </div>
                <button class="icon-button" type="button" data-modal-close>×</button>
            </div>
            <?php $formPerson = null; $parentId = 0; $defaultX = 180; $defaultY = 130; require __DIR__ . '/partials/person-form.php'; ?>
        </section>
    </div>

    <?php foreach ($people as $person): ?>
        <?php $personId = (int) $person['id']; ?>
        <div class="modal-layer" data-modal="person-edit-<?= $personId ?>" aria-hidden="true">
            <div class="modal-backdrop" data-modal-close></div>
            <section class="modal-card glass-panel" role="dialog" aria-modal="true">
                <div class="modal-head">
                    <div>
                        <p class="eyebrow">Edycja</p>
                        <h2><?= h(person_name($person)) ?></h2>
                    </div>
                    <button class="icon-button" type="button" data-modal-close>×</button>
                </div>
                <?php $formPerson = $person; $parentId = 0; $defaultX = (int) $person['x_position']; $defaultY = (int) $person['y_position']; require __DIR__ . '/partials/person-form.php'; ?>
            </section>
        </div>

        <div class="modal-layer" data-modal="person-child-<?= $personId ?>" aria-hidden="true">
            <div class="modal-backdrop" data-modal-close></div>
            <section class="modal-card glass-panel" role="dialog" aria-modal="true">
                <div class="modal-head">
                    <div>
                        <p class="eyebrow">Nowy potomek</p>
                        <h2>Dziecko osoby: <?= h(person_name($person)) ?></h2>
                    </div>
                    <button class="icon-button" type="button" data-modal-close>×</button>
                </div>
                <?php
                $position = $positions[$personId] ?? ['x' => 180, 'y' => 130];
                $formPerson = null;
                $parentId = $personId;
                $defaultX = (int) $position['x'];
                $defaultY = (int) $position['y'] + 250;
                require __DIR__ . '/partials/person-form.php';
                ?>
            </section>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
