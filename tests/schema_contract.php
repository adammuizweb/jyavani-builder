<?php
declare(strict_types=1);

function add_action(string $name, callable $callback, int $priority = 10, int $acceptedArgs = 1): void {}
function add_filter(string $name, callable $callback, int $priority = 10, int $acceptedArgs = 1): void {}

require_once dirname(__DIR__) . '/plugin.php';

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$ok) $failures[] = $message;
};

$migrationPath = $root . '/migrations/0001-create-builder-tables.sql';
$migration = is_file($migrationPath) ? (string)file_get_contents($migrationPath) : '';
$check($migration !== '', 'initial schema migration is packaged');
$check(substr_count($migration, 'CREATE TABLE IF NOT EXISTS') === 3
    && str_contains($migration, '`jvb_layouts`')
    && str_contains($migration, '`jvb_revisions`')
    && str_contains($migration, '`jvb_templates`'),
    'migration idempotently creates every Builder table');

try {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    unset($GLOBALS['_jvb_row_cache']);
    $check(jvb_get_layout($pdo, 7) === null, 'public layout lookup fails open when the layout table is absent');

    $pdo->exec('CREATE TABLE jvb_layouts (unexpected_column INTEGER)');
    unset($GLOBALS['_jvb_row_cache']);
    $unexpectedFailurePropagated = false;
    try {
        jvb_get_layout($pdo, 8);
    } catch (PDOException) {
        $unexpectedFailurePropagated = true;
    }
    $check($unexpectedFailurePropagated, 'public layout lookup does not hide unrelated database failures');
} catch (Throwable $error) {
    $failures[] = 'unexpected exception: ' . $error->getMessage();
    echo 'FAIL unexpected exception: ' . $error->getMessage() . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, 'Jy Builder schema contract failed: ' . implode('; ', $failures) . PHP_EOL);
    exit(1);
}

echo "RESULT: ALL PASS\n";
