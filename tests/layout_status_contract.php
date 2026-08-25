<?php

declare(strict_types=1);

function add_action(string $name, callable $callback, int $priority = 10, int $acceptedArgs = 1): void {}
function add_filter(string $name, callable $callback, int $priority = 10, int $acceptedArgs = 1): void {}

require_once dirname(__DIR__) . '/plugin.php';

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$ok) $failures[] = $message;
};

try {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE jvb_layouts (
        post_id INTEGER PRIMARY KEY, status TEXT NOT NULL, draft_json TEXT, published_json TEXT
    )');
    $insert = $pdo->prepare('INSERT INTO jvb_layouts (post_id, status, draft_json, published_json) VALUES (?, ?, ?, ?)');
    $largeLayout = str_repeat('x', 4 * 1024 * 1024);
    $insert->execute([10, 'published', $largeLayout, $largeLayout]);
    $insert->execute([20, 'draft', '{}', null]);
    $insert->execute([30, 'invalid', '{}', '{}']);
    unset($largeLayout);
    $pdo->exec('PRAGMA query_only = ON');

    $before = memory_get_usage(true);
    $statuses = jvb_layout_statuses($pdo, [10, '20', 20, 30, 999]);
    $growth = memory_get_usage(true) - $before;
    $check($statuses === [10 => 'published', 20 => 'draft'],
        'bulk status metadata deduplicates IDs, omits missing rows, and rejects unknown persisted states');
    $check($growth < 2 * 1024 * 1024 && !isset($GLOBALS['_jvb_row_cache']),
        'bulk status metadata does not load or cache multi-megabyte layout documents');

    $invalidRejected = 0;
    foreach ([[0], ['01'], ['nope'], ['4294967296'], ['999999999999999999999'], [PHP_INT_MAX], array_fill(0, 201, 1)] as $invalid) {
        try { jvb_layout_statuses($pdo, $invalid); }
        catch (InvalidArgumentException) { $invalidRejected++; }
    }
    $check($invalidRejected === 7, 'bulk status metadata enforces unsigned Core post IDs and the 200-item bound');
    $check(jvb_layout_statuses($pdo, []) === [], 'empty bulk status lookup performs no work');
} catch (Throwable $error) {
    $failures[] = 'unexpected exception: ' . $error->getMessage();
    echo 'FAIL unexpected exception: ' . $error->getMessage() . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, 'Jy Builder layout status contract failed: ' . implode('; ', $failures) . PHP_EOL);
    exit(1);
}

echo "RESULT: ALL PASS\n";
