<main class="page-grid">
    <section class="section-head">
        <div>
            <p class="eyebrow">Panel użytkownika</p>
            <h1>Twoje drzewa genealogiczne</h1>
        </div>
        <p>Zalogowano jako <?= h($user['name']) ?>.</p>
    </section>

    <section class="cards-grid">
        <?php foreach ($trees as $tree): ?>
            <article class="tree-card">
                <div>
                    <p class="eyebrow"><?= h($tree['owner_name']) ?></p>
                    <h2><?= h($tree['name']) ?></h2>
                    <p><?= h($tree['description'] ?: 'Brak opisu.') ?></p>
                </div>
                <div class="card-actions">
                    <a class="button primary" href="/?page=tree&tree_id=<?= (int) $tree['id'] ?>">Pełne drzewo</a>
                    <a class="button secondary" href="/?page=descendants&tree_id=<?= (int) $tree['id'] ?>">Linia prosta</a>
                </div>
            </article>
        <?php endforeach; ?>

        <article class="tree-card create-card">
            <h2>Nowe drzewo</h2>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_tree">
                <label>Nazwa
                    <input name="name" placeholder="np. Rodzina Nowaków" required>
                </label>
                <label>Opis
                    <textarea name="description" rows="3" placeholder="Krótki opis drzewa"></textarea>
                </label>
                <button class="button primary" type="submit">Utwórz</button>
            </form>
        </article>
    </section>
</main>
