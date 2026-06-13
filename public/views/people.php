<main class="people-page">
    <section class="section-head">
        <div>
            <p class="eyebrow">Członkowie rodziny</p>
            <h1><?= h($tree['name']) ?></h1>
            <p>Zarządzaj osobami przypisanymi do tego drzewa.</p>
        </div>
        <div class="toolbar-actions">
            <a class="button secondary" href="/?page=tree&tree_id=<?= (int) $tree['id'] ?>">Wróć do drzewa</a>
            <button class="button primary" type="button" data-modal-target="person-new">Dodaj osobę</button>
        </div>
    </section>

    <section class="people-list-panel glass-panel" data-people-list data-page-size="40">
        <div class="people-list-head">
            <label>Wyszukaj osobę
                <input type="search" data-people-search placeholder="Imię, nazwisko, rok, zawód" autocomplete="off">
            </label>
            <output data-people-count></output>
        </div>

        <?php if (!$people): ?>
            <div class="empty-state">
                <h2>To drzewo jest jeszcze puste.</h2>
                <p>Dodaj pierwszą osobę, żeby rozpocząć budowanie listy.</p>
            </div>
        <?php else: ?>
            <div class="people-list" data-people-items>
                <?php foreach ($people as $person): ?>
                    <?php
                    $personId = (int) $person['id'];
                    $searchText = person_name($person) . ' ' . person_years($person) . ' ' . ($person['occupation'] ?? '') . ' ' . ($person['birth_place'] ?? '');
                    ?>
                    <article class="person-list-item"
                             data-people-item
                             data-person-search="<?= h($searchText) ?>">
                        <span class="avatar" style="background: <?= h($person['avatar_color']) ?>;">
                            <?= h(person_initial($person)) ?>
                        </span>
                        <div class="person-list-main">
                            <strong><?= h(person_name($person)) ?></strong>
                            <small><?= h(person_years($person)) ?><?= $person['occupation'] ? ' · ' . h($person['occupation']) : '' ?></small>
                        </div>
                        <a class="button secondary" href="/?page=descendants&tree_id=<?= (int) $tree['id'] ?>&root_id=<?= $personId ?>">Linia</a>
                        <form method="post" data-delete-person-form>
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_person">
                            <input type="hidden" name="tree_id" value="<?= (int) $tree['id'] ?>">
                            <input type="hidden" name="person_id" value="<?= $personId ?>">
                            <input type="hidden" name="return_to" value="people">
                            <button class="icon-button danger" type="submit" title="Usuń osobę" aria-label="Usuń osobę <?= h(person_name($person)) ?>">🗑</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="people-empty hidden" data-people-empty>
                <h2>Brak wyników.</h2>
                <p>Zmień frazę wyszukiwania.</p>
            </div>

            <nav class="people-pagination" data-people-pagination aria-label="Paginacja listy członków rodziny"></nav>
        <?php endif; ?>
    </section>
</main>

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
