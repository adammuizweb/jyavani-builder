<?php
// /plugins/jyavani-builder/admin/index.php — Jy Builder admin (list + dispatch)
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;

require_once __DIR__ . '/_ui.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) { echo '<p>Database not available.</p>'; return; }

jvb_ensure_schema($pdo);

$uid = function_exists('current_user_id') ? (int)current_user_id() : 0;
$role = function_exists('current_user_role') ? current_user_role($pdo) : null;
if ($uid <= 0) { echo '<div class="jvba-empty">Please log in.</div>'; return; }
if (!in_array($role, ['author', 'editor', 'admin'], true)) { echo '<div class="jvba-empty">Author, editor or admin role required.</div>'; return; }

$csrf = function_exists('csrf_token') ? csrf_token() : '';

// Homepage designation (POST, CSRF-checked, re-renders list — no redirect needed)
$homeMsg = '';
$homeForced = null; // same-request override: settings_get() is statically cached
$act = (string)($_POST['jvb_action'] ?? '');
if ($act !== '') {
    $okCsrf = function_exists('csrf_check') ? csrf_check((string)($_POST['csrf_token'] ?? '')) : true;
    if (!$okCsrf) {
        $homeMsg = 'CSRF check failed.';
    } elseif ($act === 'duplicate') {
        // Duplicate post + its builder layout (own posts only for non-admins)
        $pid = (int)($_POST['post_id'] ?? 0);
        $st = $pdo->prepare("SELECT id, title, slug, type, content, created_by FROM `posts` WHERE id = ? AND is_deleted = 0 LIMIT 1");
        $st->execute([$pid]);
        $src = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($src)) {
            $homeMsg = 'Source post not found.';
        } elseif ($role !== 'admin' && (int)($src['created_by'] ?? 0) !== $uid) {
            $homeMsg = 'Access denied: you can only duplicate your own posts.';
        } else {
            $newTitle = $src['title'] . ' (Copy)';
            $baseSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $src['slug'] . '-copy'), '-')) ?: 'copy';
            $slug = $baseSlug; $si = 1;
            while (true) {
                $chk = $pdo->prepare('SELECT id FROM posts WHERE slug = ? AND is_deleted = 0 LIMIT 1');
                $chk->execute([$slug]);
                if (!$chk->fetchColumn()) break;
                $slug = $baseSlug . '-' . (++$si);
            }
            $pdo->prepare('INSERT INTO posts (title, slug, type, status, content, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$newTitle, $slug, $src['type'], 'draft', $src['content'], $uid]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO jvb_layouts (post_id, status, draft_json, published_json, published_at)
                           SELECT ?, status, draft_json, published_json, published_at FROM jvb_layouts WHERE post_id = ?')
                ->execute([$newId, $pid]);
            $homeMsg = 'Duplicated as "' . $newTitle . '" (draft).';
        }
    } elseif ($role !== 'admin') {
        // Homepage designation is a site-wide setting — admin only
        $homeMsg = 'Only admins can change the homepage designation.';
    } elseif (function_exists('settings_set')) {
        if ($act === 'set_home') {
            $pid = (int)($_POST['post_id'] ?? 0);
            $st = $pdo->prepare("SELECT id FROM `posts` WHERE id = ? AND type IN ('page','article') AND is_deleted = 0 LIMIT 1");
            $st->execute([$pid]);
            if ($st->fetchColumn()) {
                settings_set($pdo, 'jvb_home_post_id', (string)$pid, 1);
                $homeForced = $pid;
                $homeMsg = 'Homepage set to post #' . $pid . '. Publish its builder layout to make it live.';
            }
        } elseif ($act === 'unset_home') {
            settings_set($pdo, 'jvb_home_post_id', '0', 1);
            $homeForced = 0;
            $homeMsg = 'Homepage designation cleared.';
        }
    }
}

$view = (string)($_GET['view'] ?? 'list');

