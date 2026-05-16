<main class="form-page">
    <section class="form-card glass-panel">
        <p class="eyebrow">Błąd aplikacji</p>
        <h1>Coś poszło nie tak.</h1>
        <p><?= h($message ?? 'Nieznany błąd.') ?></p>
        <a class="button secondary" href="/?page=dashboard">Wróć</a>
    </section>
</main>
