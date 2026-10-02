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
.jvba { --jvba-brand:#6252d7; --jvba-brand-strong:#4937b8; --jvba-accent-text:#4937b8; --jvba-soft:color-mix(in srgb,var(--jvba-brand) 10%,transparent); --jvba-border:color-mix(in srgb,var(--jvba-brand) 24%,var(--adam-border)); width:100%; max-width:1180px; margin:0 auto; color:var(--adam-text); font-family:inherit; }
.jvba, .jvba * { box-sizing:border-box; }
.jvba a { color:var(--jvba-accent-text); text-decoration:none; }
.jvba-head, .jvba-hero { display:flex; align-items:center; justify-content:space-between; gap:1.25rem; flex-wrap:wrap; margin-bottom:1rem; }
.jvba-head { padding:.15rem 0; }
.jvba-head h1, .jvba-hero h1 { margin:0; color:var(--adam-text); font-size:clamp(1.35rem,3vw,1.8rem); letter-spacing:-.025em; line-height:1.2; }
.jvba-head p, .jvba-hero p { margin:.35rem 0 0; color:var(--adam-muted,var(--adam-text-2)); font-size:.86rem; line-height:1.55; }
.jvba-hero { position:relative; overflow:hidden; padding:clamp(1.1rem,3vw,1.6rem); border:1px solid var(--jvba-border); border-radius:19px; background:radial-gradient(circle at 91% -20%,color-mix(in srgb,var(--jvba-brand) 22%,transparent),transparent 35%),linear-gradient(135deg,var(--jvba-soft),var(--adam-card) 58%); box-shadow:0 14px 34px rgba(49,38,120,.07); }
.jvba-hero::after { position:absolute; right:-48px; bottom:-86px; width:190px; height:190px; border:25px solid color-mix(in srgb,var(--jvba-brand) 6%,transparent); border-radius:50%; content:""; pointer-events:none; }
.jvba-hero__intro { display:flex; align-items:center; min-width:0; gap:1rem; }
.jvba-hero__mark { display:grid; width:58px; height:58px; flex:0 0 auto; place-items:center; border-radius:17px; color:#fff; background:linear-gradient(145deg,#8878ff,var(--jvba-brand-strong)); box-shadow:0 12px 25px rgba(73,55,184,.24); }
.jvba-hero__mark svg { width:27px; height:27px; }
.jvba-eyebrow { display:block; margin:0 0 .28rem; color:var(--jvba-accent-text); font-size:.68rem; font-weight:800; letter-spacing:.13em; text-transform:uppercase; }
.jvba-actions { position:relative; z-index:1; display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
.jvba-btn { display:inline-flex; align-items:center; justify-content:center; min-height:42px; gap:.42rem; padding:.58rem .88rem; border:1px solid var(--adam-border); border-radius:10px; color:var(--adam-text); background:var(--adam-card); box-shadow:0 1px 2px rgba(17,24,39,.04); cursor:pointer; font:inherit; font-size:.82rem; font-weight:700; line-height:1.2; transition:border-color .16s,box-shadow .16s,transform .16s,background .16s; }
.jvba-btn:hover { border-color:var(--jvba-border); background:var(--adam-surface-2,var(--adam-bg)); box-shadow:0 7px 18px rgba(73,55,184,.1); transform:translateY(-1px); }
.jvba-btn:focus-visible, .jvba-field input:focus-visible, .jvba-field select:focus-visible, .jvba-field textarea:focus-visible, .jvba-search input:focus-visible { outline:3px solid color-mix(in srgb,var(--jvba-brand) 28%,transparent); outline-offset:2px; }
.jvba-btn.primary { border-color:var(--jvba-brand-strong); color:#fff; background:linear-gradient(135deg,var(--jvba-brand),var(--jvba-brand-strong)); box-shadow:0 8px 18px rgba(73,55,184,.2); }
.jvba-btn.primary:hover { color:#fff; background:linear-gradient(135deg,#5846cb,#3f2da8); }
.jvba-btn.danger { color:var(--adam-danger); border-color:color-mix(in srgb,var(--adam-danger) 42%,var(--adam-border)); }
.jvba-btn.sm { min-height:36px; padding:.42rem .65rem; font-size:.76rem; }
.jvba-stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.8rem; margin-bottom:1rem; }
.jvba-stat { display:flex; align-items:center; min-width:0; gap:.78rem; padding:.9rem 1rem; border:1px solid var(--adam-border); border-radius:14px; background:var(--adam-card); box-shadow:0 5px 18px rgba(17,24,39,.035); }
.jvba-stat__icon { display:grid; width:40px; height:40px; flex:0 0 auto; place-items:center; border-radius:11px; color:var(--jvba-accent-text); background:var(--jvba-soft); }
.jvba-stat__icon svg { width:18px; height:18px; }
.jvba-stat strong, .jvba-stat span { display:block; }
.jvba-stat strong { color:var(--adam-text); font-size:1.25rem; letter-spacing:-.03em; line-height:1.1; }
.jvba-stat span { margin-top:.2rem; color:var(--adam-muted,var(--adam-text-2)); font-size:.72rem; }
.jvba-card { margin-bottom:1rem; padding:1rem 1.15rem; border:1px solid var(--adam-border); border-radius:14px; background:var(--adam-card); box-shadow:0 5px 18px rgba(17,24,39,.035); }
.jvba-card--guide { display:flex; align-items:flex-start; gap:.65rem; border-color:var(--jvba-border); background:linear-gradient(135deg,var(--jvba-soft),var(--adam-card)); }
.jvba-card--guide svg { width:18px; height:18px; flex:0 0 auto; margin-top:.08rem; color:var(--jvba-accent-text); }
.jvba-hint { color:var(--adam-muted,var(--adam-text-2)); font-size:.79rem; line-height:1.55; }
.jvba-flash { padding:.78rem 1rem; border-radius:11px; margin-bottom:1rem; font-size:.84rem; }
.jvba-flash.ok { background:color-mix(in srgb,var(--adam-success) 14%,transparent); border:1px solid color-mix(in srgb,var(--adam-success) 35%,transparent); }
.jvba-flash.err { background:color-mix(in srgb,var(--adam-danger) 14%,transparent); border:1px solid color-mix(in srgb,var(--adam-danger) 35%,transparent); }
.jvba-table-wrap { overflow-x:auto; border:1px solid var(--adam-border); border-radius:15px; background:var(--adam-card); box-shadow:0 8px 26px rgba(17,24,39,.045); }
.jvba-table { width:100%; border-collapse:collapse; font-size:.83rem; }
.jvba-table th, .jvba-table td { padding:.78rem .9rem; text-align:left; vertical-align: middle; border-bottom:1px solid var(--adam-border-softer,var(--adam-border)); }
.jvba-table th { color:var(--adam-muted,var(--adam-text-2)); background:var(--adam-surface-2,var(--adam-bg)); font-size:.66rem; font-weight:800; letter-spacing:.075em; text-transform:uppercase; }
.jvba-table tbody tr:last-child td { border-bottom:0; }
.jvba-table tbody tr:hover { background:var(--adam-hover,var(--adam-surface-2,var(--adam-bg))); }
.jvba-table td strong { color:var(--adam-text); font-weight:750; }
.jvba-post-title { min-width:0; }
.jvba-post-title .jvba-sub { margin-top:.12rem; overflow-wrap:anywhere; }
.jvba-sub { display:block; color:var(--adam-muted,var(--adam-text-2)); font-size:.73rem; line-height:1.4; }
.jvba-mono { font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; }
.jvba-badge { display:inline-flex; align-items:center; width:fit-content; gap:.25rem; padding:.25rem .5rem; border-radius:999px; font-size:.67rem; font-weight:800; line-height:1; }
.jvba-badge.published { background:color-mix(in srgb,var(--adam-success) 16%,transparent); color:var(--adam-success); }
.jvba-badge.draft { background:color-mix(in srgb,var(--adam-warning) 16%,transparent); color:var(--adam-warning); }
.jvba-badge.none { background:var(--adam-surface-3,var(--adam-bg)); color:var(--adam-muted,var(--adam-text-2)); }
.jvba-empty { padding:3rem 1rem; border:1px dashed var(--adam-border-2,var(--adam-border)); border-radius:14px; color:var(--adam-muted,var(--adam-text-2)); background:var(--adam-surface-2,var(--adam-bg)); text-align:center; }
.jvba-toolbar { display:flex; justify-content:space-between; align-items:center; gap:.8rem; flex-wrap:wrap; margin-bottom:1rem; padding:.7rem; border:1px solid var(--adam-border); border-radius:13px; background:var(--adam-card); }
.jvba-search { display:flex; min-width:min(100%,340px); gap:.4rem; }
.jvba-search input[type=search] { min-width:0; min-height:36px; flex:1; padding:.48rem .7rem; border:1px solid var(--adam-border-2,var(--adam-border)); border-radius:9px; color:var(--adam-text); background:var(--adam-bg,var(--adam-card)); font:inherit; font-size:.8rem; }
.jvba-filter.is-active { border-color:var(--jvba-border); color:var(--jvba-accent-text); background:var(--jvba-soft); }
.jvba-updated { white-space: nowrap; }
.jvba-actions-cell { width: 1%; white-space: nowrap; text-align: right !important; }
.jvba-overflow { display:inline-flex; }
.jvba-overflow-trigger { display:inline-grid; width:36px; height:36px; padding:0; place-items:center; border:1px solid var(--adam-border); border-radius:9px; color:var(--adam-text); background:var(--adam-card); cursor:pointer; font:700 1.1rem/1 inherit; }
.jvba-overflow-trigger:hover, .jvba-overflow-trigger:focus-visible, .jvba-overflow-trigger[aria-expanded="true"] { border-color:var(--jvba-brand); color:var(--jvba-accent-text); background:var(--jvba-soft); outline:none; }
.jvba-overflow-menu { position: fixed; z-index: 10050; min-width: 155px; padding: .35rem; border: 1px solid var(--adam-border); border-radius: 10px; background: var(--adam-card); box-shadow: 0 12px 30px color-mix(in srgb, #000 22%, transparent); }
.jvba-overflow-menu[hidden] { display: none; }
.jvba-overflow-menu a, .jvba-overflow-menu button { display: flex; align-items: center; gap: .5rem; width: 100%; padding: .5rem .65rem; border: 0; border-radius: 7px; background: transparent; color: var(--adam-text); cursor: pointer; font: inherit; font-size: .82rem; text-align: left; }
.jvba-overflow-menu a, .jvba-overflow-menu a:hover, .jvba-overflow-menu a:focus-visible { text-decoration: none !important; }
.jvba-overflow-menu a:hover, .jvba-overflow-menu a:focus-visible, .jvba-overflow-menu button:hover, .jvba-overflow-menu button:focus-visible { background:var(--jvba-soft); color:var(--adam-text); outline:none; }
.jvba-overflow-menu form { margin: 0; }
/* Tokens form */
.jvba-token-section { margin-bottom:1rem; padding:1rem 1.1rem; border:1px solid var(--adam-border); border-radius:14px; background:var(--adam-card); }
.jvba-tokens { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:.85rem; }
.jvba-field { min-width:0; padding:.75rem; border:1px solid var(--adam-border-softer,var(--adam-border)); border-radius:11px; background:var(--adam-surface-2,var(--adam-bg)); }
.jvba-field label { display:block; margin-bottom:.35rem; color:var(--adam-text-2,var(--adam-text)); font-size:.75rem; font-weight:750; }
.jvba-field input, .jvba-field select, .jvba-field textarea { width:100%; min-height:42px; padding:.55rem .65rem; border:1px solid var(--adam-border-2,var(--adam-border)); border-radius:9px; color:var(--adam-text); background:var(--adam-card); font:inherit; font-size:.82rem; }
.jvba-color-wrap { display:flex; gap:.45rem; align-items:center; }
.jvba-color-wrap input[type=color] { width:42px; height:42px; flex:0 0 auto; padding:3px; border:1px solid var(--adam-border); border-radius:9px; background:var(--adam-card); cursor:pointer; }
.jvba-group-title { margin:0 0 .75rem; color:var(--jvba-accent-text); font-size:.69rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
.theme-dark .jvba { --jvba-accent-text:#b9afff; }
@media(prefers-color-scheme:dark){ html:not(.theme-light):not(.theme-dark) .jvba { --jvba-accent-text:#b9afff; } }
.jvba-form-actions { position:sticky; bottom:0; display:flex; justify-content:flex-end; margin-top:1rem; padding:.8rem; border:1px solid var(--adam-border); border-radius:12px; background:color-mix(in srgb,var(--adam-card) 92%,transparent); box-shadow:0 -8px 22px rgba(17,24,39,.06); backdrop-filter:blur(8px); }
@media(max-width:720px){
  .jvba-hero { align-items:flex-start; flex-direction:column; padding:1rem; border-radius:16px; }
  .jvba-hero__intro { align-items:flex-start; }
  .jvba-hero__mark { width:48px; height:48px; border-radius:14px; }
  .jvba-actions, .jvba-actions .jvba-btn { width:100%; }
  .jvba-stats { grid-template-columns:minmax(0,1fr); }
  .jvba-toolbar { align-items:stretch; flex-direction:column; }
  .jvba-search, .jvba-toolbar>.jvba-actions { min-width:0; width:100%; }
  .jvba-toolbar>.jvba-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
  .jvba-toolbar>.jvba-actions .jvba-hint { grid-column:1/-1; text-align:center; }
  .jvba-table-wrap { overflow:visible; border:0; background:transparent; box-shadow:none; }
  .jvba-table, .jvba-table tbody, .jvba-table tr, .jvba-table td { display:block; width:100%; }
  .jvba-table thead { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); clip-path:inset(50%); white-space:nowrap; }
  .jvba-table tbody { display:grid; gap:.7rem; }
  .jvba-table tr { padding:.25rem .8rem; border:1px solid var(--adam-border); border-radius:13px; background:var(--adam-card); box-shadow:0 5px 18px rgba(17,24,39,.035); }
  .jvba-table td { display:grid; grid-template-columns:minmax(82px,.36fr) minmax(0,1fr); align-items:center; gap:.7rem; padding:.58rem 0; border-bottom:1px solid var(--adam-border-softer,var(--adam-border)); text-align:left!important; white-space:normal; }
  .jvba-table td:last-child { border-bottom:0; }
  .jvba-table td::before { color:var(--adam-muted,var(--adam-text-2)); content:attr(data-label); font-size:.63rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
  .jvba-actions-cell .jvba-overflow { justify-self:end; }
  .jvba-token-section { padding:.85rem; }
  .jvba-tokens { grid-template-columns:minmax(0,1fr); }
  .jvba-form-actions .jvba-btn { width:100%; }
}
@media(max-width:440px){.jvba-search{display:grid;grid-template-columns:minmax(0,1fr) auto}.jvba-search .jvba-btn:last-child{grid-column:1/-1}.jvba-card--guide{padding:.85rem}.jvba-hero__intro{gap:.75rem}}
@media(prefers-reduced-motion:reduce){.jvba-btn{transition:none}}
</style>
<?php
}
