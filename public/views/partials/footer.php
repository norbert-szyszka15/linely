    </div>
    <?php
    $scripts = [
        'ui/theme',
        'ui/modals',
        'ui/popovers',
        'ui/tabs',
        'forms/child-parent-options',
        'tree/workspace',
        'tree/search',
        'people/list',
    ];
    foreach ($scripts as $script):
        $scriptPath = __DIR__ . '/../../scripts/' . $script . '.js';
        $version = file_exists($scriptPath) ? (string) filemtime($scriptPath) : '1';
    ?>
        <script src="/scripts/<?= h($script) ?>.js?v=<?= h($version) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