if ($view === 'builder') {
    $postId = (int)($_GET['post_id'] ?? 0);
    $post = ['id' => 0, 'title' => 'New', 'slug' => '', 'type' => 'theme', 'status' => 'draft'];
    if ($postId > 0) {
        $st = $pdo->prepare('SELECT id, title, slug, type, status, created_by FROM `posts` WHERE id = ? AND is_deleted = 0 LIMIT 1');
        $st->execute([$postId]);
        $fetched = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($fetched)) {
            jvb_admin_css();
            echo '<div class="jvba"><div class="jvba-empty">Post not found. <a href="' . jvb_url() . '">Back to pages</a></div></div>';
            return;
        }
        // Ownership check (CMS core rule: author/editor only own posts)
        if ($role !== 'admin' && (int)($fetched['created_by'] ?? 0) !== $uid) {
            jvb_admin_css();
            echo '<div class="jvba"><div class="jvba-empty">Access denied: you can only edit your own posts. <a href="' . jvb_url() . '">Back to pages</a></div></div>';
            return;
        }
        $post = $fetched;
    }
    require __DIR__ . '/builder.php';
    return;
}

if ($view === 'tokens') {
    require __DIR__ . '/tokens.php';
    return;
}

if ($view === 'templates') {
    require __DIR__ . '/templates.php';
    return;
}

// ---------------- Pages list ----------------

jvb_admin_css();

$q = trim((string)($_GET['q'] ?? ''));
$typeFilter = in_array($_GET['type'] ?? '', ['page', 'article'], true) ? $_GET['type'] : '';

$where = ["p.is_deleted = 0", "p.type IN ('page','article','theme')", "p.status != 'private'"];
$args = [];
// CMS core rule: author/editor only see their own posts
if ($role !== 'admin') { $where[] = 'p.created_by = ?'; $args[] = $uid; }
if ($typeFilter !== '') { $where[] = 'p.type = ?'; $args[] = $typeFilter; }
if ($q !== '') { $where[] = '(p.title LIKE ? OR p.slug LIKE ?)'; $args[] = '%' . $q . '%'; $args[] = '%' . $q . '%'; }

$sql = "
    SELECT p.id, p.title, p.slug, p.type, p.status, p.updated_at,
           l.status AS jvb_status, l.published_at AS jvb_published_at,
           (l.draft_json IS NOT NULL) AS jvb_has_draft
    FROM `posts` p
    LEFT JOIN `jvb_layouts` l ON l.post_id = p.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY (l.post_id IS NOT NULL) DESC, p.updated_at DESC
    LIMIT 200
