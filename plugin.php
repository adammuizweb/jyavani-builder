<?php
// /plugins/jyavani-builder/plugin.php — Jy Builder v3.2.3
declare(strict_types=1);

// Loaded on every request (admin + frontend) via plugin_load_active(). No context guard here —
// guards belong in the admin page files.

const JVB_VERSION = '3.2.3';
const JVB_LAYOUT_VERSION = 2;
const JVB_SETTINGS_TOKENS_KEY = 'jvb_design_tokens';
const JVB_DYNAMIC_ACCESS_MIGRATED_KEY = 'jvb_dynamic_access_migrated';
const JVB_MAX_REVISIONS = 20;

// Breakpoints (documented; tablet ≤ 1024px, mobile ≤ 767px)
const JVB_BP_TABLET = 1024;
const JVB_BP_MOBILE = 767;

// ---------------- Schema ----------------

function jvb_ensure_schema(PDO $pdo): void {
    try {
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
    } catch (Throwable $e) {
        error_log('[jyavani-builder] Could not initialize schema: ' . $e->getMessage());
    }
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
    // v2 → v3 migration: section.columns → section.rows[0].cols
    foreach ($layout['sections'] as &$sec) {
        if (!isset($sec['rows']) && isset($sec['columns']) && is_array($sec['columns'])) {
            $sec['rows'] = [['id' => jvb_uid('r'), 'settings' => ['gap' => 20], 'cols' => $sec['columns']]];
            unset($sec['columns']);
        }
    }
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

// ---------------- HTML → Layout import (Architecture B) ----------------

function jvb_html_is_complex(string $html): bool {
    return (bool)preg_match('/<(script|style|iframe|embed|object|form|svg|canvas|php|link|meta|table|thead|tbody|tfoot|tr|th|td)[\s>]|on[a-z]+\s*=|style\s*=/i', $html);
}

function jvb_html_node_to_element(DOMNode $node, DOMDocument $doc): ?array {
    if ($node->nodeType === XML_TEXT_NODE) {
        $text = trim($node->textContent);
        if ($text === '') return null;
        return ['id' => jvb_uid('e'), 'type' => 'richtext', 'settings' => ['content' => '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>']];
    }
    if ($node->nodeType !== XML_ELEMENT_NODE) return null;

    $tag = strtolower($node->nodeName);
    $html = $doc->saveHTML($node);

    switch ($tag) {
        case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
            return ['id' => jvb_uid('e'), 'type' => 'heading', 'settings' => ['text' => trim($node->textContent), 'tag' => $tag]];

        case 'p': case 'ul': case 'ol': case 'blockquote':
            return ['id' => jvb_uid('e'), 'type' => 'richtext', 'settings' => ['content' => $html]];

        case 'img':
            $src = $node->getAttribute('src');
            if (!$src) return null;
            return ['id' => jvb_uid('e'), 'type' => 'image', 'settings' => ['src' => $src, 'alt' => $node->getAttribute('alt') ?: '']];

        case 'figure':
            $imgs = $node->getElementsByTagName('img');
            if ($imgs->length > 0) {
                $img = $imgs->item(0);
                $caps = $node->getElementsByTagName('figcaption');
                return ['id' => jvb_uid('e'), 'type' => 'image', 'settings' => [
                    'src' => $img->getAttribute('src'),
                    'alt' => $img->getAttribute('alt') ?: '',
                    'caption' => $caps->length > 0 ? trim($caps->item(0)->textContent) : '',
                ]];
            }
            return ['id' => jvb_uid('e'), 'type' => 'html', 'settings' => ['html' => $html]];

        case 'hr':
            return ['id' => jvb_uid('e'), 'type' => 'divider', 'settings' => []];

        case 'table': case 'iframe': case 'pre':
            return ['id' => jvb_uid('e'), 'type' => 'html', 'settings' => ['html' => $html]];

        case 'div': case 'section': case 'article': case 'aside':
        case 'main': case 'nav': case 'header': case 'footer':
            if (jvb_html_is_complex($html)) {
                return ['id' => jvb_uid('e'), 'type' => 'html', 'settings' => ['html' => $html]];
            }
            return ['id' => jvb_uid('e'), 'type' => 'richtext', 'settings' => ['content' => $html]];

        default:
            if (jvb_html_is_complex($html)) {
                return ['id' => jvb_uid('e'), 'type' => 'html', 'settings' => ['html' => $html]];
            }
            return ['id' => jvb_uid('e'), 'type' => 'richtext', 'settings' => ['content' => $html]];
    }
}

function jvb_html_to_layout(string $html): array {
    $layout = jvb_empty_layout();
    $html = trim($html);
    if ($html === '') return $layout;

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="jvb-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('jvb-root');
    if (!$root) return $layout;

    $elements = [];
    foreach ($root->childNodes as $node) {
        $el = jvb_html_node_to_element($node, $doc);
        if ($el !== null) $elements[] = $el;
    }
    if (empty($elements)) return $layout;

    // Each element gets its own row (1 col, 100%) so user can
    // add columns to any row for multi-column layouts.
    $rows = [];
    foreach ($elements as $el) {
        $rows[] = [
            'id' => jvb_uid('r'),
            'settings' => ['gap' => 20],
            'cols' => [[
                'id' => jvb_uid('c'),
                'settings' => ['width' => ['d' => 100]],
                'elements' => [$el],
            ]],
        ];
    }

    $layout['sections'][] = [
        'id' => jvb_uid('s'),
        'settings' => [],
        'rows' => $rows,
    ];
    return $layout;
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
    $sql = 'SELECT id, title, type, is_starter, created_by, created_at, updated_at FROM `jvb_templates`';
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
    $uid = function_exists('current_user_id') ? (int)current_user_id() : 0;
    if ($uid <= 0 || !function_exists('user_can') || !user_can($pdo, $uid, 'plugin.jyavani-builder.workspace.access')) {
        jvb_json(['success' => false, 'message' => 'Insufficient permissions'], 403);
    }
    $role = function_exists('current_user_role') ? current_user_role($pdo) : null;
    $manageAny = user_can($pdo, $uid, 'plugin.jyavani-builder.content.manage-any');
    return ['uid' => $uid, 'role' => $role, 'manage_any' => $manageAny];
}

function jvb_can_preview_draft(PDO $pdo, int $postId): bool {
    $uid = function_exists('current_user_id') ? (int)current_user_id() : (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0 || !function_exists('user_can') || !user_can($pdo, $uid, 'plugin.jyavani-builder.workspace.access')) return false;
    $st = $pdo->prepare('SELECT type, created_by FROM posts WHERE id = ? AND is_deleted = 0 LIMIT 1');
    $st->execute([$postId]);
    $post = $st->fetch(PDO::FETCH_ASSOC);
    if (!is_array($post) || !jvb_user_can_content_action($pdo, $uid, $post, 'update')) return false;
    return user_can($pdo, $uid, 'plugin.jyavani-builder.content.manage-any')
        || (int)$post['created_by'] === $uid;
}

function jvb_layout_has_restricted_elements(array $layout): bool {
    $restricted = [];
    foreach (jvb_element_types() as $type => $definition) {
        if (!empty($definition['admin'])) $restricted[(string)$type] = true;
    }
    $walk = static function (array $value) use (&$walk, $restricted): bool {
        if (isset($value['type']) && is_string($value['type']) && isset($restricted[$value['type']])) return true;
        foreach ($value as $child) {
            if (is_array($child) && $walk($child)) return true;
        }
        return false;
    };
    return $walk($layout);
}

function jvb_user_can_content_action(PDO $pdo, int $uid, array $post, string $action): bool {
    if ($uid <= 0) return false;
    $type = (string)($post['type'] ?? '');
    if ($type === 'theme') {
        $actor = function_exists('authorization_actor') ? authorization_actor($pdo, $uid) : null;
        return $actor !== null && $actor['is_site_owner'] === true;
    }
    $resource = $type === 'page' ? 'pages' : ($type === 'article' ? 'posts' : '');
    if ($resource === '' || !function_exists('user_can')) return false;
    return user_can($pdo, $uid, 'core.' . $resource . '.' . $action, ['owner_id' => (int)($post['created_by'] ?? $uid)]);
}

function jvb_require_content_action(PDO $pdo, int $uid, array $post, string $action): void {
    if (!jvb_user_can_content_action($pdo, $uid, $post, $action)) {
        jvb_json(['success' => false, 'message' => 'Core content permission denied'], 403);
    }
}

function jvb_user_can_restricted_elements(PDO $pdo, int $uid, ?array $post = null): bool {
    if ($uid <= 0 || !function_exists('user_can')) return false;
    if ($post !== null) {
        $type = (string)($post['type'] ?? '');
        if ($type === 'page') return user_can($pdo, $uid, 'core.pages.unfiltered_html', ['owner_id' => (int)($post['created_by'] ?? $uid)]);
        if ($type === 'article') return user_can($pdo, $uid, 'core.posts.unfiltered_html', ['owner_id' => (int)($post['created_by'] ?? $uid)]);
    }
    if (user_can($pdo, $uid, 'core.pages.unfiltered_html') || user_can($pdo, $uid, 'core.posts.unfiltered_html')) return true;
    $actor = function_exists('authorization_actor') ? authorization_actor($pdo, $uid) : null;
    return $actor !== null && $actor['is_site_owner'] === true;
}

function jvb_current_user_can_edit_post(PDO $pdo, array $post): bool {
    $uid = function_exists('current_user_id') ? (int)current_user_id() : (int)($_SESSION['user_id'] ?? 0);
    return $uid > 0 && function_exists('user_can')
        && user_can($pdo, $uid, 'plugin.jyavani-builder.workspace.access')
        && jvb_user_can_content_action($pdo, $uid, $post, 'update');
}

function jvb_migrate_legacy_permissions(PDO $pdo): void {
    if (!function_exists('settings_get') || !function_exists('settings_set')
        || settings_get($pdo, JVB_DYNAMIC_ACCESS_MIGRATED_KEY, '0') === '1') return;
    $permissions = [
        'plugin.jyavani-builder.content.manage-any',
        'plugin.jyavani-builder.site-settings.manage',
    ];
    $placeholders = implode(',', array_fill(0, count($permissions), '?'));
    $check = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE permission_key IN ($placeholders) AND is_active = 1");
    $check->execute($permissions);
    if ((int)$check->fetchColumn() !== count($permissions)) return;

    $started = !$pdo->inTransaction();
    try {
        if ($started) $pdo->beginTransaction();
        $role = $pdo->query("SELECT id FROM roles WHERE slug = 'admin' AND is_system = 1 LIMIT 1");
        $roleId = $role !== false ? (int)$role->fetchColumn() : 0;
        if ($roleId <= 0) throw new RuntimeException('Administrator compatibility role not found');
        $grant = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_key, scope) VALUES (?, ?, 'global') ON DUPLICATE KEY UPDATE scope = VALUES(scope)");
        foreach ($permissions as $permission) $grant->execute([$roleId, $permission]);
        settings_set($pdo, JVB_DYNAMIC_ACCESS_MIGRATED_KEY, '1', 1);
        if ($started) $pdo->commit();
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[jyavani-builder] Legacy permission migration failed: ' . $e->getMessage());
    }
}

