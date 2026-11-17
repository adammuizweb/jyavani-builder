<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;
$check = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ' ' . $message . PHP_EOL;
    if (!$condition) $failures[] = $message;
};

$GLOBALS['jvb_preset_render_call'] = null;
$GLOBALS['jvb_preset_render_html'] = '<div class="preset-result">Rendered collection</div>';
function render_shortcode_preset(PDO $pdo, string|int $preset, array $overrides = [], array $context = []): string
{
    $GLOBALS['jvb_preset_render_call'] = compact('preset', 'overrides', 'context');
    return (string)$GLOBALS['jvb_preset_render_html'];
}

require_once $root . '/public/render.php';

$manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$check(($manifest['requires']['jyavani'] ?? null) === '>=2.3.160',
    'plugin requires the first Core release with the public Content Preset renderer');

$registry = jvb_element_types();
$definition = $registry['preset_collection'] ?? null;
$check(is_array($definition) && ($definition['group'] ?? '') === 'dynamic' && empty($definition['admin']),
    'Published Preset is a first-class unrestricted Dynamic element');
$fields = [];
foreach (($definition['fields'] ?? []) as $field) {
    if (is_array($field) && is_string($field['key'] ?? null)) $fields[$field['key']] = $field;
}
$check(($fields['preset_id']['type'] ?? '') === 'presetpicker'
    && ($fields['layout']['options']['grid'] ?? '') === 'Grid'
    && array_key_exists('', $fields['layout']['options'] ?? []),
    'element exposes the published-preset picker and bounded block styles');

$iconDefinition = $registry['icon'] ?? [];
$iconBoxDefinition = $registry['iconbox'] ?? [];
$check(($iconDefinition['defaults']['icon_pack'] ?? '') === 'core'
    && ($iconDefinition['fields'][0]['pack_key'] ?? '') === 'icon_pack'
    && ($iconBoxDefinition['defaults']['icon_pack'] ?? '') === 'core'
    && ($iconBoxDefinition['fields'][0]['pack_key'] ?? '') === 'icon_pack',
    'Icon and Icon Box expose a backward-compatible icon-pack selector');
$heroicons = jvb_available_heroicons();
$heroSvg = jvb_content_icon('heroicons', 'academic-cap', 32);
$heroSprite = (string)file_get_contents($root . '/assets/icon-packs/heroicons-outline.svg');
$heroLicense = (string)file_get_contents($root . '/assets/icon-packs/HEROICONS-LICENSE.txt');
$staticSources = array_column($manifest['static']['copy'] ?? [], 'to', 'from');
$check(count($heroicons) === 324
    && in_array('academic-cap', $heroicons, true)
    && in_array('chat-bubble-left-right', $heroicons, true)
    && in_array('map-pin', $heroicons, true)
    && str_contains($heroSvg, 'stroke="currentColor"')
    && str_contains($heroSvg, '<path')
    && !preg_match('~<(?:script|style|foreignObject)\b|\bon[a-z]+\s*=~i', $heroSprite)
    && str_contains($heroLicense, 'MIT License')
    && ($staticSources['assets/icon-packs/heroicons-outline.svg'] ?? '') === 'static/plugins/jyavani-builder/heroicons-outline.svg',
    'bundled Heroicons Outline catalog is complete, trusted, and color-adaptive');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, meta TEXT, type TEXT, status TEXT, is_deleted INTEGER)');
$pdo->exec('CREATE TABLE jvb_layouts (post_id INTEGER PRIMARY KEY, draft_json TEXT, published_json TEXT)');
$pdo->exec('CREATE TABLE jvb_templates (id INTEGER PRIMARY KEY, layout_json TEXT)');
$pdo->exec('CREATE TABLE jvb_revisions (id INTEGER PRIMARY KEY, layout_json TEXT)');
$insert = $pdo->prepare('INSERT INTO posts (id, title, slug, meta, type, status, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?)');
$insert->execute([1, 'Published Cards', 'published-cards', '{"layout":"cards"}', 'sc_preset', 'published', 0]);
$insert->execute([2, 'Draft Preset', 'draft-preset', '{"layout":"grid"}', 'sc_preset', 'draft', 0]);
$insert->execute([3, 'Deleted Preset', 'deleted-preset', '{"layout":"list"}', 'sc_preset', 'published', 1]);
$insert->execute([4, 'Ordinary Post', 'ordinary-post', '{}', 'article', 'published', 0]);

