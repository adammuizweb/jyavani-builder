<?php
// /plugins/jyavani-builder/admin/builder.php — builder shell (toolbar / palette / frame / panel)
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;
/** @var PDO $pdo @var array $post @var int $uid @var string $role */

$csrf = function_exists('csrf_token') ? csrf_token() : '';
$postId = (int)$post['id'];
$permalink = '/' . rawurlencode((string)$post['slug']) . '/';

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
?>
<link rel="stylesheet" href="/static/vendor/jyavani-builder/builder.css">
<div class="jvb-app" id="jvbApp">
  <!-- ── Toolbar ── -->
  <header class="jvb-bar">
    <div class="jvb-bar__group">
      <a class="jvb-bar__btn" href="<?= jvb_url() ?>" title="Back to pages"><?= jvb_ui_icon('arrow-left') ?></a>
      <div class="jvb-bar__title">
        <strong><?= htmlspecialchars($post['title'], ENT_QUOTES) ?></strong>
        <span class="jvb-bar__slug">/<?= htmlspecialchars($post['slug'], ENT_QUOTES) ?>/ · <?= htmlspecialchars($post['type'], ENT_QUOTES) ?></span>
      </div>
    </div>

    <div class="jvb-bar__group">
      <div class="jvb-devices" id="jvbDevices" role="tablist" aria-label="Preview device">
        <button data-device="desktop" class="is-active" title="Desktop"><?= jvb_ui_icon('monitor') ?></button>
        <button data-device="tablet" title="Tablet"><?= jvb_ui_icon('tablet') ?></button>
        <button data-device="mobile" title="Mobile"><?= jvb_ui_icon('smartphone') ?></button>
      </div>
      <button class="jvb-bar__btn" id="jvbUndo" title="Undo (Ctrl+Z)" disabled><?= jvb_ui_icon('undo-2') ?></button>
      <button class="jvb-bar__btn" id="jvbRedo" title="Redo (Ctrl+Y)" disabled><?= jvb_ui_icon('redo-2') ?></button>
    </div>

    <div class="jvb-bar__group">
      <span class="jvb-status" id="jvbStatus" data-status="none">—</span>
      <span class="jvb-savestate" id="jvbSaveState"></span>
      <button class="jvb-bar__btn" id="jvbRevisions" title="Revisions"><?= jvb_ui_icon('history') ?></button>
      <button class="jvb-bar__btn" id="jvbPageSettings" title="Page settings (custom CSS)"><?= jvb_ui_icon('settings') ?></button>
      <a class="jvb-bar__btn" id="jvbPreview" href="<?= htmlspecialchars($permalink, ENT_QUOTES) ?>?jvb_preview=1" target="_blank" rel="noopener" title="Preview draft"><?= jvb_ui_icon('eye') ?></a>
      <button class="jvb-bar__btn jvb-bar__btn--publish" id="jvbPublish">Publish</button>
    </div>
  </header>

  <!-- ── Workspace ── -->
  <div class="jvb-work">
    <button class="jvb-edge-tab jvb-edge-tab--left" id="jvbEdgeLeft" title="Show/hide elements panel (Ctrl+B)" aria-label="Toggle elements panel"><?= jvb_ui_icon('chevron-left', 12) ?></button>
    <button class="jvb-edge-tab jvb-edge-tab--right" id="jvbEdgeRight" title="Show/hide settings panel" aria-label="Toggle settings panel"><?= jvb_ui_icon('chevron-right', 12) ?></button>
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
        <p class="jvb-left__hint">Click to append a section:</p>
        <div class="jvb-sec-grid" id="jvbSecGrid"></div>
        <p class="jvb-left__hint">Structure:</p>
        <button class="jvb-sec-add" id="jvbAddSection">+ Empty section</button>
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
        <button class="jvb-panel__close" id="jvbPanelClose" title="Hide panel"><?= jvb_ui_icon('x', 14) ?></button>
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
    <div class="jvb-drawer__head"><strong>Revisions</strong><button id="jvbRevClose"><?= jvb_ui_icon('x', 14) ?></button></div>
    <div class="jvb-drawer__body" id="jvbRevList"></div>
  </div>
</div>

<script>window.JVB_BOOT = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="/static/js/add/modal-helpers.js"></script>
<script src="/static/js/add/media-selector.js"></script>
<script src="/static/js/add/file-selector.js"></script>
<script src="/static/vendor/jyavani-builder/builder.js"></script>
