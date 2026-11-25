<?php
declare(strict_types=1);

function add_action(string $name, callable $callback, int $priority = 10, int $acceptedArgs = 1): void {}
function add_filter(string $name, callable $callback, int $priority = 10, int $acceptedArgs = 1): void {}

function get_assignment(PDO $pdo, string $slot): ?array {
    $stmt = $pdo->prepare('SELECT * FROM assignments WHERE slot_key = ? LIMIT 1');
    $stmt->execute([$slot]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function resolve_template(PDO $pdo, string $slot): array {
    if ($slot !== 'main.homepage') return ['type' => 'theme_file'];
    $assignment = $pdo->query("SELECT custom_post_id FROM assignments WHERE slot_key = 'main.homepage' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!is_array($assignment) || (int)($assignment['custom_post_id'] ?? 0) <= 0) return ['type' => 'theme_file'];
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$assignment['custom_post_id']]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($post) ? ['type' => 'custom_post', 'post' => $post] : ['type' => 'custom_post'];
}

require_once dirname(__DIR__) . '/plugin.php';

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$ok) $failures[] = $message;
};

try {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE assignments (slot_key TEXT PRIMARY KEY, custom_post_id INTEGER, theme_id INTEGER)');
    $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, slug TEXT, type TEXT, status TEXT, is_deleted INTEGER DEFAULT 0)');
    $pdo->exec('CREATE TABLE jvb_layouts (post_id INTEGER PRIMARY KEY, status TEXT, published_json TEXT)');
    $pdo->exec('CREATE TABLE jvb_home_locales (source_post_id INTEGER, locale TEXT, localized_post_id INTEGER, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');

    $check(jvb_home_builder_target($pdo) === ['state' => 'none', 'post_id' => null],
        'homepage without a custom assignment has no Builder target');

    $pdo->exec("INSERT INTO posts (id, type, status, is_deleted) VALUES (7, 'theme', 'published', 0)");
    $pdo->exec("INSERT INTO jvb_layouts VALUES (7, 'published', '{\"sections\":[{}]}')");
    $pdo->exec("INSERT INTO assignments (slot_key, custom_post_id) VALUES ('main.homepage', 7)");
    $check(jvb_home_builder_target($pdo) === ['state' => 'assigned', 'post_id' => 7],
        'published Theme Content assignment is the homepage Builder target');

    $pdo->exec("INSERT INTO posts (id, slug, type, status, is_deleted) VALUES (8, 'home-id', 'theme', 'published', 0)");
    $pdo->exec("INSERT INTO jvb_layouts VALUES (8, 'published', '{\"sections\":[{}]}')");
    $check(jvb_set_home_locale_target($pdo, 7, 'id', 8)
        && !jvb_set_home_locale_target($pdo, 7, 'en', 8)
        && !jvb_set_home_locale_target($pdo, 7, 'id', 7),
        'localized homepage mapping API validates Theme Content layouts and locale identity');
    $GLOBALS['_jvb_home_locale'] = 'id';
    $check(jvb_home_builder_target($pdo) === ['state' => 'localized', 'post_id' => 8, 'source_post_id' => 7, 'locale' => 'id'],
        'published locale mapping selects an isolated localized Builder homepage');
    $check(jvb_homepage_available_locales($pdo) === ['en', 'id'] && jvb_is_homepage_request_path($pdo, '/id/'),
        'localized Builder homepages advertise only published locale mappings');
    $check(jvb_remove_home_locale_target($pdo, 7, 'id')
        && jvb_home_builder_target($pdo) === ['state' => 'blocked', 'post_id' => null]
        && jvb_set_home_locale_target($pdo, 7, 'id', 8),
        'localized homepage mapping API removes and restores routes without exposing fallback content');
    unset($GLOBALS['_jvb_home_locale']);

    $pdo->exec("UPDATE posts SET status = 'draft' WHERE id = 7");
    $check(jvb_home_builder_target($pdo) === ['state' => 'blocked', 'post_id' => null],
        'draft Theme Content assignment is blocked from live Builder rendering');

    $pdo->exec("UPDATE posts SET status = 'published', type = 'page' WHERE id = 7");
    $check(jvb_home_builder_target($pdo) === ['state' => 'blocked', 'post_id' => null],
        'non-theme custom assignment is blocked from the homepage Builder target');

    $pdo->exec("DELETE FROM assignments WHERE slot_key = 'main.homepage'");
    $pdo->exec("INSERT INTO assignments (slot_key, custom_post_id, theme_id) VALUES ('main.homepage', NULL, 3)");
    $check(jvb_home_builder_target($pdo) === ['state' => 'core', 'post_id' => null],
        'existing Core physical-theme assignment is not overridden by the legacy homepage setting');
} catch (Throwable $error) {
    $failures[] = 'unexpected exception: ' . $error->getMessage();
    echo 'FAIL unexpected exception: ' . $error->getMessage() . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, 'Jy Builder Theme Template contract failed: ' . implode('; ', $failures) . PHP_EOL);
    exit(1);
}

echo "RESULT: ALL PASS\n";
