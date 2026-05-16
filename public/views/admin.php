<main class="admin-page">
    <section class="section-head">
        <div>
            <p class="eyebrow">Administrator</p>
            <h1>Zarządzanie aplikacją</h1>
        </div>
        <p>Admin widzi wszystkie drzewa i może usuwać drzewa oraz użytkowników.</p>
    </section>

    <section class="admin-grid">
        <article class="table-card glass-panel">
            <h2>Użytkownicy</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>Imię</th>
                        <th>Email</th>
                        <th>Rola</th>
                        <th>Drzewa</th>
                        <th>Akcja</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr class="<?= $row['deleted_at'] ? 'muted-row' : '' ?>">
                            <td><?= h($row['name']) ?></td>
                            <td><?= h($row['email']) ?></td>
                            <td><?= h($row['role']) ?></td>
                            <td><?= (int) $row['trees_count'] ?></td>
                            <td>
                                <?php if (!$row['deleted_at'] && (int) $row['id'] !== (int) $user['id']): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                        <button class="text-danger" type="submit">Usuń</button>
                                    </form>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="table-card glass-panel">
            <h2>Drzewa</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>Nazwa</th>
                        <th>Właściciel</th>
                        <th>Akcja</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($trees as $tree): ?>
                        <tr>
                            <td><a href="/?page=tree&tree_id=<?= (int) $tree['id'] ?>"><?= h($tree['name']) ?></a></td>
                            <td><?= h($tree['owner_name']) ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_tree">
                                    <input type="hidden" name="tree_id" value="<?= (int) $tree['id'] ?>">
                                    <button class="text-danger" type="submit">Usuń</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</main>
