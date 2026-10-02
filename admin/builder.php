<?php
// /plugins/jyavani-builder/admin/builder.php — builder shell (toolbar / palette / frame / panel)
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;
/** @var PDO $pdo @var array $post @var int $uid @var string $role */

$csrf = function_exists('csrf_token') ? csrf_token() : '';
$postId = (int)($post['id'] ?? 0);
$permalink = $postId > 0 ? '/' . rawurlencode((string)($post['slug'] ?? '')) . '/' : '';
$canChangeOwner = $postId > 0 && $canManageAny && jvb_user_can_content_action($pdo, $uid, $post, 'change_owner');

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
    <div class="jvb-bar__group jvb-bar__group--identity">
      <a class="jvb-bar__btn" href="<?= jvb_url() ?>" title="Back to pages" aria-label="Back to pages"><?= svg_ico('arrow-left', 'jvb-ic') ?></a>
      <div class="jvb-bar__title">
        <strong><?= htmlspecialchars($post['title'], ENT_QUOTES) ?></strong>
        <span class="jvb-bar__slug">/<?= htmlspecialchars($post['slug'], ENT_QUOTES) ?>/ · <?= htmlspecialchars($post['type'], ENT_QUOTES) ?></span>
      </div>
    </div>

    <div class="jvb-bar__group jvb-bar__group--view">
      <div class="jvb-devices" id="jvbDevices" role="group" aria-label="Preview device">
        <button type="button" aria-pressed="true" data-device="desktop" class="is-active" title="Desktop" aria-label="Desktop preview"><?= svg_ico('monitor', 'jvb-ic') ?></button>
        <button type="button" aria-pressed="false" data-device="tablet" title="Tablet" aria-label="Tablet preview"><?= svg_ico('tablet', 'jvb-ic') ?></button>
        <button type="button" aria-pressed="false" data-device="mobile" title="Mobile" aria-label="Mobile preview"><?= svg_ico('smartphone', 'jvb-ic') ?></button>
      </div>
      <button type="button" class="jvb-bar__btn" id="jvbUndo" title="Undo (Ctrl+Z)" aria-label="Undo" disabled><?= svg_ico('undo-2', 'jvb-ic') ?></button>
      <button type="button" class="jvb-bar__btn" id="jvbRedo" title="Redo (Ctrl+Y)" aria-label="Redo" disabled><?= svg_ico('redo-2', 'jvb-ic') ?></button>
    </div>

    <div class="jvb-bar__group jvb-bar__group--actions">
      <div class="jvb-publish-state"><span class="jvb-status" id="jvbStatus" data-status="none" role="status">—</span><span class="jvb-savestate" id="jvbSaveState" aria-live="polite"></span></div>
      <button type="button" class="jvb-bar__btn" id="jvbRevisions" title="Revisions" aria-label="Revisions"><?= svg_ico('history', 'jvb-ic') ?></button>
      <button type="button" class="jvb-bar__btn" id="jvbPostSettings" title="Post settings" aria-label="Post settings"><?= svg_ico('file-text', 'jvb-ic') ?></button>
      <button type="button" class="jvb-bar__btn" id="jvbPageSettings" title="Page settings (custom CSS)" aria-label="Page settings"><?= svg_ico('settings', 'jvb-ic') ?></button>
      <a class="jvb-bar__btn" id="jvbPreview" href="<?= htmlspecialchars($permalink, ENT_QUOTES) ?>?jvb_preview=1" target="_blank" rel="noopener" title="Preview draft" aria-label="Preview draft"><?= svg_ico('eye', 'jvb-ic') ?></a>
      <button type="button" class="jvb-bar__btn" id="jvbExport" title="Export layout as JSON" aria-label="Export layout"><?= svg_ico('download', 'jvb-ic') ?></button>
      <button type="button" class="jvb-bar__btn" id="jvbImport" title="Import layout from JSON" aria-label="Import layout"><?= svg_ico('upload', 'jvb-ic') ?></button>
      <button type="button" class="jvb-bar__btn jvb-bar__btn--publish" id="jvbPublish">Publish</button>
    </div>
  </header>

  <!-- ── Workspace ── -->
  <div class="jvb-work">
    <button type="button" class="jvb-edge-tab jvb-edge-tab--left" id="jvbEdgeLeft" title="Show/hide elements panel (Ctrl+B)" aria-label="Toggle elements panel" aria-expanded="true" aria-controls="jvbLeft"><?= svg_ico('chevron-left', 'jvb-ic', ['style' => 'width:12px;height:12px']) ?></button>
    <button type="button" class="jvb-edge-tab jvb-edge-tab--right" id="jvbEdgeRight" title="Show/hide settings panel" aria-label="Toggle settings panel" aria-expanded="true" aria-controls="jvbPanel"><?= svg_ico('chevron-right', 'jvb-ic', ['style' => 'width:12px;height:12px']) ?></button>
    <!-- Left: palette -->
    <aside class="jvb-left" id="jvbLeft" aria-label="Builder library">
      <div class="jvb-left__tabs" role="tablist" aria-label="Builder library">
        <button type="button" id="jvbTabElements" role="tab" aria-selected="true" aria-controls="jvbPalette" class="is-active" data-tab="elements">Elements</button>
        <button type="button" id="jvbTabSections" role="tab" aria-selected="false" aria-controls="jvbSections" data-tab="sections">Sections</button>
        <button type="button" id="jvbTabTemplates" role="tab" aria-selected="false" aria-controls="jvbTemplates" data-tab="templates">Templates</button>
      </div>
      <div class="jvb-left__search">
        <input type="search" id="jvbPaletteSearch" placeholder="Filter elements…" aria-label="Filter elements">
      </div>
      <div class="jvb-palette" id="jvbPalette" role="tabpanel" aria-labelledby="jvbTabElements" data-tabpanel="elements"></div>
      <div class="jvb-sections" id="jvbSections" role="tabpanel" aria-labelledby="jvbTabSections" data-tabpanel="sections" hidden>
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
      <div class="jvb-templates" id="jvbTemplates" role="tabpanel" aria-labelledby="jvbTabTemplates" data-tabpanel="templates" hidden>
        <div id="jvbTplList"></div>
      </div>
    </aside>

    <!-- Center: canvas -->
    <main class="jvb-canvas" id="jvbCanvas">
      <div class="jvb-canvas__frame-wrap" id="jvbFrameWrap">
        <iframe id="jvbFrame" title="Page builder canvas" src="about:blank"></iframe>
      </div>
    </main>

    <!-- Right: settings panel -->
    <aside class="jvb-panel" id="jvbPanel" aria-label="Element settings">
      <div class="jvb-panel__head">
        <span id="jvbPanelTitle">Settings</span>
        <button type="button" class="jvb-panel__close" id="jvbPanelClose" title="Hide panel" aria-label="Hide settings panel"><?= svg_ico('x', 'jvb-ic', ['style' => 'width:14px;height:14px']) ?></button>
      </div>
      <div class="jvb-panel__tabs" id="jvbPanelTabs" role="tablist" aria-label="Settings category" hidden>
        <button type="button" id="jvbPtabContent" role="tab" aria-selected="true" aria-controls="jvbPanelBody" class="is-active" data-ptab="content">Content</button>
        <button type="button" id="jvbPtabStyle" role="tab" aria-selected="false" aria-controls="jvbPanelBody" data-ptab="style">Style</button>
        <button type="button" id="jvbPtabAdvanced" role="tab" aria-selected="false" aria-controls="jvbPanelBody" data-ptab="advanced">Advanced</button>
      </div>
      <div class="jvb-panel__body" id="jvbPanelBody" role="tabpanel" aria-labelledby="jvbPtabContent">
        <p class="jvb-panel__hint">Select a section, column or element on the canvas to edit its settings.</p>
      </div>
    </aside>
    <!-- Revisions stays inside the workspace so responsive toolbars remain visible. -->
    <div class="jvb-drawer" id="jvbRevDrawer" role="complementary" aria-label="Revisions" hidden>
      <div class="jvb-drawer__head"><strong>Revisions</strong><button type="button" id="jvbRevClose" aria-label="Close revisions"><?= svg_ico('x', 'jvb-ic', ['style' => 'width:14px;height:14px']) ?></button></div>
      <div class="jvb-drawer__body" id="jvbRevList"></div>
    </div>
  </div>

  <!-- ── Post Settings modal ── -->
  <div class="jvb-post-overlay" id="jvbPostModal" hidden>
    <div class="jvb-post-modal" role="dialog" aria-modal="true" aria-labelledby="jvbPostModalTitle">
      <div class="jvb-post-modal__head">
        <strong id="jvbPostModalTitle">Post Settings</strong>
        <button type="button" class="jvb-post-modal__close" id="jvbPostModalClose" aria-label="Close post settings"><?= svg_ico('x', 'jvb-ic', ['style' => 'width:14px;height:14px']) ?></button>
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
        <?php if ($canChangeOwner): ?>
        <label class="jvb-post-modal__field" id="jvbPostAuthorWrap">
          <span>Author</span>
          <select id="jvbPostAuthor">
            <?php
            $users = $pdo->query("SELECT id, name, role FROM users WHERE is_deleted = 0 AND role IN ('admin','editor','author') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
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

  <!-- ── Import modal ── -->
  <div class="jvb-post-overlay" id="jvbImportModal" hidden>
    <div class="jvb-post-modal" role="dialog" aria-modal="true" aria-labelledby="jvbImportModalTitle">
      <div class="jvb-post-modal__head">
        <strong id="jvbImportModalTitle">Import layout</strong>
        <button type="button" class="jvb-post-modal__close" id="jvbImportModalClose" aria-label="Close import dialog"><?= svg_ico('x', 'jvb-ic', ['style' => 'width:14px;height:14px']) ?></button>
      </div>
      <div class="jvb-post-modal__body">
        <label class="jvb-post-modal__field">
          <span>Paste a <code>.jvb.json</code> export (or a bare layout object). Current draft will be replaced — undo still works.</span>
          <textarea id="jvbImportText" rows="12" style="width:100%;font-family:ui-monospace,monospace;font-size:12px" placeholder='{"format":"jvb-layout/3","layout":{"v":3,"sections":[…]}}'></textarea>
        </label>
      </div>
      <div class="jvb-post-modal__foot">
        <button class="jvb-post-modal__btn jvb-post-modal__btn--cancel" id="jvbImportModalCancel">Cancel</button>
        <button class="jvb-post-modal__btn jvb-post-modal__btn--save" id="jvbImportModalApply">Import</button>
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
