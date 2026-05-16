<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> | Linely</title>
    <style>
        html { background: #eef2ef; }
        html[data-theme="dark"] { background: #101615; color-scheme: dark; }
    </style>
    <script>
        (() => {
            const stored = localStorage.getItem("linely-theme");
            const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
            if (stored === "dark" || (!stored && prefersDark)) {
                document.documentElement.dataset.theme = "dark";
            }
        })();
    </script>
    <link rel="stylesheet" href="/styles/main.css">
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="<?= $user ? '/?page=dashboard' : '/?page=login' ?>">
            <span class="brand-mark">L</span>
            <span>
                <strong>Linely</strong>
                <small>drzewa genealogiczne</small>
            </span>
        </a>

        <nav class="top-actions">
            <?php if ($user): ?>
                <a class="nav-link" href="/?page=dashboard">Drzewa</a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a class="nav-link" href="/?page=admin">Admin</a>
                <?php endif; ?>
                <button class="icon-button" type="button" data-theme-toggle title="Zmień motyw">☾</button>
                <a class="button secondary" href="/?action=logout">Wyloguj</a>
            <?php else: ?>
                <button class="icon-button" type="button" data-theme-toggle title="Zmień motyw">☾</button>
            <?php endif; ?>
        </nav>
    </header>

    <?php if ($flash): ?>
        <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>
