<?php
$loginError ??= null;
$loginOld ??= [];
$registerError ??= null;
$registerOld ??= [];
$loginInvalid = fn (string $field): string => !empty($loginError['fields'][$field]) ? ' class="is-invalid"' : '';
$registerInvalid = fn (string $field): string => !empty($registerError['fields'][$field]) ? ' class="is-invalid"' : '';
?>
<main class="login-layout">
    <section class="login-hero glass-panel">
        <p class="eyebrow">Projekt studencki</p>
        <h1>Twórz czytelne drzewa rodzinne bez nadmiaru formalności.</h1>
        <p>
            Prototyp ma role użytkownika i administratora, dwa tryby drzewa oraz responsywny interfejs
            w jasnym i ciemnym motywie.
        </p>
        <div class="demo-grid">
            <div>
                <strong>Administrator</strong>
                <span>admin@example.com / AdminPass!2026</span>
            </div>
            <div>
                <strong>Użytkownik</strong>
                <span>user@example.com / UserPass!2026</span>
            </div>
        </div>
    </section>

    <section class="auth-card glass-panel">
        <h2>Zaloguj się</h2>
        <?php if ($loginError): ?>
            <p class="form-error"><?= h($loginError['message']) ?></p>
        <?php endif; ?>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="login">
            <label>Email
                <input name="email" type="email" maxlength="255" value="<?= h($loginOld['email'] ?? 'user@example.com') ?>"<?= $loginInvalid('email') ?>>
            </label>
            <label>Hasło
                <input name="password" type="password" maxlength="128" value=""<?= $loginInvalid('password') ?>>
            </label>
            <button class="button primary" type="submit">Wejdź do aplikacji</button>
        </form>

        <hr class="auth-divider">

        <h2>Utwórz konto</h2>
        <?php if ($registerError): ?>
            <p class="form-error"><?= h($registerError['message']) ?></p>
        <?php endif; ?>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="register">
            <label>Imię i nazwisko
                <input name="name" maxlength="150" value="<?= h($registerOld['name'] ?? '') ?>"<?= $registerInvalid('name') ?>>
            </label>
            <label>Email
                <input name="email" type="email" maxlength="255" value="<?= h($registerOld['email'] ?? '') ?>"<?= $registerInvalid('email') ?>>
            </label>
            <label>Hasło
                <input name="password" type="password" maxlength="128"<?= $registerInvalid('password') ?>>
            </label>
            <label>Powtórz hasło
                <input name="password_confirmation" type="password" maxlength="128"<?= $registerInvalid('password_confirmation') ?>>
            </label>
            <p class="password-hint"><?= h(Auth::passwordRequirementsText()) ?></p>
            <button class="button secondary" type="submit">Zarejestruj</button>
        </form>
    </section>
</main>
