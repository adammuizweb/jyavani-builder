<?php
declare(strict_types=1);

const JVB_BP_TABLET = 1024;
const JVB_BP_MOBILE = 767;

require_once dirname(__DIR__) . '/public/render.php';

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    if (!$ok) $failures[] = $message;
};

$pdo = new PDO('sqlite::memory:');
$registry = jvb_element_types();

$check(isset($registry['profile_quote'])
    && ($registry['button']['fields'][8]['pack_key'] ?? '') === 'icon_pack'
    && ($registry['card']['fields'][7]['pack_key'] ?? '') === 'action_icon_pack',
    'generic components integrate with the canonical icon-pack picker');

$profile = jvb_render_element($pdo, [
    'id' => 'profile_1',
    'type' => 'profile_quote',
    'settings' => ['heading' => 'Profile', 'quote' => '<p>Quote</p><script>alert(1)</script>', 'cta_text' => 'Unsafe', 'cta_url' => 'javascript:alert(1)'],
], []);
$check(str_contains($profile, 'jvb-profile-quote__quote')
    && str_contains($profile, 'aria-labelledby=')
    && !str_contains($profile, 'javascript:')
    && !str_contains($profile, '<script')
    && !str_contains($profile, 'fuel-'),
    'Profile Quote renders semantic theme-independent markup and rejects unsafe links');

$tabs = jvb_render_element($pdo, [
    'id' => 'tabs_1',
    'type' => 'tabs',
    'settings' => [
        'variant' => 'pills-card',
        'list_style' => 'numbered-rows',
        'items' => [['title' => 'One', 'content' => '<p>Body</p>', 'list_items' => [['text' => 'First']]]],
    ],
], []);
$check(str_contains($tabs, 'jvb-tabs--pills-card')
    && str_contains($tabs, 'tabindex="0"')
    && str_contains($tabs, 'jvb-tabs__marker'),
    'enhanced tabs render keyboard state and structured lists');

$slider = jvb_render_content_slider([
    'slides' => [[
        'title' => 'Slide',
        'image' => '//example.com/unsafe.jpg',
        'primary_label' => 'Unsafe',
        'primary_url' => 'javascript:alert(1)',
    ]],
]);
$check(str_contains($slider, 'data-jvb-content-slider')
    && !str_contains($slider, 'javascript:')
    && !str_contains($slider, '//example.com/unsafe.jpg'),
    'content slider rejects script and protocol-relative URLs');

$rail = jvb_render_card_rail([
    'rail_title_color' => 'primary',
    'cards' => [['title' => 'Card', 'url' => '/safe', 'image' => '/image.jpg', 'initial' => true]],
]);
$check(str_contains($rail, 'href="/safe"')
    && str_contains($rail, 'data-jvb-rail-initial')
    && str_contains($rail, '--jvb-rail-title:var(--jvb-primary)'),
    'card rail renders safe links, initial centering, and its independent title color');

$divider = jvb_render_element($pdo, [
    'id' => 'divider_1',
    'type' => 'divider',
    'settings' => ['width_pct' => ['d' => 50]],
], []);
$dividerCss = jvb_layout_css(['sections' => [[
    'id' => 'section_divider', 'settings' => [], 'rows' => [[
        'id' => 'row_divider', 'settings' => [], 'cols' => [[
            'id' => 'column_divider', 'settings' => ['width' => ['d' => 100]], 'elements' => [[
                'id' => 'divider_1', 'type' => 'divider', 'settings' => ['width_pct' => ['d' => 50]],
            ]],
        ]],
    ]],
]]], []);
$check(str_contains($divider, 'style="width:50%')
    && !str_contains($dividerCss, '[data-jvb="divider_1"]{width:50%'),
    'Divider accepts saved responsive values without applying its width twice');

$css = jvb_layout_css(['sections' => [[
    'id' => 'section_1',
    'settings' => [],
    'rows' => [[
        'id' => 'row_1',
        'settings' => ['max_width' => ['d' => '64rem'], 'margin' => ['d' => ['l' => 'auto', 'r' => 'auto']]],
        'cols' => [[
            'id' => 'column_1',
            'settings' => ['width' => ['d' => 100]],
            'elements' => [[
                'id' => 'button_1',
                'type' => 'button',
                'settings' => ['border_width' => ['d' => ['t' => 1], 'm' => ['t' => 2]], 'custom_css' => 'letter-spacing:1px'],
            ]],
        ]],
    ]],
]]], []);
$check(str_contains($css, 'max-width:64rem')
    && str_contains($css, 'margin-left:auto')
    && str_contains($css, 'border-top-width:1px')
    && str_contains($css, 'border-top-width:2px')
    && str_contains($css, '[data-jvb="button_1"]>.jvb-btn'),
    'generated styles support auto centering, max width, responsive borders, and button scoping');

if ($failures !== []) {
    fwrite(STDERR, 'Jy Builder component contract failed: ' . implode('; ', $failures) . PHP_EOL);
    exit(1);
}

echo "RESULT: ALL PASS\n";
