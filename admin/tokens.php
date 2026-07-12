<?php
// /plugins/jyavani-builder/admin/tokens.php — global design tokens (admin only)
declare(strict_types=1);

if (!defined('DASHBOARD_CONTEXT')) exit;
/** @var PDO $pdo @var string $role */

if ($role !== 'admin') { echo '<div class="jvba-empty">Admin only.</div>'; return; }

jvb_admin_css();
$csrf = function_exists('csrf_token') ? csrf_token() : '';
$flash = '';
$flashOk = true;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $okCsrf = function_exists('csrf_check') ? csrf_check($_POST['csrf_token'] ?? '') : true;
    if (!$okCsrf) {
        $flash = 'Invalid CSRF token.'; $flashOk = false;
    } else {
        $tokens = [
            'colors' => [
                'primary'   => trim((string)($_POST['c_primary'] ?? '')),
                'secondary' => trim((string)($_POST['c_secondary'] ?? '')),
                'accent'    => trim((string)($_POST['c_accent'] ?? '')),
                'text'      => trim((string)($_POST['c_text'] ?? '')),
                'muted'     => trim((string)($_POST['c_muted'] ?? '')),
                'surface'   => trim((string)($_POST['c_surface'] ?? '')),
                'alt'       => trim((string)($_POST['c_alt'] ?? '')),
                'border'    => trim((string)($_POST['c_border'] ?? '')),
            ],
            'typography' => [
                'font_body'    => trim((string)($_POST['t_font_body'] ?? '')),
                'font_heading' => trim((string)($_POST['t_font_heading'] ?? '')),
                'base_size'    => (int)($_POST['t_base_size'] ?? 16),
                'scale'        => (float)($_POST['t_scale'] ?? 1.25),
                'line_height'  => (float)($_POST['t_line_height'] ?? 1.65),
            ],
            'spacing' => [
                'container' => (int)($_POST['s_container'] ?? 1200),
                'section_y' => (int)($_POST['s_section_y'] ?? 80),
                'gap'       => (int)($_POST['s_gap'] ?? 24),
                'radius'    => (int)($_POST['s_radius'] ?? 10),
            ],
        ];
        jvb_save_tokens($pdo, $tokens);
        $flash = 'Design tokens saved.';
    }
}

$tokens = jvb_get_tokens($pdo);
$c = $tokens['colors']; $t = $tokens['typography']; $s = $tokens['spacing'];

$colorField = static function (string $name, string $label, string $val): void {
    echo '<div class="jvba-field"><label>' . htmlspecialchars($label, ENT_QUOTES) . '</label>'
        . '<div class="jvba-color-wrap"><input type="color" value="' . htmlspecialchars($val, ENT_QUOTES) . '" oninput="this.nextElementSibling.value=this.value">'
        . '<input type="text" name="' . $name . '" value="' . htmlspecialchars($val, ENT_QUOTES) . '" oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))this.previousElementSibling.value=this.value"></div></div>';
};
?>
<div class="jvba">
  <div class="jvba-head">
    <h1>Design Tokens</h1>
    <div class="jvba-actions">
      <a class="jvba-btn" href="<?= jvb_url() ?>">‹ Pages</a>
    </div>
  </div>

  <?php if ($flash !== ''): ?>
  <div class="jvba-flash <?= $flashOk ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
  <?php endif; ?>

  <div class="jvba-card">
    <span class="jvba-hint">Global design system for all builder pages. Elements reference these tokens (<span class="jvba-mono">var(--jvb-primary)</span> etc.) — change once, rebrand everywhere. Token-aware color fields in the builder accept a token name (<span class="jvba-mono">primary</span>) or a hex value.</span>
  </div>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

    <div class="jvba-group-title">Colors</div>
    <div class="jvba-tokens">
      <?php
      $colorField('c_primary', 'Primary', $c['primary']);
      $colorField('c_secondary', 'Secondary', $c['secondary']);
      $colorField('c_accent', 'Accent', $c['accent']);
      $colorField('c_text', 'Text', $c['text']);
      $colorField('c_muted', 'Muted', $c['muted']);
      $colorField('c_surface', 'Surface', $c['surface']);
      $colorField('c_alt', 'Alt Surface', $c['alt']);
      $colorField('c_border', 'Border', $c['border']);
      ?>
    </div>

    <div class="jvba-group-title">Typography</div>
    <div class="jvba-tokens">
      <div class="jvba-field"><label>Body Font</label><input type="text" name="t_font_body" value="<?= htmlspecialchars($t['font_body'], ENT_QUOTES) ?>"></div>
      <div class="jvba-field"><label>Heading Font</label><input type="text" name="t_font_heading" value="<?= htmlspecialchars($t['font_heading'], ENT_QUOTES) ?>"></div>
      <div class="jvba-field"><label>Base Size (px)</label><input type="number" name="t_base_size" value="<?= (int)$t['base_size'] ?>" min="12" max="24"></div>
      <div class="jvba-field"><label>Modular Scale</label><input type="number" step="0.05" name="t_scale" value="<?= htmlspecialchars((string)$t['scale'], ENT_QUOTES) ?>" min="1" max="2"></div>
      <div class="jvba-field"><label>Line Height</label><input type="number" step="0.05" name="t_line_height" value="<?= htmlspecialchars((string)$t['line_height'], ENT_QUOTES) ?>" min="1" max="2.5"></div>
    </div>

    <div class="jvba-group-title">Spacing & Shape</div>
    <div class="jvba-tokens">
      <div class="jvba-field"><label>Container Width (px)</label><input type="number" name="s_container" value="<?= (int)$s['container'] ?>" min="600" max="1920"></div>
      <div class="jvba-field"><label>Section Vertical Padding (px)</label><input type="number" name="s_section_y" value="<?= (int)$s['section_y'] ?>" min="0" max="400"></div>
      <div class="jvba-field"><label>Gap (px)</label><input type="number" name="s_gap" value="<?= (int)$s['gap'] ?>" min="0" max="120"></div>
      <div class="jvba-field"><label>Corner Radius (px)</label><input type="number" name="s_radius" value="<?= (int)$s['radius'] ?>" min="0" max="60"></div>
    </div>

    <div style="margin-top:1.25rem">
      <button class="jvba-btn primary" type="submit">Save Tokens</button>
    </div>
  </form>
</div>
