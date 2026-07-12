<?php
// /plugins/jyavani-builder/admin/_ui.php — shared admin helpers & base CSS
declare(strict_types=1);

function jvb_url(array $params = []): string {
    $params = array_merge(['page' => 'admin/tools/jyavani-builder'], array_filter($params, static fn($v) => $v !== null));
    return '?' . http_build_query($params);
}

function jvb_js_redirect(string $url): void {
    echo '<script>location.replace(' . json_encode($url) . ');</script>';
}

function jvb_admin_css(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    ?>
<style>
.jvba { color: var(--adam-text); font-family: inherit; }
.jvba a { color: var(--adam-accent); text-decoration: none; }
.jvba-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.jvba-head h1 { font-size: 1.35rem; margin: 0; }
.jvba-actions { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
.jvba-btn { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem .9rem; border-radius: 8px; border: 1px solid var(--adam-border); background: var(--adam-card); color: var(--adam-text); cursor: pointer; font: inherit; font-size: .85rem; }
.jvba-btn:hover { background: var(--adam-bg); }
.jvba-btn.primary { background: var(--adam-accent); border-color: var(--adam-accent); color: #fff; }
.jvba-btn.primary:hover { filter: brightness(1.08); }
.jvba-btn.danger { color: var(--adam-danger); border-color: var(--adam-danger); }
.jvba-btn.sm { padding: .3rem .6rem; font-size: .78rem; }
.jvba-card { background: var(--adam-card); border: 1px solid var(--adam-border); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
.jvba-hint { color: var(--adam-text-2); font-size: .8rem; }
.jvba-flash { padding: .7rem 1rem; border-radius: 10px; margin-bottom: 1rem; font-size: .88rem; }
.jvba-flash.ok { background: color-mix(in srgb, var(--adam-success) 14%, transparent); border: 1px solid var(--adam-success); }
.jvba-flash.err { background: color-mix(in srgb, var(--adam-danger) 14%, transparent); border: 1px solid var(--adam-danger); }
.jvba-table-wrap { overflow-x: auto; border: 1px solid var(--adam-border); border-radius: 12px; background: var(--adam-card); }
.jvba-table { width: 100%; border-collapse: collapse; font-size: .87rem; }
.jvba-table th, .jvba-table td { padding: .65rem .9rem; text-align: left; border-bottom: 1px solid var(--adam-border); }
.jvba-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--adam-text-2); background: var(--adam-bg); }
.jvba-table tbody tr:last-child td { border-bottom: 0; }
.jvba-table tbody tr:hover { background: var(--adam-bg); }
.jvba-sub { display: block; font-size: .76rem; color: var(--adam-text-2); }
.jvba-mono { font-family: ui-monospace, monospace; }
.jvba-badge { display: inline-block; padding: .12rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; }
.jvba-badge.published { background: color-mix(in srgb, var(--adam-success) 18%, transparent); color: var(--adam-success); }
.jvba-badge.draft { background: color-mix(in srgb, var(--adam-warning) 18%, transparent); color: var(--adam-warning); }
.jvba-badge.none { background: var(--adam-bg); color: var(--adam-text-2); }
.jvba-empty { padding: 2.5rem 1rem; text-align: center; color: var(--adam-text-2); }
.jvba-search { display: flex; gap: .4rem; }
.jvba-search input[type=search] { padding: .4rem .7rem; border-radius: 8px; border: 1px solid var(--adam-border); background: var(--adam-card); color: var(--adam-text); min-width: 220px; }
.jvba-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
/* Tokens form */
.jvba-tokens { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .9rem; }
.jvba-field label { display: block; font-size: .78rem; font-weight: 600; margin-bottom: .3rem; }
.jvba-field input, .jvba-field select, .jvba-field textarea { width: 100%; padding: .45rem .6rem; border-radius: 8px; border: 1px solid var(--adam-border); background: var(--adam-card); color: var(--adam-text); font: inherit; font-size: .85rem; }
.jvba-color-wrap { display: flex; gap: .4rem; align-items: center; }
.jvba-color-wrap input[type=color] { width: 38px; height: 34px; padding: 2px; border-radius: 8px; border: 1px solid var(--adam-border); background: var(--adam-card); cursor: pointer; }
.jvba-group-title { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: var(--adam-text-2); margin: 1.2rem 0 .6rem; }
</style>
<?php
}
