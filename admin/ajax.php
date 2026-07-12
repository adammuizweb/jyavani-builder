<?php
// /plugins/jyavani-builder/admin/ajax.php — served via frontend route /jvb-builder/
// Clean JSON API (no dashboard HTML) + iframe canvas frame renderer.
declare(strict_types=1);

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    jvb_json(['success' => false, 'message' => 'Database not available'], 500);
}
jvb_ensure_schema($pdo);

$action = (string)($_GET['action'] ?? '');

// ---------------- iframe canvas frame (GET, HTML document) ----------------
if ($action === 'frame') {
    if (!function_exists('is_logged_in') || !is_logged_in()) {
        http_response_code(401);
        exit('Not logged in');
    }
    $role = function_exists('current_user_role') ? current_user_role($pdo) : null;
    if (!in_array($role, ['editor', 'admin'], true)) {
        http_response_code(403);
        exit('Insufficient permissions');
    }
    $postId = (int)($_GET['post_id'] ?? 0);
    $device = in_array($_GET['device'] ?? '', ['desktop', 'tablet', 'mobile'], true) ? $_GET['device'] : 'desktop';

    $post = null;
    if ($postId > 0) {
        $st = $pdo->prepare('SELECT id, title, slug, type FROM `posts` WHERE id = ? AND is_deleted = 0 LIMIT 1');
        $st->execute([$postId]);
        $post = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $layout = $postId > 0 ? (jvb_get_layout($pdo, $postId, 'draft') ?? jvb_empty_layout()) : jvb_empty_layout();
    // Unsaved preview payload (posted by parent before frame reload)
    if (isset($_GET['preview_key'], $_SESSION['jvb_frame'][$_GET['preview_key']])) {
        $layout = jvb_normalize_layout($_SESSION['jvb_frame'][$_GET['preview_key']]);
        unset($_SESSION['jvb_frame'][$_GET['preview_key']]);
    }

    $html = jvb_render_layout($pdo, $layout, is_array($post) ? $post : [], ['canvas' => true]);
    $tokens = jvb_get_tokens($pdo);
    $tokensJson = json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/static/vendor/jyavani-builder/frontend.css">
<link rel="stylesheet" href="/static/vendor/jyavani-builder/frame.css">
</head>
<body class="jvb-frame jvb-frame--<?= htmlspecialchars($device, ENT_QUOTES) ?>">
<?= $html ?>
<script>window.JVB_FRAME = { postId: <?= $postId ?>, device: '<?= htmlspecialchars($device, ENT_QUOTES) ?>', tokens: <?= $tokensJson ?>, icons: <?= json_encode(jvb_ui_icons_js(['settings', 'arrow-up', 'arrow-down', 'copy', 'bookmark', 'x', 'rows-3']), JSON_UNESCAPED_SLASHES) ?> };</script>
<script src="/static/vendor/jyavani-builder/frontend.js"></script>
<script src="/static/vendor/jyavani-builder/frame.js"></script>
</body>
</html>
<?php
    exit;
}

// ---------------- JSON API (POST) ----------------

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jvb_json(['success' => false, 'message' => 'POST required'], 405);
}
$me = jvb_require_editorial($pdo);
if (!jvb_csrf_ok()) {
    jvb_json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}
$in = jvb_input();
$action = (string)($in['action'] ?? '');
$uid = $me['uid'];

// Frame preview stash: parent posts layout, gets a key, then loads frame with it (avoids long URLs)
if ($action === 'frame_stash') {
    $layout = jvb_normalize_layout($in['layout'] ?? null);
    $key = bin2hex(random_bytes(6));
    if (!isset($_SESSION['jvb_frame']) || !is_array($_SESSION['jvb_frame'])) $_SESSION['jvb_frame'] = [];
    $_SESSION['jvb_frame'][$key] = json_encode($layout);
    jvb_json(['success' => true, 'key' => $key]);
}

