<?php
declare(strict_types=1);

// ── Frontend Renderer ──────────────────────────────────────────────

$GLOBALS['_jvb_rendered'] = false;

function jvb_fetch_post_meta(int $postId): ?string {
    try {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) return null;
        $stmt = $pdo->prepare("SELECT meta FROM posts WHERE id = :id AND is_deleted = 0 LIMIT 1");
        $stmt->execute([':id' => $postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ($row['meta'] ?? null) : null;
    } catch (Throwable $e) {
        error_log('[jvb] Failed to fetch meta for post ' . $postId . ': ' . $e->getMessage());
        return null;
    }
}

add_filter('post_content', function(string $content, array $post): string {
    if ($GLOBALS['_jvb_rendered']) return $content;

    $rawMeta = $post['meta'] ?? null;
    if (empty($rawMeta)) {
        $postId = (int)($post['id'] ?? 0);
        if ($postId > 0) {
            $rawMeta = jvb_fetch_post_meta($postId);
        }
    }
    if (empty($rawMeta)) return $content;

    $meta = is_string($rawMeta) ? json_decode($rawMeta, true) : $rawMeta;
    if (!is_array($meta) || empty($meta['builder_data'])) return $content;

    $builderData = $meta['builder_data'];
    if (!is_array($builderData) || empty($builderData['rows'])) return $content;

    $GLOBALS['_jvb_rendered'] = true;
    return render_builder_layout($builderData['rows']);
}, 5, 2);

function render_builder_layout(array $rows): string {
    $html = '<div class="jyavani-builder-layout">';
    foreach ($rows as $row) {
        $html .= render_builder_row($row);
    }
    $html .= '</div>';
    return $html;
}

function render_builder_row(array $row): string {
    $settings = $row['settings'] ?? [];
    $columns  = $row['columns'] ?? [];

    $cls = 'jvb-row';
    if (!empty($settings['full_width'])) $cls .= ' jvb-row-full';
    if (!empty($settings['class'])) $cls .= ' ' . htmlspecialchars($settings['class'], ENT_QUOTES, 'UTF-8');

    $style = '';
    if (!empty($settings['bg_color'])) $style .= 'background-color:' . htmlspecialchars($settings['bg_color'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['text_color'])) $style .= 'color:' . htmlspecialchars($settings['text_color'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['padding'])) $style .= 'padding:' . htmlspecialchars($settings['padding'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['margin'])) $style .= 'margin:' . htmlspecialchars($settings['margin'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['bg_image'])) $style .= 'background-image:url(' . htmlspecialchars($settings['bg_image'], ENT_QUOTES, 'UTF-8') . ');background-size:cover;background-position:center;';

    $html = '<div class="' . $cls . '"' . ($style ? ' style="' . $style . '"' : '') . '>';
    $html .= '<div class="jvb-row-inner">';
    foreach ($columns as $col) {
        $html .= render_builder_column($col);
    }
    $html .= '</div></div>';
    return $html;
}

function render_builder_column(array $col): string {
    $settings = $col['settings'] ?? [];
    $elements = $col['elements'] ?? [];
    $width    = (int)($col['width'] ?? 12);

    $cls = 'jvb-col jvb-col-' . max(1, min(12, $width));
    if (!empty($settings['class'])) $cls .= ' ' . htmlspecialchars($settings['class'], ENT_QUOTES, 'UTF-8');

    $style = '';
    if (!empty($settings['bg_color'])) $style .= 'background-color:' . htmlspecialchars($settings['bg_color'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['padding'])) $style .= 'padding:' . htmlspecialchars($settings['padding'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['text_align'])) $style .= 'text-align:' . htmlspecialchars($settings['text_align'], ENT_QUOTES, 'UTF-8') . ';';

    $html = '<div class="' . $cls . '"' . ($style ? ' style="' . $style . '"' : '') . '>';
    $html .= '<div class="jvb-col-inner">';
    foreach ($elements as $el) {
        $html .= render_builder_element($el);
    }
    $html .= '</div></div>';
    return $html;
}

function render_builder_element(array $el): string {
    $type     = $el['type'] ?? 'text';
    $settings = $el['settings'] ?? [];

    $elId   = !empty($el['id']) ? ' id="' . htmlspecialchars($el['id'], ENT_QUOTES, 'UTF-8') . '"' : '';
    $elCls  = 'jvb-el jvb-el-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
    $elCls .= !empty($settings['class']) ? ' ' . htmlspecialchars($settings['class'], ENT_QUOTES, 'UTF-8') : '';
    $elCls .= !empty($settings['align']) ? ' jvb-align-' . htmlspecialchars($settings['align'], ENT_QUOTES, 'UTF-8') : '';

    $elStyle = '';
    if (!empty($settings['text_color'])) $elStyle .= 'color:' . htmlspecialchars($settings['text_color'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['bg_color'])) $elStyle .= 'background-color:' . htmlspecialchars($settings['bg_color'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['padding'])) $elStyle .= 'padding:' . htmlspecialchars($settings['padding'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['margin'])) $elStyle .= 'margin:' . htmlspecialchars($settings['margin'], ENT_QUOTES, 'UTF-8') . ';';
    if (!empty($settings['width'])) $elStyle .= 'width:' . htmlspecialchars($settings['width'], ENT_QUOTES, 'UTF-8') . ';';

    $elAttr = $elId . ($elStyle ? ' style="' . $elStyle . '"' : '');

    switch ($type) {
        case 'heading':
            $tag = in_array($settings['tag'] ?? '', ['h1','h2','h3','h4','h5','h6'], true) ? $settings['tag'] : 'h2';
            $text = htmlspecialchars((string)($settings['text'] ?? ''), ENT_QUOTES, 'UTF-8');
            return '<' . $tag . ' class="' . $elCls . '"' . $elAttr . '>' . $text . '</' . $tag . '>';

        case 'text':
            return '<div class="' . $elCls . '"' . $elAttr . '><div class="jvb-text-content">' . (string)($settings['content'] ?? '') . '</div></div>';

        case 'image':
            $src = htmlspecialchars((string)($settings['src'] ?? ''), ENT_QUOTES, 'UTF-8');
            $alt = htmlspecialchars((string)($settings['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
            $cap = htmlspecialchars((string)($settings['caption'] ?? ''), ENT_QUOTES, 'UTF-8');
            $link = !empty($settings['link']) ? htmlspecialchars((string)$settings['link'], ENT_QUOTES, 'UTF-8') : '';
            $img = '<img src="' . $src . '" alt="' . $alt . '" loading="lazy">';
            $img = $link ? '<a href="' . $link . '">' . $img . '</a>' : $img;
            return '<div class="' . $elCls . '"' . $elAttr . '>' . $img . ($cap ? '<div class="jvb-image-caption">' . $cap . '</div>' : '') . '</div>';

        case 'button':
            $text = htmlspecialchars((string)($settings['text'] ?? 'Button'), ENT_QUOTES, 'UTF-8');
            $url  = htmlspecialchars((string)($settings['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
            $btnCls = 'jvb-btn';
            if (!empty($settings['size'])) $btnCls .= ' jvb-btn-' . htmlspecialchars($settings['size'], ENT_QUOTES, 'UTF-8');
            if (!empty($settings['style'])) $btnCls .= ' jvb-btn-' . htmlspecialchars($settings['style'], ENT_QUOTES, 'UTF-8');
            $target = !empty($settings['new_tab']) ? ' target="_blank" rel="noopener"' : '';
            return '<div class="' . $elCls . '"' . $elAttr . '><a href="' . $url . '" class="' . $btnCls . '"' . $target . '>' . $text . '</a></div>';

        case 'divider':
            $w = !empty($settings['width']) ? ' style="width:' . htmlspecialchars($settings['width'], ENT_QUOTES, 'UTF-8') . '"' : '';
            return '<div class="' . $elCls . '"' . $elAttr . '><hr class="jvb-divider"' . $w . '></div>';

        case 'spacer':
            $h = !empty($settings['height']) ? ' style="height:' . htmlspecialchars($settings['height'], ENT_QUOTES, 'UTF-8') . '"' : '';
            return '<div class="' . $elCls . '"' . $elAttr . $h . '></div>';

        case 'video':
            $src = (string)($settings['src'] ?? '');
            if (empty($src)) return '';
            $embedUrl = parse_video_url($src);
            if (!$embedUrl) return '';
            $ratio = !empty($settings['aspect_ratio']) ? ' style="aspect-ratio:' . htmlspecialchars($settings['aspect_ratio'], ENT_QUOTES, 'UTF-8') . '"' : '';
            return '<div class="' . $elCls . '"' . $elAttr . '><div class="jvb-video-embed"' . $ratio . '><iframe src="' . htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8') . '" frameborder="0" allowfullscreen></iframe></div></div>';

        case 'shortcode':
            $sc = (string)($settings['shortcode'] ?? '');
            if (empty($sc)) return '';
            return '<div class="' . $elCls . '"' . $elAttr . '>' . do_builder_shortcode($sc) . '</div>';

        case 'html':
            return '<div class="' . $elCls . '"' . $elAttr . '>' . (string)($settings['html'] ?? '') . '</div>';

        default:
            return '';
    }
}

function parse_video_url(string $url): ?string {
    $url = trim($url);
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([a-zA-Z0-9_-]+)#', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return null;
}

function do_builder_shortcode(string $raw): string {
    $raw = trim($raw);
    if (str_starts_with($raw, '[post_cat_shortcode')) {
        $attrs = post_cat__parse_attrs(substr($raw, 20, -1));
        global $pdo;
        if ($pdo instanceof PDO) {
            return post_cat_shortcode_render($pdo, $attrs);
        }
        return '';
    }
    if (str_starts_with($raw, '[widget:')) {
        global $pdo;
        if ($pdo instanceof PDO && function_exists('widget_expand_shortcodes')) {
            return widget_expand_shortcodes($raw, $pdo);
        }
        return '';
    }
    return $raw;
}

// ── Admin hooks ────────────────────────────────────────────────────

add_action('admin_init', function(): void {
    $route = $_GET['page'] ?? '';
    if ($route !== 'admin/tools/jyavani-builder') return;

    $action = $_POST['action'] ?? '';
    if ($action !== 'jyavani_builder_save') return;

    $postId = (int)($_POST['post_id'] ?? 0);
    $builderData = $_POST['builder_data'] ?? '';

    if ($postId < 1 || $builderData === '') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No DB connection']);
        exit;
    }

    $decoded = json_decode($builderData, true);
    if (!is_array($decoded)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT meta FROM posts WHERE id = :id AND is_deleted = 0 LIMIT 1");
    $stmt->execute([':id' => $postId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    $meta = json_decode((string)($row['meta'] ?? '{}'), true);
    if (!is_array($meta)) $meta = [];
    $meta['builder_data'] = $decoded;

    $updateStmt = $pdo->prepare("UPDATE posts SET meta = :meta WHERE id = :id");
    $updateStmt->execute([
        ':meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':id'   => $postId,
    ]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Layout saved']);
    exit;
});

add_action('admin_init', function(): void {
    $route = $_GET['page'] ?? '';
    if ($route !== 'admin/tools/jyavani-builder') return;

    $action = $_GET['action'] ?? '';
    if ($action !== 'jyavani_builder_load') return;

    $postId = (int)($_GET['post_id'] ?? 0);
    if ($postId < 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        exit;
    }

    global $pdo;
    if (!$pdo instanceof PDO) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No DB connection']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, title, slug, type, meta FROM posts WHERE id = :id AND is_deleted = 0 LIMIT 1");
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    $meta = json_decode((string)($post['meta'] ?? '{}'), true);
    $builderData = (is_array($meta) && isset($meta['builder_data'])) ? $meta['builder_data'] : ['rows' => []];

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'post'    => ['id' => $post['id'], 'title' => $post['title'], 'slug' => $post['slug'], 'type' => $post['type']],
        'data'    => $builderData,
    ]);
    exit;
});

// ── Frontend assets ─────────────────────────────────────────────

add_action('wp_head', function(): void {
    if (defined('DASHBOARD_CONTEXT')) return;
    echo '<link rel="stylesheet" href="/static/vendor/jyavani-builder/frontend.css">' . "\n";
});

add_action('admin_head', function(): void {
    $route = $_GET['page'] ?? '';
    if ($route !== 'admin/tools/jyavani-builder') return;
    global $pdo;
    if (!$pdo instanceof PDO) return;
    $posts = [];
    try {
        $stmt = $pdo->query("SELECT id, title, type, slug FROM posts WHERE is_deleted = 0 AND status = 'published' AND type IN ('article', 'page') ORDER BY type, title LIMIT 200");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
?>
<script>
window.JVB_POSTS = <?= json_encode($posts, JSON_UNESCAPED_UNICODE) ?>;
window.JVB_ADMIN_PATH = '<?= ADMIN_BASE_PATH ?? '' ?>';
</script>
<?php
});