function jvb_json(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jvb_csrf_ok(): bool {
    if (!function_exists('csrf_check')) return false;
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

// Read a raw SVG string from the CMS core icon set (public/static/icons/lucide/).
// Returns a clean <svg> element with class="jvb-ic", stripped of width/height
// so CSS sizing rules apply.
function jvb_icon_svg(string $name): string {
    static $cache = [];
    $name = preg_replace('/[^a-z0-9-]/', '', $name);
    if (isset($cache[$name])) return $cache[$name];
    $dir = defined('PUBLIC_PATH')
        ? PUBLIC_PATH . '/static/icons/lucide'
        : (realpath(__DIR__ . '/../../public_html/static/icons/lucide')
            ?: (realpath(__DIR__ . '/../../public/static/icons/lucide') ?: ''));
    if (!$dir) { $cache[$name] = ''; return ''; }
    $path = $dir . '/' . $name . '.svg';
    if (!is_file($path)) { $cache[$name] = ''; return ''; }
    $svg = (string)file_get_contents($path);
    $svg = (string)preg_replace('/<!--.*?-->/s', '', $svg);
    $svg = trim($svg);
    $svg = (string)preg_replace_callback('/^(<svg[^>]*>)/s', function ($m) {
        return preg_replace('/\s(width|height)="[^"]*"/', '', $m[1]);
    }, $svg, 1);
    $svg = (string)preg_replace('/\sclass="[^"]*"/', '', $svg, 1);
    $svg = str_replace('<svg', '<svg class="jvb-ic"', $svg);
    $cache[$name] = trim($svg);
    return $cache[$name];
}

// Keep copied static assets on the same cache key as the plugin release.
function jvb_asset_url(string $file): string {
    return '/static/plugins/jyavani-builder/' . $file . '?v=' . JVB_VERSION;
}

// Icon map for JS chrome (builder + frame), keyed by name → inline SVG.
function jvb_ui_icons_js(array $names): array {
    $out = [];
    foreach ($names as $n) $out[$n] = jvb_icon_svg($n);
    return $out;
}

add_filter('post_content', function (string $html, array $post = []): string {
    $postId = (int)($post['id'] ?? 0);
    if ($postId <= 0) return $html;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return $html;

    // Draft preview: ?jvb_preview=1 for logged-in editorial users
    $which = 'published';
    if (isset($_GET['jvb_preview']) && jvb_can_preview_draft($pdo, $postId)) $which = 'draft';

    $layout = jvb_get_layout($pdo, $postId, $which);
    if ($layout === null) return $html;

    jvb_mark_rendered();
    $out = jvb_render_layout($pdo, $layout, $post);
    if ($which === 'draft') {
        $out = '<div class="jvb-preview-bar">' . jvb_icon_svg('alert-triangle') . ' Draft preview — <a href="?">exit preview</a></div>' . $out;
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
    if (isset($_GET['jvb_preview']) && jvb_can_preview_draft($pdo, $postId)) $which = 'draft';

    $layout = jvb_get_layout($pdo, $postId, $which);
    if ($layout === null) return $html; // no published builder layout → theme slot

    $st = $pdo->prepare('SELECT id, title, slug, type, status, meta, thumbnail FROM `posts` WHERE id = ? LIMIT 1');
    $st->execute([$postId]);
    $post = $st->fetch(PDO::FETCH_ASSOC);
    if (!is_array($post)) return $html;

    jvb_mark_rendered();
    $out = jvb_render_layout($pdo, $layout, $post);
    if ($which === 'draft') {
        $out = '<div class="jvb-preview-bar">' . jvb_icon_svg('alert-triangle') . ' Draft preview — <a href="?">exit preview</a></div>' . $out;
    }
    return $out;
}, 5);

// Conditional frontend CSS (grid, sections, elements, tokens). wp_head fires
// BEFORE the main slot renders, so jvb_mark_rendered() is not set yet — we must
// pre-detect whether this request will render a builder layout:
//  1) homepage with a designated builder home post (or slug 'home')
//  2) singular published post/page having a published builder layout
//  3) draft preview mode (?jvb_preview=1)
add_action('jy_head', function (): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return;

    $need = false;
    $path = trim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'), '/');
    if ($path === '') {
        $need = jvb_home_post_id($pdo) !== null;
    } else {
        $slug = basename($path);
        try {
            $st = $pdo->prepare("SELECT p.id FROM `posts` p JOIN `jvb_layouts` l ON l.post_id = p.id WHERE p.slug = ? AND p.is_deleted = 0 AND p.status = 'published' AND l.published_json IS NOT NULL AND l.published_json != '' LIMIT 1");
            $st->execute([$slug]);
            $need = (bool)$st->fetchColumn();
        } catch (Throwable $e) {}
    }
    if (!$need && isset($_GET['jvb_preview'])) $need = true;
    if (!$need) return;
    echo '<link rel="stylesheet" href="' . jvb_asset_url('frontend.css') . '">' . "\n";
});

// Conditional frontend JS (animations, lightbox, countdown, tabs, accordion).
add_action('jy_footer', function (): void {
    if (empty($GLOBALS['_jvb_any_rendered'])) return;
    echo '<script src="' . jvb_asset_url('frontend.js') . '" defer></script>' . "\n";
});

// ---------------- Frontend AJAX route ----------------

if (function_exists('register_frontend_route')) {
    register_frontend_route('jvb-builder', __DIR__ . '/admin/ajax.php');
}

// ---------------- Lifecycle ----------------

add_action('admin_init', function (): void {
    // Schema and starter templates are only needed by Builder screens. Running
    // them for every dashboard AJAX request can break unrelated integrations.
    $page = (string)($_GET['page'] ?? '');
    if ($page !== 'admin/tools/jyavani-builder' && !str_starts_with($page, 'admin/tools/jyavani-builder/')) {
        return;
    }
    $pdo = $GLOBALS['pdo'] ?? null;
    if ($pdo instanceof PDO) {
        $uid = function_exists('current_user_id') ? (int)current_user_id() : 0;
        if ($uid <= 0 || !function_exists('user_can') || !user_can($pdo, $uid, 'plugin.jyavani-builder.workspace.access')) return;
        jvb_migrate_legacy_permissions($pdo);
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
    if (function_exists('settings_set')) {
        settings_set($pdo, JVB_SETTINGS_TOKENS_KEY, '', 1);
        settings_set($pdo, JVB_DYNAMIC_ACCESS_MIGRATED_KEY, '0', 1);
    }
});

// ---------------- CMS Core Hooks (editor integration) ----------------

// Add Builder radio option to post/page edit forms
add_filter('editor_mode_options', function (array $modes, array $post): array {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO) || !jvb_current_user_can_edit_post($pdo, $post)) return $modes;
    $modes['builder'] = 'Builder (visual)';
    return $modes;
}, 10, 2);

// Render builder area after Quill/CodeMirror areas
add_action('editor_mode_after_areas', function (array $post, string $chosenMode): void {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO) || !jvb_current_user_can_edit_post($pdo, $post)) return;
    $postId = (int)($post['id'] ?? 0);
    $hasBuilderLayout = false;
    if ($pdo instanceof PDO && $postId > 0) {
        try {
            $st = $pdo->prepare('SELECT status FROM jvb_layouts WHERE post_id = ? LIMIT 1');
            $st->execute([$postId]);
            $hasBuilderLayout = (bool)$st->fetchColumn();
        } catch (Throwable $e) {}
    }
    $show = ($chosenMode === 'builder' || ($hasBuilderLayout && $chosenMode !== 'quill' && $chosenMode !== 'codemirror'));
    $adminBase = defined('ADMIN_BASE_PATH') ? ADMIN_BASE_PATH : '';
    $builderUrl = $adminBase . '/?page=admin/tools/jyavani-builder&view=builder&post_id=' . $postId;
    $postType = $post['type'] ?? 'post';
    ?>
    <div id="builder-area" style="margin-top:.6rem;display:<?= $show ? 'block' : 'none' ?>;">
      <div style="padding:24px;text-align:center;border:2px dashed #cbd5e1;border-radius:8px;background:#f8fafc;">
        <?php if ($hasBuilderLayout): ?>
        <p style="margin:0 0 12px;color:#2563eb;font-weight:600">This <?= htmlspecialchars($postType) ?> is edited with Jy Builder</p>
        <?php else: ?>
        <p style="margin:0 0 12px;color:#64748b">Open the visual builder to edit this <?= htmlspecialchars($postType) ?>'s layout</p>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($builderUrl, ENT_QUOTES) ?>" target="_blank"
           style="display:inline-block;padding:8px 20px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px">
          Open in Builder
        </a>
        <p style="margin:12px 0 0;font-size:12px;color:#94a3b8">The builder opens in a new tab. Changes are auto-saved as draft.</p>
      </div>
    </div>
    <?php
}, 10, 2);

