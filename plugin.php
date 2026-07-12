<?php
// /plugins/jyavani-builder/plugin.php — Jyavani Page Builder v2.0 (foundation)
declare(strict_types=1);

// Loaded on every request (admin + frontend) via plugin_load_active(). No context guard here —
// guards belong in the admin page files.

const JVB_VERSION = '2.0.0';
const JVB_LAYOUT_VERSION = 2;
const JVB_SETTINGS_TOKENS_KEY = 'jvb_design_tokens';
const JVB_MAX_REVISIONS = 20;

// Breakpoints (documented; tablet ≤ 1024px, mobile ≤ 767px)
const JVB_BP_TABLET = 1024;
const JVB_BP_MOBILE = 767;

// ---------------- Schema ----------------

function jvb_ensure_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `jvb_layouts` (
            `post_id` int(10) unsigned NOT NULL,
            `status` varchar(10) NOT NULL DEFAULT 'draft',
            `draft_json` longtext DEFAULT NULL,
            `published_json` longtext DEFAULT NULL,
            `published_at` datetime DEFAULT NULL,
            `updated_by` int(10) unsigned DEFAULT NULL,
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `jvb_revisions` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `post_id` int(10) unsigned NOT NULL,
            `layout_json` longtext DEFAULT NULL,
            `note` varchar(120) NOT NULL DEFAULT '',
            `created_by` int(10) unsigned DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `post_id` (`post_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `jvb_templates` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `title` varchar(191) NOT NULL,
            `type` varchar(20) NOT NULL DEFAULT 'section',
            `layout_json` longtext DEFAULT NULL,
            `is_starter` tinyint(1) NOT NULL DEFAULT 0,
            `created_by` int(10) unsigned DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// ---------------- Layout storage ----------------

function jvb_empty_layout(): array {
    return ['v' => JVB_LAYOUT_VERSION, 'settings' => ['custom_css' => ''], 'sections' => []];
}

function jvb_normalize_layout($raw): array {
    $layout = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($layout)) return jvb_empty_layout();
    if (!isset($layout['sections']) || !is_array($layout['sections'])) $layout['sections'] = [];
    if (!isset($layout['settings']) || !is_array($layout['settings'])) $layout['settings'] = [];
    $layout['v'] = JVB_LAYOUT_VERSION;
    return $layout;
}

// Raw layouts row for a post (static-cached per request).
function jvb_get_layout_row(PDO $pdo, int $postId): ?array {
    if (!isset($GLOBALS['_jvb_row_cache'])) $GLOBALS['_jvb_row_cache'] = [];
    $cache = &$GLOBALS['_jvb_row_cache'];
    if (array_key_exists($postId, $cache)) return $cache[$postId];
    $st = $pdo->prepare('SELECT * FROM `jvb_layouts` WHERE post_id = ? LIMIT 1');
    $st->execute([$postId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $cache[$postId] = is_array($row) ? $row : null;
    return $cache[$postId];
}

function jvb_cache_forget(int $postId): void {
    if (isset($GLOBALS['_jvb_row_cache'])) unset($GLOBALS['_jvb_row_cache'][$postId]);
}

// Decoded layout array for rendering ('published' or 'draft').
function jvb_get_layout(PDO $pdo, int $postId, string $which = 'published'): ?array {
    $row = jvb_get_layout_row($pdo, $postId);
    if ($row === null) return null;
    $raw = $which === 'draft' ? ($row['draft_json'] ?? null) : ($row['published_json'] ?? null);
    if ($raw === null || $raw === '') return null;
    $layout = jvb_normalize_layout($raw);
    return $layout['sections'] !== [] ? $layout : null;
}

function jvb_layout_status(PDO $pdo, int $postId): string {
    $row = jvb_get_layout_row($pdo, $postId);
    if ($row === null) return 'none';
    return (string)($row['status'] ?? 'draft'); // draft | published
}

function jvb_save_draft(PDO $pdo, int $postId, array $layout, ?int $uid = null): void {
    $json = json_encode(jvb_normalize_layout($layout), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare("
        INSERT INTO `jvb_layouts` (post_id, status, draft_json, updated_by) VALUES (?, 'draft', ?, ?)
        ON DUPLICATE KEY UPDATE draft_json = VALUES(draft_json), updated_by = VALUES(updated_by)
    ")->execute([$postId, $json, $uid]);
    jvb_cache_forget($postId);
}

function jvb_publish(PDO $pdo, int $postId, ?int $uid = null): bool {
    $row = jvb_get_layout_row($pdo, $postId);
    if ($row === null || empty($row['draft_json'])) return false;
    $layout = jvb_normalize_layout($row['draft_json']);
    $json = json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    jvb_add_revision($pdo, $postId, $layout, 'publish', $uid);
    $pdo->prepare("UPDATE `jvb_layouts` SET published_json = ?, status = 'published', published_at = NOW(), updated_by = ? WHERE post_id = ?")
        ->execute([$json, $uid, $postId]);
    jvb_cache_forget($postId);
    return true;
}

function jvb_unpublish(PDO $pdo, int $postId): void {
    $pdo->prepare("UPDATE `jvb_layouts` SET status = 'draft', published_json = NULL, published_at = NULL WHERE post_id = ?")->execute([$postId]);
    jvb_cache_forget($postId);
}

// ---------------- Revisions ----------------

function jvb_add_revision(PDO $pdo, int $postId, array $layout, string $note = '', ?int $uid = null): void {
    $pdo->prepare('INSERT INTO `jvb_revisions` (post_id, layout_json, note, created_by) VALUES (?, ?, ?, ?)')
        ->execute([$postId, json_encode(jvb_normalize_layout($layout), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $note, $uid]);
    // prune old revisions beyond the cap
    $pdo->prepare("
        DELETE FROM `jvb_revisions` WHERE post_id = ? AND id NOT IN (
            SELECT id FROM (SELECT id FROM `jvb_revisions` WHERE post_id = ? ORDER BY id DESC LIMIT " . JVB_MAX_REVISIONS . ") AS keep
        )
    ")->execute([$postId, $postId]);
}

function jvb_get_revisions(PDO $pdo, int $postId, int $limit = JVB_MAX_REVISIONS): array {
    $limit = max(1, min(100, $limit));
    $st = $pdo->prepare("
        SELECT r.*, u.username AS author_name FROM `jvb_revisions` r
        LEFT JOIN `users` u ON u.id = r.created_by
        WHERE r.post_id = ? ORDER BY r.id DESC LIMIT " . $limit . "
    ");
    $st->execute([$postId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function jvb_restore_revision(PDO $pdo, int $revisionId, ?int $uid = null): ?int {
    $st = $pdo->prepare('SELECT * FROM `jvb_revisions` WHERE id = ? LIMIT 1');
    $st->execute([$revisionId]);
    $rev = $st->fetch(PDO::FETCH_ASSOC);
    if (!is_array($rev)) return null;
    $postId = (int)$rev['post_id'];
    $layout = jvb_normalize_layout($rev['layout_json']);
    jvb_save_draft($pdo, $postId, $layout, $uid);
    return $postId;
}

// ---------------- Design tokens ----------------

function jvb_default_tokens(): array {
    return [
        'colors' => [
            'primary'   => '#2563eb',
            'secondary' => '#0f172a',
            'accent'    => '#f59e0b',
            'text'      => '#1e293b',
            'muted'     => '#64748b',
            'surface'   => '#ffffff',
            'alt'       => '#f1f5f9',
            'border'    => '#e2e8f0',
        ],
        'typography' => [
            'font_body'    => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
            'font_heading' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
            'base_size'    => 16,
            'scale'        => 1.25,
            'line_height'  => 1.65,
        ],
        'spacing' => [
            'container' => 1200,
            'section_y' => 80,
            'gap'       => 24,
            'radius'    => 10,
        ],
    ];
}

function jvb_get_tokens(PDO $pdo): array {
    $raw = function_exists('settings_get') ? settings_get($pdo, JVB_SETTINGS_TOKENS_KEY, '') : '';
    $saved = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    $def = jvb_default_tokens();
    if (!is_array($saved)) return $def;
    // shallow-merge per group so new keys survive upgrades
    foreach ($def as $group => $vals) {
        if (isset($saved[$group]) && is_array($saved[$group])) {
            $def[$group] = array_merge($vals, $saved[$group]);
        }
    }
    return $def;
}

function jvb_save_tokens(PDO $pdo, array $tokens): void {
    $def = jvb_default_tokens();
    $clean = $def;
    foreach ($def as $group => $vals) {
        if (isset($tokens[$group]) && is_array($tokens[$group])) {
            $clean[$group] = array_merge($vals, array_intersect_key($tokens[$group], $vals));
        }
    }
    if (function_exists('settings_set')) {
        settings_set($pdo, JVB_SETTINGS_TOKENS_KEY, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1);
    }
}

// CSS variables block for tokens (scoped to .jvb-page).
function jvb_tokens_css(array $tokens): string {
    $c = $tokens['colors'] ?? [];
    $t = $tokens['typography'] ?? [];
    $s = $tokens['spacing'] ?? [];
    $vars = [];
    foreach ($c as $k => $v) $vars[] = '--jvb-' . preg_replace('/[^a-z0-9-]/', '', $k) . ':' . $v;
    $vars[] = '--jvb-font-body:' . ($t['font_body'] ?? 'sans-serif');
    $vars[] = '--jvb-font-heading:' . ($t['font_heading'] ?? 'sans-serif');
    $vars[] = '--jvb-base-size:' . (int)($t['base_size'] ?? 16) . 'px';
    $vars[] = '--jvb-scale:' . (float)($t['scale'] ?? 1.25);
    $vars[] = '--jvb-line-height:' . (float)($t['line_height'] ?? 1.65);
    $vars[] = '--jvb-container:' . (int)($s['container'] ?? 1200) . 'px';
    $vars[] = '--jvb-section-y:' . (int)($s['section_y'] ?? 80) . 'px';
    $vars[] = '--jvb-gap:' . (int)($s['gap'] ?? 24) . 'px';
    $vars[] = '--jvb-radius:' . (int)($s['radius'] ?? 10) . 'px';
    return ':root{' . implode(';', $vars) . '}';
}

// ---------------- Templates ----------------

function jvb_list_templates(PDO $pdo, ?string $type = null): array {
    $sql = 'SELECT id, title, type, is_starter, created_at, updated_at FROM `jvb_templates`';
    $args = [];
    if ($type !== null) { $sql .= ' WHERE type = ?'; $args[] = $type; }
    $sql .= ' ORDER BY is_starter DESC, updated_at DESC';
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function jvb_get_template(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare('SELECT * FROM `jvb_templates` WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function jvb_save_template(PDO $pdo, string $title, string $type, $layout, ?int $uid = null, ?int $id = null): int {
    $json = json_encode(is_string($layout) ? json_decode($layout, true) : $layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($id !== null && $id > 0) {
        $pdo->prepare('UPDATE `jvb_templates` SET title = ?, type = ?, layout_json = ? WHERE id = ? AND is_starter = 0')
            ->execute([$title, $type, $json, $id]);
        return $id;
    }
    $pdo->prepare('INSERT INTO `jvb_templates` (title, type, layout_json, created_by) VALUES (?, ?, ?, ?)')
        ->execute([$title, $type, $json, $uid]);
    return (int)$pdo->lastInsertId();
}

function jvb_delete_template(PDO $pdo, int $id): void {
    $pdo->prepare('DELETE FROM `jvb_templates` WHERE id = ? AND is_starter = 0')->execute([$id]);
}

// ---------------- Security ----------------

// Editorial gate for AJAX: JSON 403 + exit when unauthorized.
function jvb_require_editorial(PDO $pdo): array {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        jvb_json(['success' => false, 'message' => 'Not logged in'], 401);
    }
    $role = function_exists('current_user_role') ? current_user_role($pdo) : null;
    if (!in_array($role, ['editor', 'admin'], true)) {
        jvb_json(['success' => false, 'message' => 'Insufficient permissions'], 403);
    }
    $uid = function_exists('current_user_id') ? (int)current_user_id() : 0;
    return ['uid' => $uid, 'role' => $role];
}

function jvb_json(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jvb_csrf_ok(): bool {
    if (!function_exists('csrf_check')) return true;
    $tok = (string)($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
    if ($tok === '') {
        $raw = file_get_contents('php://input');
        $data = json_decode((string)$raw, true);
        $tok = is_array($data) ? (string)($data['csrf_token'] ?? '') : '';
    }
    return csrf_check($tok);
}

// Read JSON body (AJAX calls send application/json).
function jvb_input(): array {
    $ct = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_contains($ct, 'application/json')) {
        $data = json_decode((string)file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

// ---------------- Migration from v1 (meta.builder_data) ----------------

function jvb_migrate_v1(PDO $pdo, int $postId): bool {
    $st = $pdo->prepare('SELECT meta FROM `posts` WHERE id = ? AND is_deleted = 0 LIMIT 1');
    $st->execute([$postId]);
    $raw = $st->fetchColumn();
    if (!is_string($raw) || $raw === '') return false;
    $meta = json_decode($raw, true);
    $bd = is_array($meta) ? ($meta['builder_data'] ?? null) : null;
    if (!is_array($bd) || empty($bd['rows']) || !is_array($bd['rows'])) return false;

    $sections = [];
    foreach ($bd['rows'] as $row) {
        $cols = [];
        foreach ((array)($row['columns'] ?? []) as $col) {
            $els = [];
            foreach ((array)($col['elements'] ?? []) as $el) {
                $type = (string)($el['type'] ?? 'text');
                $map = ['paragraph' => 'richtext', 'text' => 'richtext'];
                $type = $map[$type] ?? $type;
                if (in_array($type, ['css', 'script'], true)) $type = 'html'; // merge into html (admin-only)
                $els[] = [
                    'id' => 'e_' . bin2hex(random_bytes(4)),
                    'type' => $type,
                    'settings' => is_array($el['settings'] ?? null) ? $el['settings'] : [],
                ];
            }
            $cols[] = [
                'id' => 'c_' . bin2hex(random_bytes(4)),
                'settings' => ['width' => ['d' => round(((int)($col['width'] ?? 12)) / 12 * 100, 2)]],
                'elements' => $els,
            ];
        }
        $rs = is_array($row['settings'] ?? null) ? $row['settings'] : [];
        $sections[] = [
            'id' => 's_' . bin2hex(random_bytes(4)),
            'settings' => [
                'layout' => !empty($rs['full_width']) ? 'full' : 'boxed',
                'bg_type' => !empty($rs['bg_image']) ? 'image' : (!empty($rs['bg_color']) ? 'color' : 'none'),
                'bg_color' => (string)($rs['bg_color'] ?? ''),
                'bg_image' => (string)($rs['bg_image'] ?? ''),
            ],
            'columns' => $cols,
        ];
    }
    $layout = ['v' => JVB_LAYOUT_VERSION, 'settings' => ['custom_css' => ''], 'sections' => $sections];
    $uid = function_exists('current_user_id') ? (int)current_user_id() : null;
    jvb_save_draft($pdo, $postId, $layout, $uid);
    jvb_add_revision($pdo, $postId, $layout, 'migrated-from-v1', $uid);
    return true;
}

// ---------------- Render pipeline ----------------

require_once __DIR__ . '/public/render.php';
require_once __DIR__ . '/public/starters.php';

// Track whether any layout was rendered this request (for footer assets).
function jvb_mark_rendered(): void { $GLOBALS['_jvb_any_rendered'] = true; }

add_filter('post_content', function (string $html, array $post = []): string {
    $postId = (int)($post['id'] ?? 0);
    if ($postId <= 0) return $html;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return $html;

    // Draft preview: ?jvb_preview=1 for logged-in editorial users
    $which = 'published';
    if (isset($_GET['jvb_preview']) && function_exists('is_logged_in') && is_logged_in()) {
        $role = function_exists('current_user_role') ? current_user_role($pdo) : null;
        if (in_array($role, ['editor', 'admin'], true)) $which = 'draft';
    }

    $layout = jvb_get_layout($pdo, $postId, $which);
    if ($layout === null) {
        // Auto-migrate v1 layouts on first access (draft only; does not publish)
        if (jvb_get_layout_row($pdo, $postId) === null && jvb_migrate_v1($pdo, $postId)) {
            $layout = jvb_get_layout($pdo, $postId, $which);
        }
        if ($layout === null) return $html;
    }

    jvb_mark_rendered();
    $out = jvb_render_layout($pdo, $layout, $post);
    if ($which === 'draft') {
        $out = '<div class="jvb-preview-bar">⚠ Draft preview — <a href="?">exit preview</a></div>' . $out;
    }
    return $out;
}, 5);

// ---------------- Homepage support ----------------
// The homepage (context 'home') renders theme slot 'main.homepage' and has no
// $post / post_content filter. Core exposes `layout_slot_html` (app/layout.php)
// which we use to swap the slot output when a published builder layout exists
// for the designated home post (setting `jvb_home_post_id`, fallback: page slug 'home').

function jvb_home_post_id(PDO $pdo): ?int {
    $id = 0;
    if (function_exists('settings_get')) {
        $id = (int)(settings_get($pdo, 'jvb_home_post_id', '0') ?? '0');
    }
    if ($id <= 0) {
        $st = $pdo->prepare("SELECT id FROM `posts` WHERE slug = 'home' AND type = 'page' AND status = 'published' AND is_deleted = 0 ORDER BY id DESC LIMIT 1");
        $st->execute();
        $id = (int)$st->fetchColumn();
    }
    if ($id <= 0) return null;
    $st = $pdo->prepare('SELECT id FROM `posts` WHERE id = ? AND is_deleted = 0 LIMIT 1');
    $st->execute([$id]);
    return $st->fetchColumn() ? $id : null;
}

add_filter('layout_slot_html', function (string $html, string $slot = '', array $context = []): string {
    if ($slot !== 'main.homepage') return $html;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return $html;
    $postId = jvb_home_post_id($pdo);
    if ($postId === null) return $html;

    $which = 'published';
    if (isset($_GET['jvb_preview']) && function_exists('is_logged_in') && is_logged_in()) {
        $role = function_exists('current_user_role') ? current_user_role($pdo) : null;
        if (in_array($role, ['editor', 'admin'], true)) $which = 'draft';
    }

    $layout = jvb_get_layout($pdo, $postId, $which);
    if ($layout === null) return $html; // no published builder layout → theme slot

    $st = $pdo->prepare('SELECT id, title, slug, type, status, meta, thumbnail FROM `posts` WHERE id = ? LIMIT 1');
    $st->execute([$postId]);
    $post = $st->fetch(PDO::FETCH_ASSOC);
    if (!is_array($post)) return $html;

    jvb_mark_rendered();
    $out = jvb_render_layout($pdo, $layout, $post);
    if ($which === 'draft') {
        $out = '<div class="jvb-preview-bar">⚠ Draft preview — <a href="?">exit preview</a></div>' . $out;
    }
    return $out;
}, 5);

// Conditional frontend JS (animations, lightbox, countdown, tabs, accordion).
add_action('wp_footer', function (): void {
    if (empty($GLOBALS['_jvb_any_rendered'])) return;
    $base = '/static/vendor/jyavani-builder';
    echo '<script src="' . $base . '/frontend.js" defer></script>' . "\n";
});

// ---------------- Frontend AJAX route ----------------

if (function_exists('register_frontend_route')) {
    register_frontend_route('jvb-builder', __DIR__ . '/admin/ajax.php');
}

// ---------------- Lifecycle ----------------

add_action('admin_init', function (): void {
    $pdo = $GLOBALS['pdo'] ?? null;
    if ($pdo instanceof PDO) {
        jvb_ensure_schema($pdo);
        jvb_seed_starter_templates($pdo);
    }
});

add_action('plugin_uninstall', function (string $name): void {
    if ($name !== 'jyavani-builder') return;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return;
    $pdo->exec('DROP TABLE IF EXISTS `jvb_layouts`');
    $pdo->exec('DROP TABLE IF EXISTS `jvb_revisions`');
    $pdo->exec('DROP TABLE IF EXISTS `jvb_templates`');
    if (function_exists('settings_set')) settings_set($pdo, JVB_SETTINGS_TOKENS_KEY, '', 1);
});
