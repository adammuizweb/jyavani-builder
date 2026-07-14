<?php
// /plugins/jyavani-builder/public/starters.php — built-in starter templates
declare(strict_types=1);

function jvb_starter_templates(): array {
    $el = function (string $type, array $settings = []): array {
        return ['id' => jvb_uid('e'), 'type' => $type, 'settings' => $settings];
    };
    $sec = function (array $settings, array $columns) {
        return [
            'id' => jvb_uid('s'),
            'settings' => $settings,
            'columns' => array_map(function ($c) {
                return ['id' => jvb_uid('c'), 'settings' => $c[0], 'elements' => $c[1]];
            }, $columns),
        ];
    };
    $w = function (float $d, ?float $t = null, ?float $m = null): array {
        return ['width' => array_filter(['d' => $d, 't' => $t, 'm' => $m], static fn($v) => $v !== null)];
    };

    return [
        // ---- Hero ----
        [
            'title' => 'Hero — Centered', 'type' => 'section',
            'layout' => $sec(
                ['layout' => 'full', 'bg_type' => 'gradient', 'bg_from' => 'primary', 'bg_to' => 'secondary', 'bg_angle' => 135,
                    'padding' => ['d' => ['t' => 120, 'b' => 120, 'unit' => 'px'], 'm' => ['t' => 64, 'b' => 64, 'unit' => 'px']]],
                [[$w(100), [
                    $el('heading', ['text' => 'Build Something Great', 'tag' => 'h1', 'color' => '#ffffff', 'align' => ['d' => 'center'], 'typography' => ['d' => ['size' => 56, 'weight' => '800'], 'm' => ['size' => 34]]]),
                    $el('richtext', ['content' => '<p style="text-align:center;color:rgba(255,255,255,.85);font-size:20px">A short, compelling subtitle that explains your value proposition in one breath.</p>']),
                    $el('spacer', ['height' => ['d' => 12]]),
                    $el('button', ['text' => 'Get Started', 'url' => '#', 'size' => 'lg', 'color' => 'accent', 'align' => ['d' => 'center']]),
                ]]]
            ),
        ],
        // ---- Features 3-col ----
        [
            'title' => 'Features — 3 Columns', 'type' => 'section',
            'layout' => $sec(
                ['layout' => 'boxed', 'padding' => ['d' => ['t' => 96, 'b' => 96, 'unit' => 'px']]],
                [
                    [$w(100), [
                        $el('heading', ['text' => 'Everything you need', 'tag' => 'h2', 'align' => ['d' => 'center'], 'typography' => ['d' => ['size' => 40, 'weight' => '800']]]),
                        $el('spacer', ['height' => ['d' => 24]]),
                    ]],
                    [$w(33.33, 100, 100), [$el('iconbox', ['icon' => 'zap', 'title' => 'Fast', 'text' => 'Optimized for speed from the ground up.', 'align' => ['d' => 'center']])]],
                    [$w(33.33, 100, 100), [$el('iconbox', ['icon' => 'shield', 'title' => 'Secure', 'text' => 'Built-in protection for your content.', 'align' => ['d' => 'center']])]],
                    [$w(33.34, 100, 100), [$el('iconbox', ['icon' => 'puzzle', 'title' => 'Extensible', 'text' => 'Hook into anything with the plugin API.', 'align' => ['d' => 'center']])]],
                ]
            ),
        ],
        // ---- CTA band ----
        [
            'title' => 'CTA — Band', 'type' => 'section',
            'layout' => $sec(
                ['layout' => 'full', 'bg_type' => 'color', 'bg_color' => 'secondary', 'padding' => ['d' => ['t' => 72, 'b' => 72, 'unit' => 'px']]],
                [
                    [$w(66.66, 100, 100), [
                        $el('heading', ['text' => 'Ready to get started?', 'tag' => 'h2', 'color' => '#ffffff', 'typography' => ['d' => ['size' => 34, 'weight' => '800']]]),
                        $el('richtext', ['content' => '<p style="color:rgba(255,255,255,.75)">Join thousands of happy users today.</p>']),
                    ]],
                    [$w(33.34, 100, 100), [
                        $el('spacer', ['height' => ['d' => 28]]),
                        $el('button', ['text' => 'Sign Up Free', 'url' => '#', 'color' => 'accent', 'align' => ['d' => 'right', 't' => 'left', 'm' => 'left']]),
                    ]],
                ]
            ),
        ],
        // ---- Pricing 3-col ----
        [
            'title' => 'Pricing — 3 Plans', 'type' => 'section',
            'layout' => $sec(
                ['layout' => 'boxed', 'bg_type' => 'color', 'bg_color' => 'alt', 'padding' => ['d' => ['t' => 96, 'b' => 96, 'unit' => 'px']]],
                [
                    [$w(100), [
                        $el('heading', ['text' => 'Simple pricing', 'tag' => 'h2', 'align' => ['d' => 'center'], 'typography' => ['d' => ['size' => 40, 'weight' => '800']]]),
                        $el('spacer', ['height' => ['d' => 24]]),
                    ]],
                    [$w(33.33, 100, 100), [$el('pricing', ['plan' => 'Starter', 'price' => '0', 'period' => '/mo', 'features' => "1 website\nCommunity support\nBasic elements", 'btn_text' => 'Start Free'])]],
                    [$w(33.33, 100, 100), [$el('pricing', ['plan' => 'Pro', 'price' => '49', 'period' => '/mo', 'features' => "10 websites\nPriority support\nAll elements\nDesign tokens", 'btn_text' => 'Go Pro', 'highlight' => true, 'badge' => 'Popular'])]],
                    [$w(33.34, 100, 100), [$el('pricing', ['plan' => 'Enterprise', 'price' => '199', 'period' => '/mo', 'features' => "Unlimited websites\nDedicated support\nCustom development\nSLA", 'btn_text' => 'Contact Sales'])]],
                ]
            ),
        ],
        // ---- Testimonial ----
        [
            'title' => 'Testimonial — Single', 'type' => 'section',
            'layout' => $sec(
                ['layout' => 'boxed', 'padding' => ['d' => ['t' => 80, 'b' => 80, 'unit' => 'px']]],
                [[$w(100), [
                    $el('testimonial', ['quote' => 'Jyavani Builder let us launch our campaign page in an afternoon. The design tokens keep everything on brand.', 'name' => 'Sarah Wijaya', 'role' => 'Marketing Lead, Acme Corp', 'rating' => 5, 'align' => ['d' => 'center']]),
                ]]]
            ),
        ],
        // ---- Full landing page ----
        [
            'title' => 'Landing Page — SaaS', 'type' => 'page',
            'layout' => ['v' => JVB_LAYOUT_VERSION, 'settings' => ['custom_css' => ''], 'sections' => '__INHERIT__'],
        ],
    ];
}

function jvb_seed_starter_templates(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `jvb_templates` WHERE is_starter = 1")->fetchColumn();
    if ($cnt > 0) return;

    $starters = jvb_starter_templates();
    $sections = [];
    foreach ($starters as $tpl) {
        if ($tpl['type'] === 'page') {
            // Assemble the page from the section starters above
            $tpl['layout']['sections'] = array_map(static fn($s) => $s['layout'], $sections);
        } else {
            $sections[] = $tpl;
        }
        jvb_save_template($pdo, $tpl['title'], $tpl['type'], $tpl['layout'], null);
        $pdo->prepare('UPDATE `jvb_templates` SET is_starter = 1 WHERE id = ?')->execute([(int)$pdo->lastInsertId()]);
    }
}