// Add jvb_status column to post/page list queries
add_filter('post_list_select', function (string $select, string $where): string {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return $select;
    $uid = function_exists('current_user_id') ? (int)current_user_id() : 0;
    if ($uid <= 0 || !function_exists('user_can') || !user_can($pdo, $uid, 'plugin.jyavani-builder.workspace.access')) return $select;
    try { $pdo->query('SELECT 1 FROM jvb_layouts LIMIT 1'); } catch (Throwable $e) { return $select; }
    return $select . ', jvb.status AS jvb_status';
}, 10, 2);

// Add LEFT JOIN jvb_layouts to post/page list queries
add_filter('post_list_join', function (string $join, string $where): string {
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!($pdo instanceof PDO)) return $join;
    $uid = function_exists('current_user_id') ? (int)current_user_id() : 0;
    if ($uid <= 0 || !function_exists('user_can') || !user_can($pdo, $uid, 'plugin.jyavani-builder.workspace.access')) return $join;
    try { $pdo->query('SELECT 1 FROM jvb_layouts LIMIT 1'); } catch (Throwable $e) { return $join; }
    return $join . ' LEFT JOIN jvb_layouts jvb ON jvb.post_id = p.id';
}, 10, 2);

// Add BUILDER badge after post title in list pages
add_filter('post_list_title_after', function (string $html, array $post): string {
    if (!empty($post['jvb_status'])) {
        $html .= ' <span style="display:inline-block;padding:1px 6px;background:#dbeafe;color:#2563eb;border-radius:4px;font-size:10px;font-weight:700;letter-spacing:.03em;vertical-align:middle;margin-left:4px">BUILDER</span>';
    }
    return $html;
}, 10, 2);