";
$st = $pdo->prepare($sql);
$st->execute($args);
$posts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$builderCount = 0;
foreach ($posts as $p) { if ($p['jvb_status'] !== null) $builderCount++; }
$homePostId = $homeForced !== null ? ($homeForced > 0 ? $homeForced : null) : jvb_home_post_id($pdo);
?>
<div class="jvba">
  <?php if ($homeMsg !== ''): ?><div class="jvba-card" style="margin-bottom:.75rem"><?= htmlspecialchars($homeMsg, ENT_QUOTES) ?></div><?php endif; ?>
  <div class="jvba-head">
    <h1>Jy Builder</h1>
    <div class="jvba-actions">
      <a class="jvba-btn primary" href="<?= jvb_url(['view' => 'builder']) ?>"><?= svg_ico('plus', 'jvb-ic', ['style' => 'width:13px;height:13px']) ?> New</a>
      <a class="jvba-btn" href="<?= jvb_url(['view' => 'templates']) ?>">Templates</a>
      <?php if ($role === 'admin'): ?>
      <a class="jvba-btn" href="<?= jvb_url(['view' => 'tokens']) ?>">Design Tokens</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="jvba-toolbar">
    <form method="get" class="jvba-search">
      <input type="hidden" name="page" value="admin/tools/jyavani-builder">
      <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" placeholder="Search pages…">
      <?php if ($typeFilter !== ''): ?><input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter, ENT_QUOTES) ?>"><?php endif; ?>
      <button class="jvba-btn sm" type="submit">Search</button>
      <?php if ($q !== '' || $typeFilter !== ''): ?><a class="jvba-btn sm" href="<?= jvb_url() ?>">Reset</a><?php endif; ?>
    </form>
    <div class="jvba-actions">
      <a class="jvba-btn sm" href="<?= jvb_url(['type' => $typeFilter === 'page' ? null : 'page', 'q' => $q ?: null]) ?>">Pages</a>
      <a class="jvba-btn sm" href="<?= jvb_url(['type' => $typeFilter === 'article' ? null : 'article', 'q' => $q ?: null]) ?>">Articles</a>
      <span class="jvba-hint"><?= count($posts) ?> posts · <?= $builderCount ?> with builder</span>
    </div>
  </div>

  <?php if (!$posts): ?>
    <div class="jvba-empty">No posts found.</div>
  <?php else: ?>
  <div class="jvba-table-wrap">
    <table class="jvba-table">
      <thead><tr><th>Post</th><th>Type</th><th>Post Status</th><th>Builder</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($posts as $p):
        $pid = (int)$p['id'];
        $jvbStatus = $p['jvb_status'] ?? 'none';
        $badgeCls = $jvbStatus === 'published' ? 'published' : ($jvbStatus === 'draft' ? 'draft' : 'none');
        $badgeLbl = $jvbStatus === 'published' ? 'Published' : ($jvbStatus === 'draft' ? 'Draft' : '—');
        if ($jvbStatus === 'published' && !empty($p['jvb_has_draft'])) {
            // draft differs from published? (we store draft copy on publish, so presence of draft_json ≠ dirty;
            // treat any draft_json newer than published_at as dirty — simplified: show "Published")
        }
        ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($p['title'], ENT_QUOTES) ?></strong>
            <span class="jvba-sub jvba-mono">/<?= htmlspecialchars($p['slug'], ENT_QUOTES) ?>/</span>
          </td>
          <td><?= htmlspecialchars($p['type'], ENT_QUOTES) ?></td>
          <td><span class="jvba-sub"><?= htmlspecialchars($p['status'], ENT_QUOTES) ?></span></td>
          <td><span class="jvba-badge <?= $badgeCls ?>"><?= $badgeLbl ?></span><?php if ($homePostId === $pid): ?> <span class="jvba-badge published" title="This post provides the homepage layout"><?= svg_ico('house', 'jvb-ic', ['style' => 'width:12px;height:12px']) ?> Home</span><?php endif; ?></td>
          <td class="jvba-sub" style="white-space:nowrap"><?= htmlspecialchars(date('d M Y', strtotime((string)$p['updated_at'])), ENT_QUOTES) ?></td>
          <td style="white-space:nowrap">
            <a class="jvba-btn sm primary" href="<?= jvb_url(['view' => 'builder', 'post_id' => $pid]) ?>"><?= svg_ico('zap', 'jvb-ic', ['style' => 'width:13px;height:13px']) ?> Builder</a>
            <a class="jvba-btn sm" href="/<?= htmlspecialchars($p['slug'], ENT_QUOTES) ?>/" target="_blank" rel="noopener">View</a>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
              <input type="hidden" name="post_id" value="<?= $pid ?>">
              <input type="hidden" name="jvb_action" value="duplicate">
              <button class="jvba-btn sm" type="submit" title="Duplicate this post and its builder layout (as draft)"><?= svg_ico('copy', 'jvb-ic', ['style' => 'width:13px;height:13px']) ?></button>
            </form>
            <?php if ($role === 'admin'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
              <input type="hidden" name="post_id" value="<?= $pid ?>">
              <?php if ($homePostId === $pid): ?>
                <input type="hidden" name="jvb_action" value="unset_home">
                <button class="jvba-btn sm" type="submit" title="Remove homepage designation"><?= svg_ico('house', 'jvb-ic', ['style' => 'width:13px;height:13px']) ?><?= svg_ico('x', 'jvb-ic', ['style' => 'width:11px;height:11px']) ?></button>
              <?php else: ?>
                <input type="hidden" name="jvb_action" value="set_home">
                <button class="jvba-btn sm" type="submit" title="Use this post's builder layout as the homepage"><?= svg_ico('house', 'jvb-ic', ['style' => 'width:13px;height:13px']) ?> Set home</button>
              <?php endif; ?>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="jvba-card" style="margin-top:1rem">
    <span class="jvba-hint">
      <strong>Draft → Publish workflow:</strong> edits in the builder are autosaved as a draft and never touch the live page until you click <strong>Publish</strong>.
      Preview drafts any time with <span class="jvba-mono">?jvb_preview=1</span> on the page URL. Revisions are kept automatically on each publish (last <?= JVB_MAX_REVISIONS ?>).
      <br><strong>Homepage:</strong> mark a post with <span class="jvba-mono">Set home</span> — its <em>published</em> builder layout replaces the theme's homepage slot (site header/sidebar/footer stay).
      Without a designation, a published page with slug <span class="jvba-mono">home</span> is used automatically.
    </span>
  </div>
</div>