// Post lookup helper
$getPost = static function (int $postId) use ($pdo): ?array {
    $st = $pdo->prepare('SELECT id, title, slug, type, status FROM `posts` WHERE id = ? AND is_deleted = 0 LIMIT 1');
    $st->execute([$postId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return is_array($r) ? $r : null;
};

switch ($action) {
    case 'load': {
        $post = $getPost((int)($in['post_id'] ?? 0));
        if ($post === null) jvb_json(['success' => false, 'message' => 'Post not found'], 404);
        $row = jvb_get_layout_row($pdo, (int)$post['id']);
        $layout = jvb_empty_layout();
        $status = 'none';
        $dirty = false;
        if ($row !== null) {
            $status = (string)$row['status'];
            if (!empty($row['draft_json'])) {
                $layout = jvb_normalize_layout($row['draft_json']);
                $dirty = true;
            } elseif (!empty($row['published_json'])) {
                $layout = jvb_normalize_layout($row['published_json']);
            }
        }
        jvb_json([
            'success' => true,
            'post' => $post,
            'layout' => $layout,
            'status' => $status,
            'has_draft' => $dirty,
            'published_at' => $row['published_at'] ?? null,
            'revisions' => count(jvb_get_revisions($pdo, (int)$post['id'])),
        ]);
    }

    case 'save_draft': {
        $post = $getPost((int)($in['post_id'] ?? 0));
        if ($post === null) jvb_json(['success' => false, 'message' => 'Post not found'], 404);
        $layout = jvb_normalize_layout($in['layout'] ?? null);
        jvb_save_draft($pdo, (int)$post['id'], $layout, $uid);
        jvb_json(['success' => true, 'saved_at' => date('Y-m-d H:i:s')]);
    }

    case 'publish': {
        $post = $getPost((int)($in['post_id'] ?? 0));
        if ($post === null) jvb_json(['success' => false, 'message' => 'Post not found'], 404);
        // Publish current editor state if provided, else stored draft
        if (isset($in['layout'])) {
            jvb_save_draft($pdo, (int)$post['id'], jvb_normalize_layout($in['layout']), $uid);
        }
        if (!jvb_publish($pdo, (int)$post['id'], $uid)) {
            jvb_json(['success' => false, 'message' => 'Nothing to publish (empty draft)'], 400);
        }
        jvb_json(['success' => true, 'published_at' => date('Y-m-d H:i:s')]);
    }

    case 'unpublish': {
        $post = $getPost((int)($in['post_id'] ?? 0));
        if ($post === null) jvb_json(['success' => false, 'message' => 'Post not found'], 404);
        jvb_unpublish($pdo, (int)$post['id']);
        jvb_json(['success' => true]);
    }

    case 'discard_draft': {
        $post = $getPost((int)($in['post_id'] ?? 0));
        if ($post === null) jvb_json(['success' => false, 'message' => 'Post not found'], 404);
        $row = jvb_get_layout_row($pdo, (int)$post['id']);
        if ($row !== null && !empty($row['published_json'])) {
            $pdo->prepare('UPDATE `jvb_layouts` SET draft_json = published_json WHERE post_id = ?')->execute([(int)$post['id']]);
        }
        jvb_json(['success' => true]);
    }

    case 'revisions': {
        $post = $getPost((int)($in['post_id'] ?? 0));
        if ($post === null) jvb_json(['success' => false, 'message' => 'Post not found'], 404);
        $revs = jvb_get_revisions($pdo, (int)$post['id']);
        $out = array_map(static function ($r) {
            $layout = jvb_normalize_layout($r['layout_json']);
            $n = 0;
            foreach ($layout['sections'] as $sec) foreach ((array)($sec['columns'] ?? []) as $c) $n += count((array)($c['elements'] ?? []));
            return [
                'id' => (int)$r['id'],
                'note' => $r['note'],
                'author' => $r['author_name'] ?? '',
                'created_at' => $r['created_at'],
                'sections' => count($layout['sections']),
                'elements' => $n,
            ];
        }, $revs);
        jvb_json(['success' => true, 'revisions' => $out]);
    }

    case 'restore_revision': {
        $postId = jvb_restore_revision($pdo, (int)($in['revision_id'] ?? 0), $uid);
        if ($postId === null) jvb_json(['success' => false, 'message' => 'Revision not found'], 404);
        $layout = jvb_get_layout($pdo, $postId, 'draft') ?? jvb_empty_layout();
        jvb_json(['success' => true, 'layout' => $layout]);
    }

    case 'render': {
        // Server-render an arbitrary layout (used for canvas refresh & template preview)
        $layout = jvb_normalize_layout($in['layout'] ?? null);
        $html = jvb_render_layout($pdo, $layout, [], ['canvas' => true]);
        jvb_json(['success' => true, 'html' => $html]);
    }

    case 'elements': {
        $types = jvb_element_types();
        $out = [];
        foreach ($types as $typeKey => $def) {
            if ($me['role'] !== 'admin' && !empty($def['admin'])) continue;
            $def['type'] = $typeKey;
            $out[] = $def;
        }
        // Forms for the form picker (if Form Builder active)
        $forms = [];
        if (function_exists('fb_accessible_forms')) {
            foreach (fb_accessible_forms($pdo, "status = 'active'") as $f) {
                $forms[] = ['id' => (int)$f['id'], 'title' => $f['title'], 'slug' => $f['slug']];
            }
        }
        // Categories for posts element
        $cats = [];
        try {
            $cats = $pdo->query("SELECT slug, name FROM `categories` WHERE is_deleted = 0 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {}
        // Icon SVG bodies for palette + icon picker
        $iconSvgs = [];
        foreach (jvb_available_icons() as $iconName) {
            $iconSvgs[$iconName] = jvb_lucide($iconName, 20);
        }
        jvb_json([
            'success' => true,
            'elements' => $out,
            'icons' => jvb_available_icons(),
            'icon_svgs' => $iconSvgs,
            'ui_icons' => jvb_ui_icons_js(['x', 'trash-2', 'copy', 'pencil', 'plus', 'grip-vertical', 'chevron-left', 'chevron-right', 'rows-3']),
            'tokens' => jvb_get_tokens($pdo),
            'forms' => $forms,
            'categories' => $cats,
            'role' => $me['role'],
        ]);
    }

    case 'tokens_save': {
        if ($me['role'] !== 'admin') jvb_json(['success' => false, 'message' => 'Admin only'], 403);
        $tokens = $in['tokens'] ?? null;
        if (!is_array($tokens)) jvb_json(['success' => false, 'message' => 'Invalid tokens'], 400);
        jvb_save_tokens($pdo, $tokens);
        jvb_json(['success' => true, 'tokens' => jvb_get_tokens($pdo)]);
    }

    case 'templates_list': {
        jvb_json(['success' => true, 'templates' => jvb_list_templates($pdo)]);
    }

    case 'template_get': {
        $tpl = jvb_get_template($pdo, (int)($in['template_id'] ?? 0));
        if ($tpl === null) jvb_json(['success' => false, 'message' => 'Template not found'], 404);
        jvb_json(['success' => true, 'template' => [
            'id' => (int)$tpl['id'],
            'title' => $tpl['title'],
            'type' => $tpl['type'],
            'layout' => json_decode((string)$tpl['layout_json'], true),
        ]]);
    }

    case 'template_save': {
        $title = trim((string)($in['title'] ?? ''));
        $type = in_array($in['type'] ?? '', ['section', 'page'], true) ? $in['type'] : 'section';
        $layout = $in['layout'] ?? null;
        if ($title === '' || !is_array($layout)) jvb_json(['success' => false, 'message' => 'Title and layout required'], 400);
        $id = jvb_save_template($pdo, $title, $type, $layout, $uid, isset($in['template_id']) ? (int)$in['template_id'] : null);
        jvb_json(['success' => true, 'template_id' => $id]);
    }

    case 'template_delete': {
        jvb_delete_template($pdo, (int)($in['template_id'] ?? 0));
        jvb_json(['success' => true]);
    }

    default:
        jvb_json(['success' => false, 'message' => 'Unknown action'], 400);
}
