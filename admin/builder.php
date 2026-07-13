<?php
// /plugins/jyavani-builder/admin/builder.php — builder shell (toolbar / palette / frame / panel)
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;
/** @var PDO $pdo @var array $post @var int $uid @var string $role */

$csrf = function_exists('csrf_token') ? csrf_token() : '';
$postId = (int)($post['id'] ?? 0);
$permalink = $postId > 0 ? '/' . rawurlencode((string)($post['slug'] ?? '')) . '/' : '';

$boot = [
    'postId'    => $postId,
    'post'      => $post,
    'permalink' => $permalink,
    'ajax'      => '/jvb-builder/',
    'frameUrl'  => '/jvb-builder/?action=frame&post_id=' . $postId,
    'csrf'      => $csrf,
    'role'      => $role,
    'adminBase' => defined('ADMIN_BASE_PATH') ? ADMIN_BASE_PATH : '',
    'listUrl'   => jvb_url(),
];
$assetDir = __DIR__ . '/../assets';
$v = max(
    (int)@filemtime($assetDir . '/builder.js'),
    (int)@filemtime($assetDir . '/builder.css')
);
?>
<link rel="stylesheet" href="<?= jvb_asset_url('builder.css') ?>">
<div class="jvb-app" id="jvbApp">
  <!-- ── Toolbar ── -->
  <header class="jvb-bar">
    <div class="jvb-bar__group">
      <a class="jvb-bar__btn" href="<?= jvb_url() ?>" title="Back to pages"><?= svg_ico('arrow-left', 'jvb-ic') ?></a>
      <div class="jvb-bar__title">
        <strong><?= htmlspecialchars($post['title'], ENT_QUOTES) ?></strong>
        <span class="jvb-bar__slug">/<?= htmlspecialchars($post['slug'], ENT_QUOTES) ?>/ · <?= htmlspecialchars($post['type'], ENT_QUOTES) ?></span>
      </div>
    </div>

    <div class="jvb-bar__group">
      <div class="jvb-devices" id="jvbDevices" role="tablist" aria-label="Preview device">
        <button data-device="desktop" class="is-active" title="Desktop"><?= svg_ico('monitor', 'jvb-ic') ?></button>
        <button data-device="tablet" title="Tablet"><?= svg_ico('tablet', 'jvb-ic') ?></button>
        <button data-device="mobile" title="Mobile"><?= svg_ico('smartphone', 'jvb-ic') ?></button>
      </div>
      <button class="jvb-bar__btn" id="jvbUndo" title="Undo (Ctrl+Z)" disabled><?= svg_ico('undo-2', 'jvb-ic') ?></button>
      <button class="jvb-bar__btn" id="jvbRedo" title="Redo (Ctrl+Y)" disabled><?= svg_ico('redo-2', 'jvb-ic') ?></button>
    </div>

    <div class="jvb-bar__group">
      <span class="jvb-status" id="jvbStatus" data-status="none">—</span>
      <span class="jvb-savestate" id="jvbSaveState"></span>
      <button class="jvb-bar__btn" id="jvbRevisions" title="Revisions"><?= svg_ico('history', 'jvb-ic') ?></button>
      <button class="jvb-bar__btn" id="jvbPostSettings" title="Post settings"><?= svg_ico('file-text', 'jvb-ic') ?></button>
      <button class="jvb-bar__btn" id="jvbPageSettings" title="Page settings (custom CSS)"><?= svg_ico('settings', 'jvb-ic') ?></button>
      <a class="jvb-bar__btn" id="jvbPreview" href="<?= htmlspecialchars($permalink, ENT_QUOTES) ?>?jvb_preview=1" target="_blank" rel="noopener" title="Preview draft"><?= svg_ico('eye', 'jvb-ic') ?></a>
      <button class="jvb-bar__btn jvb-bar__btn--publish" id="jvbPublish">Publish</button>
    </div>
  </header>

  <!-- ── Workspace ── -->
  <div class="jvb-work">
    <button class="jvb-edge-tab jvb-edge-tab--left" id="jvbEdgeLeft" title="Show/hide elements panel (Ctrl+B)" aria-label="Toggle elements panel"><?= svg_ico('chevron-left', 'jvb-ic', ['style' => 'width:12px;height:12px']) ?></button>
    <button class="jvb-edge-tab jvb-edge-tab--right" id="jvbEdgeRight" title="Show/hide settings panel" aria-label="Toggle settings panel"><?= svg_ico('chevron-right', 'jvb-ic', ['style' => 'width:12px;height:12px']) ?></button>
    <!-- Left: palette -->
    <aside class="jvb-left" id="jvbLeft">
      <div class="jvb-left__tabs">
        <button class="is-active" data-tab="elements">Elements</button>
        <button data-tab="sections">Sections</button>
        <button data-tab="templates">Templates</button>
      </div>
      <div class="jvb-left__search">
        <input type="search" id="jvbPaletteSearch" placeholder="Filter elements…">
      </div>
      <div class="jvb-palette" id="jvbPalette" data-tabpanel="elements"></div>
      <div class="jvb-sections" id="jvbSections" data-tabpanel="sections" hidden>
        <p class="jvb-left__hint">Start with a section:</p>
        <div class="jvb-sec-group">
          <div class="jvb-sec-group__title">Quick Start</div>
          <div class="jvb-sec-grid" id="jvbSecQuick"></div>
        </div>
        <div class="jvb-sec-group">
          <div class="jvb-sec-group__title">Columns (1 row)</div>
          <div class="jvb-sec-grid" id="jvbSecCols"></div>
        </div>
        <div class="jvb-sec-group">
          <div class="jvb-sec-group__title">Rows (1 column each)</div>
          <div class="jvb-sec-grid" id="jvbSecRows"></div>
        </div>
      </div>
      <div class="jvb-templates" id="jvbTemplates" data-tabpanel="templates" hidden>
        <div id="jvbTplList"></div>
      </div>
    </aside>

    <!-- Center: canvas -->
    <main class="jvb-canvas" id="jvbCanvas">
      <div class="jvb-canvas__frame-wrap" id="jvbFrameWrap">
        <iframe id="jvbFrame" title="Canvas" src="about:blank"></iframe>
      </div>
    </main>

    <!-- Right: settings panel -->
    <aside class="jvb-panel" id="jvbPanel">
      <div class="jvb-panel__head">
        <span id="jvbPanelTitle">Settings</span>
        <button class="jvb-panel__close" id="jvbPanelClose" title="Hide panel"><?= svg_ico('x', 'jvb-ic', ['style' => 'width:14px;height:14px']) ?></button>
      </div>
      <div class="jvb-panel__tabs" id="jvbPanelTabs" hidden>
        <button class="is-active" data-ptab="content">Content</button>
        <button data-ptab="style">Style</button>
        <button data-ptab="advanced">Advanced</button>
      </div>
      <div class="jvb-panel__body" id="jvbPanelBody">
        <p class="jvb-panel__hint">Select a section, column or element on the canvas to edit its settings.</p>
      </div>
    </aside>
  </div>

  <!-- ── Revisions drawer ── -->
  <div class="jvb-drawer" id="jvbRevDrawer" hidden>
    <div class="jvb-drawer__head"><strong>Revisions</strong><button id="jvbRevClose"><?= svg_ico('x', 'jvb-ic', ['style' => 'width:14px;height:14px']) ?></button></div>
    <div class="jvb-drawer__body" id="jvbRevList"></div>
  </div>
  <!-- ── Post Settings modal ── -->
  <div class="jvb-post-overlay" id="jvbPostModal" hidden>
    <div class="jvb-post-modal">
      <div class="jvb-post-modal__head">
        <strong>Post Settings</strong>
        <button class="jvb-post-modal__close" id="jvbPostModalClose"><?= svg_ico('x', 'jvb-ic', ['style' => 'width:14px;height:14px']) ?></button>
      </div>
      <div class="jvb-post-modal__body">
        <label class="jvb-post-modal__field">
          <span>Title</span>
          <input type="text" id="jvbPostTitle" value="<?= htmlspecialchars($post['title'] ?? '', ENT_QUOTES) ?>" placeholder="Post title">
        </label>
        <label class="jvb-post-modal__field">
          <span>Type</span>
          <select id="jvbPostType">
            <option value="page"<?= ($post['type'] ?? '') === 'page' ? ' selected' : '' ?>>Page</option>
            <option value="article"<?= ($post['type'] ?? '') === 'article' ? ' selected' : '' ?>>Article</option>
            <option value="theme"<?= ($post['type'] ?? 'theme') === 'theme' ? ' selected' : '' ?>>Theme</option>
          </select>
        </label>
        <label class="jvb-post-modal__field">
          <span>Status</span>
          <select id="jvbPostStatus">
            <option value="draft"<?= ($post['status'] ?? 'draft') === 'draft' ? ' selected' : '' ?>>Draft</option>
            <option value="published"<?= ($post['status'] ?? '') === 'published' ? ' selected' : '' ?>>Published</option>
            <option value="private"<?= ($post['status'] ?? '') === 'private' ? ' selected' : '' ?>>Private</option>
          </select>
        </label>
        <?php if ($role === 'admin'): ?>
        <label class="jvb-post-modal__field" id="jvbPostAuthorWrap">
          <span>Author</span>
          <select id="jvbPostAuthor">
            <?php
            $users = $pdo->query("SELECT id, name, role FROM users WHERE is_deleted = 0 AND role IN ('admin','editor') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            $curAuthor = (int)($post['created_by'] ?? $uid);
            foreach ($users as $u):
            ?>
            <option value="<?= (int)$u['id'] ?>"<?= (int)$u['id'] === $curAuthor ? ' selected' : '' ?>><?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endif; ?>
      </div>
      <div class="jvb-post-modal__foot">
        <button class="jvb-post-modal__btn jvb-post-modal__btn--cancel" id="jvbPostModalCancel">Cancel</button>
        <button class="jvb-post-modal__btn jvb-post-modal__btn--save" id="jvbPostModalSave">Save</button>
      </div>
    </div>
  </div>

</div>

<script>window.JVB_BOOT = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<link rel="stylesheet" href="/static/components/toast/toast.css">
<link rel="stylesheet" href="/static/components/confirm/confirm.css">
<script src="/static/components/toast/toast.js"></script>
<script src="/static/components/confirm/confirm.js"></script>
<script src="/static/js/add/modal-helpers.js"></script>
<script src="/static/js/add/media-selector.js"></script>
<script src="/static/js/add/file-selector.js"></script>
<script src="<?= jvb_asset_url('builder.js') ?>"></script>