$presets = jvb_published_presets($pdo);
$check($presets === [[
    'id' => 1,
    'title' => 'Published Cards',
    'slug' => 'published-cards',
    'layout' => 'cards',
]], 'picker inventory exposes only eligible published, non-deleted Presets');

for ($id = 5; $id <= 505; $id++) {
    $insert->execute([$id, 'Published ' . $id, 'published-' . $id, '{}', 'sc_preset', 'published', 0]);
}
$allPresets = jvb_published_presets($pdo);
$searchedPresets = jvb_published_presets($pdo, 'published-505');
$includedPresets = jvb_published_presets($pdo, '', 100, [505]);
$check(count($allPresets) === 100
    && array_column($searchedPresets, 'id') === [505]
    && in_array(505, array_column($includedPresets, 'id'), true),
    'picker inventory stays bounded while search and current-ID resolution reach later Presets');
$check(array_column(jvb_published_presets_by_slugs($pdo, ['published-cards']), 'id') === [1],
    'portable Preset slugs resolve to the local published identity');

$settings = [
    'preset_id' => 1,
    'layout' => 'grid',
    'show_header' => true,
    'title' => '<Latest>',
    'summary' => 'Selected content',
    'more_text' => 'View all',
    'more_url' => 'javascript:alert(1)',
];
$html = jvb_render_preset_collection($pdo, $settings, [
    'canvas' => true,
    'post' => ['id' => 42],
    'element_id' => 'el_1',
]);
$call = $GLOBALS['jvb_preset_render_call'];
$check(is_array($call)
    && ($call['preset'] ?? null) === 1
    && ($call['overrides'] ?? null) === ['layout' => 'grid']
    && ($call['context']['surface'] ?? '') === 'plugin.jyavani-builder'
    && ($call['context']['post_id'] ?? 0) === 42
    && ($call['context']['element_id'] ?? '') === 'el_1',
    'renderer composes through Core with a request-local block-style override and exact context');
$check(str_contains($html, '&lt;Latest&gt;')
    && str_contains($html, 'Rendered collection')
    && !str_contains($html, 'javascript:')
    && str_contains($html, 'jvb-preset-collection--grid'),
    'collection wrapper escapes editor copy, rejects unsafe links, and retains rendered Preset HTML');

jvb_render_preset_collection($pdo, ['preset_id' => 1, 'layout' => 'grid'], ['fallback' => true]);
$check(($GLOBALS['jvb_preset_render_call']['overrides'] ?? null) === ['layout' => 'grid', 'pagination' => '0'],
    'fallback snapshots suppress request-specific Preset pagination links');

$GLOBALS['jvb_preset_render_html'] = '';
$emptyCanvas = jvb_render_preset_collection($pdo, ['preset_id' => 999], ['canvas' => true]);
$emptyFrontend = jvb_render_preset_collection($pdo, ['preset_id' => 999], ['canvas' => false]);
$check(str_contains($emptyCanvas, 'unavailable or returned no content') && $emptyFrontend === '',
    'missing or empty Presets remain actionable in the canvas and silent on the public page');

$layout = [
    'sections' => [[
        'rows' => [[
            'cols' => [[
                'elements' => [[
                    'type' => 'preset_collection',
                    'settings' => ['preset_id' => 1],
                ]],
            ]],
        ]],
    ]],
];
$check(jvb_layout_preset_ids($layout) === [1]
    && jvb_layout_references_preset($layout, 1)
    && !jvb_layout_references_preset($layout, 10),
    'dependency detection matches the exact stable Preset ID');
$pdo->beginTransaction();
$lockedLayout = jvb_lock_layout_presets($pdo, $layout);
$pdo->rollBack();
$check(($lockedLayout['sections'][0]['rows'][0]['cols'][0]['elements'][0]['settings']['preset_slug'] ?? '') === 'published-cards',
    'published Preset references lock by ID and retain a portable slug for export');
