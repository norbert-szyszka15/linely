<?php
$formPerson ??= null;
$parentId ??= 0;
$defaultX ??= 160;
$defaultY ??= 120;
?>
<form method="post" class="person-form">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_person">
    <input type="hidden" name="tree_id" value="<?= (int) $tree['id'] ?>">
    <input type="hidden" name="person_id" value="<?= (int) ($formPerson['id'] ?? 0) ?>">
    <input type="hidden" name="parent_id" value="<?= (int) $parentId ?>">
    <input type="hidden" name="x_position" value="<?= (int) $defaultX ?>">
    <input type="hidden" name="y_position" value="<?= (int) $defaultY ?>">

    <label>Imię
        <input name="first_name" value="<?= h($formPerson['first_name'] ?? '') ?>" required>
    </label>
    <label>Nazwisko
        <input name="last_name" value="<?= h($formPerson['last_name'] ?? '') ?>">
    </label>
    <label>Nazwisko panieńskie
        <input name="maiden_name" value="<?= h($formPerson['maiden_name'] ?? '') ?>">
    </label>
    <label>Płeć
        <select name="gender">
            <?php foreach (['unknown' => 'Nieznana', 'female' => 'Kobieta', 'male' => 'Mężczyzna', 'other' => 'Inna'] as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= ($formPerson['gender'] ?? 'unknown') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Data urodzenia
        <input name="birth_date" type="date" value="<?= h($formPerson['birth_date'] ?? '') ?>">
    </label>
    <label>Miejsce urodzenia
        <input name="birth_place" value="<?= h($formPerson['birth_place'] ?? '') ?>">
    </label>
    <label>Data śmierci
        <input name="death_date" type="date" value="<?= h($formPerson['death_date'] ?? '') ?>">
    </label>
    <label>Miejsce śmierci
        <input name="death_place" value="<?= h($formPerson['death_place'] ?? '') ?>">
    </label>
    <label>Zawód
        <input name="occupation" value="<?= h($formPerson['occupation'] ?? '') ?>">
    </label>
    <label>Kolor kafelka
        <input name="avatar_color" type="color" value="<?= h($formPerson['avatar_color'] ?? '#5f8f86') ?>">
    </label>
    <label class="check-row">
        <input name="is_living" type="checkbox" <?= (int) ($formPerson['is_living'] ?? 1) ? 'checked' : '' ?>>
        Osoba żyje
    </label>
    <?php if ($parentId): ?>
        <label>Drugi rodzic
            <select name="co_parent_id">
                <option value="0">Brak / nieznany</option>
                <?php foreach ($people as $candidate): ?>
                    <?php if ((int) $candidate['id'] === (int) $parentId) continue; ?>
                    <option value="<?= (int) $candidate['id'] ?>"><?= h(person_name($candidate)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Typ relacji z rodzicami
            <select name="relation_type">
                <option value="biological">Biologiczna</option>
                <option value="adoptive">Adopcyjna</option>
                <option value="step">Przybrana</option>
                <option value="unknown">Nieznana</option>
            </select>
        </label>
    <?php endif; ?>
    <label class="full-span">Notatki
        <textarea name="notes" rows="4"><?= h($formPerson['notes'] ?? '') ?></textarea>
    </label>

    <div class="form-actions full-span">
        <button class="button primary" type="submit">Zapisz</button>
        <button class="button secondary" type="button" data-modal-close>Anuluj</button>
    </div>
</form>
