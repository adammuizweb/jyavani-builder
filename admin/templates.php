<?php
// /plugins/jyavani-builder/admin/templates.php — template library management
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;
/** @var PDO $pdo */

jvb_admin_css();
$csrf = function_exists('csrf_token') ? csrf_token() : '';
$flash = '';
$flashOk = true;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $okCsrf = function_exists('csrf_check') ? csrf_check($_POST['csrf_token'] ?? '') : true;
    $act = (string)($_POST['jvb_action'] ?? '');
    if (!$okCsrf) {
        $flash = 'Invalid CSRF token.'; $flashOk = false;
    } elseif ($act === 'delete') {
        jvb_delete_template($pdo, (int)($_POST['template_id'] ?? 0));
        $flash = 'Template deleted.';
    } elseif ($act === 'reseed') {
        $pdo->exec('DELETE FROM `jvb_templates` WHERE is_starter = 1');
        // reset the static guard by calling seed directly
        $cnt = 0;
        foreach (jvb_starter_templates() as $tpl) {
            if ($tpl['type'] === 'page') continue; // assembled below
        }
        // reuse the seeder logic inline
        $starters = jvb_starter_templates();
        $sections = [];
        foreach ($starters as $tpl) {
            if ($tpl['type'] === 'page') {
                $tpl['layout']['sections'] = array_map(static fn($s) => $s['layout'], $sections);
            } else {
                $sections[] = $tpl;
            }
            jvb_save_template($pdo, $tpl['title'], $tpl['type'], $tpl['layout'], null);
            $pdo->prepare('UPDATE `jvb_templates` SET is_starter = 1 WHERE id = ?')->execute([(int)$pdo->lastInsertId()]);
            $cnt++;
        }
        $flash = $cnt . ' starter templates restored.';
    }
}

$templates = jvb_list_templates($pdo);
?>
<div class="jvba">
  <div class="jvba-head">
    <h1>Template Library</h1>
    <div class="jvba-actions">
      <a class="jvba-btn" href="<?= jvb_url() ?>">‹ Pages</a>
      <form method="post" style="display:inline" onsubmit="return confirm('Restore starter templates? Your own templates are kept.')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <input type="hidden" name="jvb_action" value="reseed">
        <button class="jvba-btn" type="submit"><?= svg_ico('refresh-cw', 'jvb-ic', ['style' => 'width:13px;height:13px']) ?> Restore Starters</button>
      </form>
    </div>
  </div>

  <?php if ($flash !== ''): ?>
  <div class="jvba-flash <?= $flashOk ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
  <?php endif; ?>

  <div class="jvba-card">
    <span class="jvba-hint">Templates are inserted from the builder (left panel → Templates tab). Save any section or whole page as a template from the builder's canvas tools. Starters are built-in and can't be deleted.</span>
  </div>

  <?php if (!$templates): ?>
    <div class="jvba-empty">No templates yet.</div>
  <?php else: ?>
  <div class="jvba-table-wrap">
    <table class="jvba-table">
      <thead><tr><th>Title</th><th>Type</th><th>Source</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($templates as $tpl): ?>
        <tr>
          <td><strong><?= htmlspecialchars($tpl['title'], ENT_QUOTES) ?></strong></td>
          <td><?= htmlspecialchars($tpl['type'], ENT_QUOTES) ?></td>
          <td><?= !empty($tpl['is_starter']) ? '<span class="jvba-badge published">Starter</span>' : '<span class="jvba-badge none">Custom</span>' ?></td>
          <td class="jvba-sub"><?= htmlspecialchars(date('d M Y H:i', strtotime((string)$tpl['updated_at'])), ENT_QUOTES) ?></td>
          <td>
            <?php if (empty($tpl['is_starter'])): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this template?')">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
              <input type="hidden" name="jvb_action" value="delete">
              <input type="hidden" name="template_id" value="<?= (int)$tpl['id'] ?>">
              <button class="jvba-btn sm danger" type="submit">Delete</button>
            </form>
            <?php else: ?><span class="jvba-hint">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
