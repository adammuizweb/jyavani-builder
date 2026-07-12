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
      <a class="jvb-bar__btn" href="<?= jvb_url() ?>" title="Back to pages">‹</a>
      <button class="jvb-bar__btn" id="jvbToggleLeft" title="Toggle elements panel (Ctrl+B)" aria-pressed="false">◧</button>
      <div class="jvb-bar__title">
        <strong><?= htmlspecialchars($post['title'], ENT_QUOTES) ?></strong>
        <span class="jvb-bar__slug">/<?= htmlspecialchars($post['slug'], ENT_QUOTES) ?>/ · <?= htmlspecialchars($post['type'], ENT_QUOTES) ?></span>
      </div>
    </div>

    <div class="jvb-bar__group">
      <div class="jvb-devices" id="jvbDevices" role="tablist" aria-label="Preview device">
        <button data-device="desktop" class="is-active" title="Desktop">🖥</button>
        <button data-device="tablet" title="Tablet">📱</button>
        <button data-device="mobile" title="Mobile">📲</button>
      </div>
      <button class="jvb-bar__btn" id="jvbUndo" title="Undo (Ctrl+Z)" disabled>↶</button>
      <button class="jvb-bar__btn" id="jvbRedo" title="Redo (Ctrl+Y)" disabled>↷</button>
    </div>

    <div class="jvb-bar__group">
      <span class="jvb-status" id="jvbStatus" data-status="none">—</span>
      <span class="jvb-savestate" id="jvbSaveState"></span>
      <button class="jvb-bar__btn" id="jvbToggleRight" title="Show/hide settings panel" aria-pressed="false">◨</button>
      <button class="jvb-bar__btn" id="jvbRevisions" title="Revisions">🕓</button>
      <button class="jvb-bar__btn" id="jvbPageSettings" title="Page settings (custom CSS)">⚙</button>
      <a class="jvb-bar__btn" id="jvbPreview" href="<?= htmlspecialchars($permalink, ENT_QUOTES) ?>?jvb_preview=1" target="_blank" rel="noopener" title="Preview draft">👁</a>
      <button class="jvb-bar__btn jvb-bar__btn--publish" id="jvbPublish">Publish</button>
    </div>
  </header>

  <!-- ── Workspace ── -->
  <div class="jvb-work">
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
        <button class="jvb-panel__close" id="jvbPanelClose" title="Hide panel">✕</button>
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
    <div class="jvb-drawer__head"><strong>Revisions</strong><button id="jvbRevClose">✕</button></div>
    <div class="jvb-drawer__body" id="jvbRevList"></div>
  </div>
</div>

<script>window.JVB_BOOT = <?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="/static/js/add/modal-helpers.js"></script>
<script src="/static/js/add/media-selector.js"></script>
<script src="/static/js/add/file-selector.js"></script>
<script src="/static/vendor/jyavani-builder/builder.js"></script>