$invalidLayout = $layout;
$invalidLayout['sections'][0]['rows'][0]['cols'][0]['elements'][0]['settings']['preset_id'] = 2;
$rejected = false;
$pdo->beginTransaction();
try {
    jvb_lock_layout_presets($pdo, $invalidLayout);
} catch (RuntimeException) {
    $rejected = true;
}
$pdo->rollBack();
$check($rejected, 'draft or unavailable Presets are rejected before a layout can be saved');
$json = json_encode($layout, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$pdo->prepare('INSERT INTO jvb_layouts (post_id, draft_json, published_json) VALUES (?, ?, ?)')->execute([42, $json, null]);
$check(jvb_preset_is_referenced($pdo, 1)
    && !jvb_preset_is_referenced($pdo, 2),
    'active Builder layouts protect referenced Presets from deletion');
$pdo->exec('DELETE FROM jvb_layouts');
$pdo->prepare('INSERT INTO jvb_revisions (id, layout_json) VALUES (?, ?)')->execute([1, $json]);
$check(jvb_preset_is_referenced($pdo, 1),
    'retained Builder revisions protect Presets required for a later restore');
$stringIdLayout = $layout;
$stringIdLayout['sections'][0]['rows'][0]['cols'][0]['elements'][0]['settings']['preset_id'] = '1';
$pdo->prepare('UPDATE jvb_revisions SET layout_json = ? WHERE id = 1')
    ->execute([json_encode($stringIdLayout, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
$check(jvb_preset_is_referenced($pdo, 1),
    'dependency protection recognizes legacy string-form Preset IDs');
$pdo->prepare('UPDATE posts SET slug = ? WHERE id = ?')->execute(['renamed-cards', 1]);
$check(jvb_preset_is_referenced($pdo, 1),
    'Preset slug changes do not break ID-based Builder dependencies');

$ajax = (string)file_get_contents($root . '/admin/ajax.php');
$builder = (string)file_get_contents($root . '/assets/builder.js');
$plugin = (string)file_get_contents($root . '/plugin.php');
$css = (string)file_get_contents($root . '/assets/frontend.css');
$check(str_contains($ajax, "'presets' => \$presets") && str_contains($ajax, "jvb_published_presets(\$pdo, '', 100"),
    'elements API publishes the server-validated Preset inventory');
$check(str_contains($builder, 'FR.presetpicker = function')
    && str_contains($builder, 'S.presets = els.presets || []')
    && str_contains($builder, "api('presets_search'")
    && str_contains($builder, 'Unavailable preset (ID '),
    'builder provides a bounded searchable picker and preserves missing selections for repair');
$check(str_contains($builder, 'S.iconPacks = els.icon_packs')
    && str_contains($builder, 'jvb-icon-pack-select')
    && str_contains($builder, '#jvb-hero-'),
    'builder icon picker switches between Core and bundled Heroicons without storing SVG markup');
$check(str_contains($builder, "api('presets_resolve'")
    && str_contains($builder, 'settings.preset_id = preset.id')
    && str_contains($builder, 'portable preset slug'),
    'layout import remaps portable Preset slugs instead of trusting foreign database IDs');
$check(str_contains($plugin, "add_action('admin_shortcode_preset_before_delete'")
    && str_contains($plugin, 'jvb_preset_is_referenced($pdo, $presetId)'),
    'Core Preset deletion participates in Jy Builder dependency protection');
$check(str_contains($plugin, 'static $renderedLayouts = [];')
    && str_contains($plugin, 'array_key_exists($renderKey, $renderedLayouts)')
    && str_contains($plugin, 'return $renderedLayouts[$renderKey] = $out;'),
    'singular rendering reuses the first output so nested Preset styles survive Core double filtering');
$check(str_contains($ajax, '$ownsTransaction = $newPost')
    && str_contains($ajax, 'if ($ownsTransaction) $pdo->beginTransaction();')
    && str_contains($ajax, 'if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();'),
    'standalone draft and publish creation roll back their post when Preset validation fails');
$check(str_contains($css, '.jvb-preset-collection__head')
    && str_contains($css, '.jvb-preset-collection--empty'),
    'Preset blocks include responsive frontend and canvas presentation');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " Jy Builder Preset element contract check(s) failed.\n");
    exit(1);
}

echo "Jy Builder Preset element contract passed ({$checks} checks).\n";
