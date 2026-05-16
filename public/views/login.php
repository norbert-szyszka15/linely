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
                <span>admin@example.com / admin123</span>
            </div>
            <div>
                <strong>Użytkownik</strong>
                <span>user@example.com / user123</span>
            </div>
        </div>
    </section>

    <section class="auth-card glass-panel">
        <h2>Zaloguj się</h2>
        <form method="post" class="stack">
            <input type="hidden" name="action" value="login">
            <label>Email
                <input name="email" type="email" value="user@example.com" required>
            </label>
            <label>Hasło
                <input name="password" type="password" value="user123" required>
            </label>
            <button class="button primary" type="submit">Wejdź do aplikacji</button>
        </form>
    </section>
</main>
