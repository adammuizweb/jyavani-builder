<?php
// /plugins/jyavani-builder/public/render.php — layout → HTML + scoped CSS
declare(strict_types=1);

function jvb_e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function jvb_uid(string $prefix = 'n'): string {
    return $prefix . '_' . bin2hex(random_bytes(4));
}

// ---------------- Element registry (single source of truth for palette + panel) ----------------

function jvb_element_types(): array {
    $types = [
        'heading' => [
            'label' => 'Heading', 'group' => 'basic', 'icon' => 'type',
            'defaults' => ['text' => 'Heading', 'tag' => 'h2', 'link' => '', 'color' => '', 'align' => ['d' => ''], 'typography' => ['d' => ['size' => '', 'weight' => '700']]],
            'inline' => 'text',
            'fields' => [
                ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
                ['key' => 'tag', 'label' => 'HTML Tag', 'type' => 'select', 'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6']],
                ['key' => 'link', 'label' => 'Link URL (optional)', 'type' => 'text'],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
                ['key' => 'color', 'label' => 'Text Color', 'type' => 'color', 'token' => true],
                ['key' => 'typography', 'label' => 'Typography', 'type' => 'typography', 'devices' => true],
                ['key' => 'margin', 'label' => 'Margin', 'type' => 'spacing4', 'devices' => true],
            ],
        ],
        'richtext' => [
            'label' => 'Rich Text', 'group' => 'basic', 'icon' => 'file-text',
            'defaults' => ['content' => '<p>Write your content here…</p>'],
            'inline' => 'content',
            'fields' => [
                ['key' => 'content', 'label' => 'Content', 'type' => 'richtext'],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
                ['key' => 'color', 'label' => 'Text Color', 'type' => 'color', 'token' => true],
                ['key' => 'typography', 'label' => 'Typography', 'type' => 'typography', 'devices' => true],
                ['key' => 'margin', 'label' => 'Margin', 'type' => 'spacing4', 'devices' => true],
            ],
        ],
        'image' => [
            'label' => 'Image', 'group' => 'basic', 'icon' => 'image',
            'defaults' => ['src' => '', 'alt' => '', 'caption' => '', 'link' => '', 'width_pct' => ['d' => 100], 'radius' => '', 'align' => ['d' => '']],
            'fields' => [
                ['key' => 'src', 'label' => 'Image', 'type' => 'media'],
                ['key' => 'alt', 'label' => 'Alt Text', 'type' => 'text'],
                ['key' => 'caption', 'label' => 'Caption', 'type' => 'text'],
                ['key' => 'link', 'label' => 'Link URL', 'type' => 'text'],
                ['key' => 'width_pct', 'label' => 'Width %', 'type' => 'slider', 'min' => 10, 'max' => 100, 'devices' => true],
                ['key' => 'radius', 'label' => 'Corner Radius (px)', 'type' => 'number'],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
            ],
        ],
        'button' => [
            'label' => 'Button', 'group' => 'basic', 'icon' => 'mouse-pointer',
            'defaults' => ['text' => 'Click Me', 'url' => '#', 'target' => '', 'btn_style' => 'solid', 'size' => 'md', 'color' => 'primary', 'align' => ['d' => 'left'], 'full' => false],
            'inline' => 'text',
            'fields' => [
                ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
                ['key' => 'url', 'label' => 'URL', 'type' => 'text'],
                ['key' => 'target', 'label' => 'Open in new tab', 'type' => 'toggle'],
                ['key' => 'btn_style', 'label' => 'Style', 'type' => 'select', 'options' => ['solid' => 'Solid', 'outline' => 'Outline', 'ghost' => 'Ghost']],
                ['key' => 'size', 'label' => 'Size', 'type' => 'select', 'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large']],
                ['key' => 'color', 'label' => 'Color (token or hex)', 'type' => 'color', 'token' => true],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
                ['key' => 'full', 'label' => 'Full width', 'type' => 'toggle'],
                ['key' => 'margin', 'label' => 'Margin', 'type' => 'spacing4', 'devices' => true],
            ],
        ],
        'icon' => [
            'label' => 'Icon', 'group' => 'basic', 'icon' => 'star',
            'defaults' => ['name' => 'star', 'size' => 32, 'color' => 'primary', 'link' => '', 'align' => ['d' => '']],
            'fields' => [
                ['key' => 'name', 'label' => 'Icon', 'type' => 'iconpicker'],
                ['key' => 'size', 'label' => 'Size (px)', 'type' => 'number'],
                ['key' => 'color', 'label' => 'Color', 'type' => 'color', 'token' => true],
                ['key' => 'link', 'label' => 'Link URL', 'type' => 'text'],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
            ],
        ],
        'spacer' => [
            'label' => 'Spacer', 'group' => 'basic', 'icon' => 'move-vertical',
            'defaults' => ['height' => ['d' => 40]],
            'fields' => [
                ['key' => 'height', 'label' => 'Height (px)', 'type' => 'slider', 'min' => 4, 'max' => 400, 'devices' => true],
            ],
        ],
        'divider' => [
            'label' => 'Divider', 'group' => 'basic', 'icon' => 'minus',
            'defaults' => ['width_pct' => 100, 'thickness' => 1, 'line_style' => 'solid', 'color' => 'border', 'align' => ['d' => '']],
            'fields' => [
                ['key' => 'width_pct', 'label' => 'Width %', 'type' => 'slider', 'min' => 5, 'max' => 100],
                ['key' => 'thickness', 'label' => 'Thickness (px)', 'type' => 'slider', 'min' => 1, 'max' => 20],
                ['key' => 'line_style', 'label' => 'Style', 'type' => 'select', 'options' => ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dotted', 'double' => 'Double']],
                ['key' => 'color', 'label' => 'Color', 'type' => 'color', 'token' => true],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
                ['key' => 'margin', 'label' => 'Margin', 'type' => 'spacing4', 'devices' => true],
            ],
        ],
        'video' => [
            'label' => 'Video', 'group' => 'basic', 'icon' => 'play',
            'defaults' => ['src' => '', 'ratio' => '16/9', 'facade' => true, 'radius' => ''],
            'fields' => [
                ['key' => 'src', 'label' => 'YouTube / Vimeo URL', 'type' => 'text'],
                ['key' => 'ratio', 'label' => 'Aspect Ratio', 'type' => 'select', 'options' => ['16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1', '21/9' => '21:9']],
                ['key' => 'facade', 'label' => 'Lazy facade (load on click)', 'type' => 'toggle'],
                ['key' => 'radius', 'label' => 'Corner Radius (px)', 'type' => 'number'],
            ],
        ],
        'html' => [
            'label' => 'HTML', 'group' => 'advanced', 'icon' => 'code', 'admin' => true,
            'defaults' => ['html' => '<div>Custom HTML</div>'],
            'fields' => [
                ['key' => 'html', 'label' => 'HTML Code', 'type' => 'code', 'mode' => 'htmlmixed'],
            ],
        ],
        'shortcode' => [
            'label' => 'Shortcode', 'group' => 'advanced', 'icon' => 'braces', 'admin' => true,
            'defaults' => ['shortcode' => ''],
            'fields' => [
                ['key' => 'shortcode', 'label' => 'Shortcode', 'type' => 'text'],
            ],
        ],
        'accordion' => [
            'label' => 'Accordion', 'group' => 'content', 'icon' => 'list-collapse',
            'defaults' => ['items' => [['title' => 'Item 1', 'content' => '<p>Content…</p>'], ['title' => 'Item 2', 'content' => '<p>Content…</p>']], 'first_open' => true, 'icon_color' => 'primary'],
            'fields' => [
                ['key' => 'items', 'label' => 'Items', 'type' => 'repeater', 'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'content', 'label' => 'Content', 'type' => 'richtext'],
                ], 'item_label' => 'title'],
                ['key' => 'first_open', 'label' => 'First item open', 'type' => 'toggle'],
                ['key' => 'icon_color', 'label' => 'Icon Color', 'type' => 'color', 'token' => true],
            ],
        ],
        'tabs' => [
            'label' => 'Tabs', 'group' => 'content', 'icon' => 'panel-top',
            'defaults' => ['items' => [['title' => 'Tab 1', 'content' => '<p>Content…</p>'], ['title' => 'Tab 2', 'content' => '<p>Content…</p>']], 'color' => 'primary'],
            'fields' => [
                ['key' => 'items', 'label' => 'Tabs', 'type' => 'repeater', 'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'content', 'label' => 'Content', 'type' => 'richtext'],
                ], 'item_label' => 'title'],
                ['key' => 'color', 'label' => 'Active Color', 'type' => 'color', 'token' => true],
            ],
        ],
        'counter' => [
            'label' => 'Counter', 'group' => 'content', 'icon' => 'hash',
            'defaults' => ['number' => 100, 'prefix' => '', 'suffix' => '+', 'duration' => 1500, 'color' => 'primary', 'align' => ['d' => 'center'], 'typography' => ['d' => ['size' => 48, 'weight' => '700']]],
            'fields' => [
                ['key' => 'number', 'label' => 'Number', 'type' => 'number'],
                ['key' => 'prefix', 'label' => 'Prefix', 'type' => 'text'],
                ['key' => 'suffix', 'label' => 'Suffix', 'type' => 'text'],
                ['key' => 'duration', 'label' => 'Duration (ms)', 'type' => 'number'],
                ['key' => 'color', 'label' => 'Color', 'type' => 'color', 'token' => true],
                ['key' => 'typography', 'label' => 'Typography', 'type' => 'typography', 'devices' => true],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
            ],
        ],
        'countdown' => [
            'label' => 'Countdown', 'group' => 'content', 'icon' => 'timer',
            'defaults' => ['target' => '', 'labels' => true, 'color' => 'primary', 'align' => ['d' => 'center']],
            'fields' => [
                ['key' => 'target', 'label' => 'Target Date', 'type' => 'datetime'],
                ['key' => 'labels', 'label' => 'Show labels', 'type' => 'toggle'],
                ['key' => 'color', 'label' => 'Color', 'type' => 'color', 'token' => true],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
            ],
        ],
        'gallery' => [
            'label' => 'Gallery', 'group' => 'content', 'icon' => 'grid-3x3',
            'defaults' => ['images' => [], 'columns' => ['d' => 3, 't' => 2, 'm' => 1], 'gap' => 12, 'radius' => '', 'lightbox' => true],
            'fields' => [
                ['key' => 'images', 'label' => 'Images', 'type' => 'gallery'],
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'slider', 'min' => 1, 'max' => 6, 'devices' => true],
                ['key' => 'gap', 'label' => 'Gap (px)', 'type' => 'number'],
                ['key' => 'radius', 'label' => 'Corner Radius (px)', 'type' => 'number'],
                ['key' => 'lightbox', 'label' => 'Lightbox', 'type' => 'toggle'],
            ],
        ],
        'testimonial' => [
            'label' => 'Testimonial', 'group' => 'content', 'icon' => 'quote',
            'defaults' => ['quote' => 'This product changed everything for us.', 'name' => 'Jane Doe', 'role' => 'CEO, Company', 'avatar' => '', 'rating' => 5, 'color' => 'primary', 'align' => ['d' => '']],
            'fields' => [
                ['key' => 'quote', 'label' => 'Quote', 'type' => 'textarea'],
                ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'role', 'label' => 'Role / Title', 'type' => 'text'],
                ['key' => 'avatar', 'label' => 'Avatar', 'type' => 'media'],
                ['key' => 'rating', 'label' => 'Rating (0-5)', 'type' => 'slider', 'min' => 0, 'max' => 5],
                ['key' => 'color', 'label' => 'Accent Color', 'type' => 'color', 'token' => true],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
            ],
        ],
        'pricing' => [
            'label' => 'Pricing', 'group' => 'content', 'icon' => 'tag',
            'defaults' => ['plan' => 'Pro', 'price' => '49', 'currency' => '$', 'period' => '/mo', 'features' => "Feature one\nFeature two\nFeature three", 'btn_text' => 'Get Started', 'btn_url' => '#', 'highlight' => false, 'badge' => 'Popular', 'color' => 'primary'],
            'fields' => [
                ['key' => 'plan', 'label' => 'Plan Name', 'type' => 'text'],
                ['key' => 'price', 'label' => 'Price', 'type' => 'text'],
                ['key' => 'currency', 'label' => 'Currency', 'type' => 'text'],
                ['key' => 'period', 'label' => 'Period', 'type' => 'text'],
                ['key' => 'features', 'label' => 'Features (one per line)', 'type' => 'textarea'],
                ['key' => 'btn_text', 'label' => 'Button Text', 'type' => 'text'],
                ['key' => 'btn_url', 'label' => 'Button URL', 'type' => 'text'],
                ['key' => 'highlight', 'label' => 'Highlight this plan', 'type' => 'toggle'],
                ['key' => 'badge', 'label' => 'Badge Text', 'type' => 'text', 'show_if' => ['highlight' => true]],
                ['key' => 'color', 'label' => 'Accent Color', 'type' => 'color', 'token' => true],
            ],
        ],
        'iconbox' => [
            'label' => 'Icon Box', 'group' => 'content', 'icon' => 'box',
            'defaults' => ['icon' => 'zap', 'title' => 'Feature', 'text' => 'Describe this feature briefly.', 'url' => '', 'layout' => 'top', 'color' => 'primary', 'align' => ['d' => '']],
            'fields' => [
                ['key' => 'icon', 'label' => 'Icon', 'type' => 'iconpicker'],
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'text', 'label' => 'Text', 'type' => 'textarea'],
                ['key' => 'url', 'label' => 'Link URL (optional)', 'type' => 'text'],
                ['key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'options' => ['top' => 'Icon top', 'left' => 'Icon left']],
                ['key' => 'color', 'label' => 'Icon Color', 'type' => 'color', 'token' => true],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
            ],
        ],
        'posts' => [
            'label' => 'Posts Grid', 'group' => 'dynamic', 'icon' => 'newspaper',
            'defaults' => ['count' => 3, 'columns' => ['d' => 3, 't' => 2, 'm' => 1], 'category' => '', 'order' => 'newest', 'show_image' => true, 'show_excerpt' => true, 'color' => 'primary'],
            'fields' => [
                ['key' => 'count', 'label' => 'Number of posts', 'type' => 'slider', 'min' => 1, 'max' => 24],
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'slider', 'min' => 1, 'max' => 6, 'devices' => true],
                ['key' => 'category', 'label' => 'Category slug (optional)', 'type' => 'text'],
                ['key' => 'order', 'label' => 'Order', 'type' => 'select', 'options' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'title' => 'Title A-Z']],
                ['key' => 'show_image', 'label' => 'Show thumbnail', 'type' => 'toggle'],
                ['key' => 'show_excerpt', 'label' => 'Show excerpt', 'type' => 'toggle'],
                ['key' => 'color', 'label' => 'Accent Color', 'type' => 'color', 'token' => true],
            ],
        ],
        'form' => [
            'label' => 'Form', 'group' => 'dynamic', 'icon' => 'clipboard-list',
            'defaults' => ['form_id' => 0],
            'fields' => [
                ['key' => 'form_id', 'label' => 'Form (Form Builder plugin)', 'type' => 'formpicker'],
            ],
        ],
    ];
    if (function_exists('apply_filters')) {
        $types = apply_filters('jvb_elements', $types);
    }
    return is_array($types) ? $types : [];
}

// ---------------- Device value resolution (d → t → m inheritance) ----------------

function jvb_dev($val, string $device, $fallback = null) {
    if (!is_array($val)) return $val !== null && $val !== '' ? $val : $fallback;
    $order = $device === 'm' ? ['m', 't', 'd'] : ($device === 't' ? ['t', 'd'] : ['d']);
    foreach ($order as $d) {
        if (isset($val[$d]) && $val[$d] !== '' && $val[$d] !== null) return $val[$d];
    }
    return $fallback;
}

// Token-aware color: "primary" → var(--jvb-primary), "#fff" → as-is, "" → ''
function jvb_color($v): string {
    $v = trim((string)$v);
    if ($v === '') return '';
    if (preg_match('/^[a-z0-9_-]+$/i', $v) && !preg_match('/^[0-9a-f]{3,8}$/i', $v)) {
        return 'var(--jvb-' . $v . ')';
    }
    return $v;
}

// ---------------- CSS generation ----------------

// 4-side spacing: {d:{t,r,b,l,unit}, t:{…}, m:{…}} → "padding:…" per device
function jvb_css_spacing($spacing, string $prop, string $device): string {
    if (!is_array($spacing)) return '';
    $v = jvb_dev($spacing, $device);
    if (!is_array($v)) return '';
    $unit = preg_match('/^(px|rem|em|%)$/', (string)($v['unit'] ?? '')) ? $v['unit'] : 'px';
    $sides = [];
    foreach (['t' => 'top', 'r' => 'right', 'b' => 'bottom', 'l' => 'left'] as $k => $name) {
        $n = $v[$k] ?? null;
        if ($n === null || $n === '') continue;
        $sides[] = $prop . '-' . $name . ':' . (is_numeric($n) ? $n . $unit : $n);
    }
    return $sides ? implode(';', $sides) . ';' : '';
}

function jvb_css_typography($typo, string $device): string {
    if (!is_array($typo)) return '';
    $v = jvb_dev($typo, $device);
    if (!is_array($v)) return '';
    $out = '';
    if (isset($v['size']) && $v['size'] !== '') {
        $unit = preg_match('/^(px|rem|em)$/', (string)($v['unit'] ?? '')) ? $v['unit'] : 'px';
        $out .= 'font-size:' . $v['size'] . $unit . ';';
    }
    if (!empty($v['weight'])) $out .= 'font-weight:' . (int)$v['weight'] . ';';
    if (!empty($v['line'])) $out .= 'line-height:' . $v['line'] . ';';
    if (isset($v['spacing']) && $v['spacing'] !== '') $out .= 'letter-spacing:' . $v['spacing'] . 'px;';
    if (!empty($v['transform']) && in_array($v['transform'], ['uppercase', 'lowercase', 'capitalize', 'none'], true)) {
        $out .= 'text-transform:' . $v['transform'] . ';';
    }
    return $out;
}

// Collect per-node CSS rules for one device set. Returns ['base' => [], 't' => [], 'm' => []]
function jvb_node_css(array $s, string $kind): array {
    $rules = ['d' => '', 't' => '', 'm' => ''];
    foreach (['d', 't', 'm'] as $dev) {
        $r = '';
        // spacing — section padding goes to .jvb-section__inner (handled in jvb_layout_css)
        if ($kind !== 'section') {
            $r .= jvb_css_spacing($s['spacing']['padding'] ?? ($s['padding'] ?? null), 'padding', $dev);
        }
        $r .= jvb_css_spacing($s['spacing']['margin'] ?? ($s['margin'] ?? null), 'margin', $dev);
        // alignment
        $al = jvb_dev($s['align'] ?? null, $dev);
        if ($al !== null && in_array($al, ['left', 'center', 'right', 'justify'], true)) $r .= 'text-align:' . $al . ';';
        // typography
        $r .= jvb_css_typography($s['typography'] ?? null, $dev);
        // width (elements)
        if ($kind === 'element') {
            $w = jvb_dev($s['width_pct'] ?? null, $dev);
            if ($w !== null && is_numeric($w) && (float)$w < 100) $r .= 'width:' . (float)$w . '%;';
        }
        // colors (device-independent but allowed here for simplicity — only emit on desktop)
        if ($dev === 'd') {
            if (($c = jvb_color($s['color'] ?? '')) !== '') $r .= 'color:' . $c . ';';
            if (($c = jvb_color($s['bg_color'] ?? '')) !== '') $r .= 'background-color:' . $c . ';';
        }
        // visibility
        $hide = $s['hide_on'] ?? [];
        if (is_array($hide)) {
            if ($dev === 't' && in_array('tablet', $hide, true)) $r .= 'display:none!important;';
            if ($dev === 'm' && in_array('mobile', $hide, true)) $r .= 'display:none!important;';
            if ($dev === 'd' && in_array('desktop', $hide, true)) $r .= 'display:none!important;';
        }
        $rules[$dev] = $r;
    }
    return $rules;
}

function jvb_wrap_css(string $selector, array $rules): string {
    $out = '';
    if ($rules['d'] !== '') $out .= $selector . '{' . $rules['d'] . '}';
    if ($rules['t'] !== '') $out .= '@media(max-width:' . JVB_BP_TABLET . 'px){' . $selector . '{' . $rules['t'] . '}}';
    if ($rules['m'] !== '') $out .= '@media(max-width:' . JVB_BP_MOBILE . 'px){' . $selector . '{' . $rules['m'] . '}}';
    return $out;
}

// Column width rules (flex-basis percentages)
function jvb_column_css(array $s, string $sel): string {
    $out = '';
    foreach (['d', 't', 'm'] as $dev) {
        $w = jvb_dev($s['width'] ?? null, $dev, $dev === 'd' ? 100 : null);
        if ($w === null || !is_numeric($w)) continue;
        // flex-grow ratio: distributes row width proportionally after the flex
        // gap is subtracted — width% would overflow the row and wrap columns.
        $rule = $sel . '{flex-grow:' . (float)$w . ';}';
        if ($dev === 't') $out .= '@media(max-width:' . JVB_BP_TABLET . 'px){' . $rule . '}';
        elseif ($dev === 'm') $out .= '@media(max-width:' . JVB_BP_MOBILE . 'px){' . $rule . '}';
        else $out .= $rule;
    }
    return $out;
}

// Section background + overlay CSS
// Type is inferred from what's filled: image > gradient > color
function jvb_section_bg_css(array $s, string $sel): string {
    $out = '';
    $hasImage = !empty($s['bg_image']);
    $hasGradient = jvb_color($s['bg_from'] ?? '') !== '' && jvb_color($s['bg_to'] ?? '') !== '';
    $hasColor = jvb_color($s['bg_color'] ?? '') !== '';

    if ($hasImage) {
        $size = in_array($s['bg_size'] ?? '', ['cover', 'contain', 'auto'], true) ? $s['bg_size'] : 'cover';
        $pos = preg_match('/^[a-z0-9% ]+$/i', (string)($s['bg_position'] ?? '')) ? $s['bg_position'] : 'center';
        $att = ($s['bg_attachment'] ?? '') === 'fixed' ? 'fixed' : 'scroll';
        $out .= $sel . '{background-image:url(' . jvb_e($s['bg_image']) . ');background-size:' . $size . ';background-position:' . $pos . ';background-attachment:' . $att . ';}';
        // bg_color as fallback behind image
        if ($hasColor) $out .= $sel . '{background-color:' . jvb_color($s['bg_color']) . ';}';
    } elseif ($hasGradient) {
        $from = jvb_color($s['bg_from']);
        $to = jvb_color($s['bg_to']);
        $angle = (int)($s['bg_angle'] ?? 135);
        $out .= $sel . '{background:linear-gradient(' . $angle . 'deg,' . $from . ',' . $to . ');}';
    } elseif ($hasColor) {
        $out .= $sel . '{background-color:' . jvb_color($s['bg_color']) . ';}';
    }
    // overlay
    if (($oc = jvb_color($s['overlay_color'] ?? '')) !== '' && (float)($s['overlay_opacity'] ?? 0) > 0) {
        $out .= $sel . '>.jvb-section__overlay{background:' . $oc . ';opacity:' . min(1, max(0, (float)$s['overlay_opacity'])) . ';}';
    }
    // min height
    if (!empty($s['min_height'])) {
        $mh = is_numeric($s['min_height']) ? $s['min_height'] . 'px' : $s['min_height'];
        $out .= $sel . '>.jvb-section__inner{min-height:' . jvb_e($mh) . ';}';
    }
    return $out;
}

// Shape divider SVG (top/bottom)
function jvb_shape_svg(string $type, string $color, bool $flip = false): string {
    $fill = jvb_color($color) ?: 'var(--jvb-surface)';
    $paths = [
        'wave'     => '<path d="M0,64 C120,112 240,16 360,48 C480,80 600,128 720,96 C840,64 960,32 1080,48 C1200,64 1320,96 1440,80 L1440,128 L0,128 Z"/>',
        'tilt'     => '<path d="M0,96 L1440,32 L1440,128 L0,128 Z"/>',
        'curve'    => '<path d="M0,96 Q720,0 1440,96 L1440,128 L0,128 Z"/>',
        'triangle' => '<path d="M0,128 L720,16 L1440,128 Z"/>',
        'zigzag'   => '<path d="M0,96 L120,48 L240,96 L360,48 L480,96 L600,48 L720,96 L840,48 L960,96 L1080,48 L1200,96 L1320,48 L1440,96 L1440,128 L0,128 Z"/>',
    ];
    if (!isset($paths[$type])) return '';
    $tf = $flip ? ' transform="rotate(180 720 64)"' : '';
    return '<svg class="jvb-shape" viewBox="0 0 1440 128" preserveAspectRatio="none" aria-hidden="true"' . $tf . '><g fill="' . jvb_e($fill) . '"' . $tf . '>' . $paths[$type] . '</g></svg>';
}

// ---------------- Node attributes (animation, class, id) ----------------

function jvb_node_attrs(array $s, string $extraClass = ''): string {
    $cls = trim($extraClass . ' ' . (string)($s['class'] ?? ''));
    $out = $cls !== '' ? ' class="' . jvb_e($cls) . '"' : '';
    if (!empty($s['css_id']) && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', (string)$s['css_id'])) {
        $out .= ' id="' . jvb_e($s['css_id']) . '"';
    }
    $anim = (string)($s['animation'] ?? '');
    $allowed = ['fade', 'fade-up', 'fade-down', 'fade-left', 'fade-right', 'zoom-in', 'flip-up'];
    if (in_array($anim, $allowed, true)) {
        $out .= ' data-jvb-anim="' . $anim . '"';
        if (!empty($s['anim_delay']) && is_numeric($s['anim_delay'])) $out .= ' data-jvb-delay="' . (int)$s['anim_delay'] . '"';
    }
    return $out;
}

// ---------------- Lucide icons (plugin set first, then core set) ----------------

function jvb_lucide_dirs(): array {
    return [
        __DIR__ . '/../assets/icons/',
        dirname(__DIR__, 3) . '/public/static/icons/lucide/',
    ];
}

function jvb_lucide(string $name, int $size = 24): string {
    static $cache = [];
    $name = preg_replace('/[^a-z0-9-]/', '', strtolower($name));
    if ($name === '') $name = 'circle';
    if (isset($cache[$name])) return str_replace('%%SIZE%%', (string)$size, $cache[$name]);
    $svg = '';
    foreach (jvb_lucide_dirs() as $dir) {
        $file = $dir . $name . '.svg';
        if (is_file($file)) { $svg = (string)file_get_contents($file); break; }
    }
    if ($svg === '') {
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>';
    }
    // normalize: strip width/height, enforce currentColor
    $svg = preg_replace('/<svg[^>]*>/', '<svg viewBox="0 0 24 24" width="%%SIZE%%" height="%%SIZE%%" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">', $svg, 1);
    $cache[$name] = $svg;
    return str_replace('%%SIZE%%', (string)$size, $svg);
}

// All icon names available (for the picker).
function jvb_available_icons(): array {
    $names = [];
    foreach (jvb_lucide_dirs() as $dir) {
        foreach ((array)glob($dir . '*.svg') as $f) {
            $names[] = basename($f, '.svg');
        }
    }
    sort($names);
    return array_values(array_unique($names));
}

// ---------------- Video URL parsing ----------------

function jvb_video_embed(string $url): ?array {
    $url = trim($url);
    if ($url === '') return null;
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
        return ['type' => 'youtube', 'id' => $m[1], 'embed' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1', 'thumb' => 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg'];
    }
    if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $m)) {
        return ['type' => 'vimeo', 'id' => $m[1], 'embed' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1', 'thumb' => ''];
    }
    if (preg_match('#\.(mp4|webm|ogg)(\?.*)?$#i', $url)) {
        return ['type' => 'file', 'id' => '', 'embed' => $url, 'thumb' => ''];
    }
    return null;
}

// ---------------- Shortcode expansion (reuse core expanders) ----------------

function jvb_expand_shortcodes(string $html, PDO $pdo): string {
    if (function_exists('widget_expand_shortcodes')) $html = widget_expand_shortcodes($html, $pdo);
    if (function_exists('post_cat_shortcode_expand')) $html = post_cat_shortcode_expand($html, $pdo);
    if (function_exists('private_file_shortcode_expand')) $html = private_file_shortcode_expand($html, $pdo);
    if (function_exists('video_shortcode_expand')) $html = video_shortcode_expand($html, $pdo);
    return $html;
}

// ---------------- Element renderers ----------------

function jvb_render_element(PDO $pdo, array $el, array $ctx): string {
    $type = (string)($el['type'] ?? '');
    $s = is_array($el['settings'] ?? null) ? $el['settings'] : [];
    $id = (string)($el['id'] ?? '');

    // Plugin-registered elements first
    if (function_exists('apply_filters')) {
        $custom = apply_filters('jvb_render_element', null, $el, $ctx);
        if (is_string($custom) && $custom !== '') return $custom;
    }

    $inner = '';
    switch ($type) {
        case 'heading': {
            $tag = in_array($s['tag'] ?? '', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $s['tag'] : 'h2';
            $text = jvb_e($s['text'] ?? '');
            if (!empty($s['link'])) $text = '<a href="' . jvb_e($s['link']) . '">' . $text . '</a>';
            $inner = '<' . $tag . ' class="jvb-heading" data-jvb-inline="text">' . $text . '</' . $tag . '>';
            break;
        }
        case 'richtext': {
            $inner = '<div class="jvb-richtext" data-jvb-inline="content">' . jvb_expand_shortcodes((string)($s['content'] ?? ''), $pdo) . '</div>';
            break;
        }
        case 'image': {
            $src = trim((string)($s['src'] ?? ''));
            if ($src === '') { $inner = '<div class="jvb-image jvb-image--empty">No image selected</div>'; break; }
            $style = '';
            if (isset($s['radius']) && $s['radius'] !== '' && is_numeric($s['radius'])) $style = ' style="border-radius:' . (int)$s['radius'] . 'px"';
            $img = '<img src="' . jvb_e($src) . '" alt="' . jvb_e($s['alt'] ?? '') . '" loading="lazy"' . $style . '>';
            if (!empty($s['link'])) $img = '<a href="' . jvb_e($s['link']) . '">' . $img . '</a>';
            $cap = trim((string)($s['caption'] ?? '')) !== '' ? '<figcaption>' . jvb_e($s['caption']) . '</figcaption>' : '';
            $inner = '<figure class="jvb-image">' . $img . $cap . '</figure>';
            break;
        }
        case 'button': {
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $style = in_array($s['btn_style'] ?? '', ['solid', 'outline', 'ghost'], true) ? $s['btn_style'] : 'solid';
            $size = in_array($s['size'] ?? '', ['sm', 'md', 'lg'], true) ? $s['size'] : 'md';
            $target = !empty($s['target']) ? ' target="_blank" rel="noopener"' : '';
            $full = !empty($s['full']) ? ' jvb-btn--full' : '';
            $inner = '<a class="jvb-btn jvb-btn--' . $style . ' jvb-btn--' . $size . $full . '" href="' . jvb_e($s['url'] ?? '#') . '"' . $target . ' style="--jvb-btn:' . jvb_e($color) . '" data-jvb-inline="text">' . jvb_e($s['text'] ?? 'Button') . '</a>';
            break;
        }
        case 'icon': {
            $size = max(8, min(256, (int)($s['size'] ?? 32)));
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $svg = jvb_lucide((string)($s['name'] ?? 'star'), $size);
            $ico = '<span class="jvb-icon" style="color:' . jvb_e($color) . '">' . $svg . '</span>';
            if (!empty($s['link'])) $ico = '<a href="' . jvb_e($s['link']) . '">' . $ico . '</a>';
            $inner = $ico;
            break;
        }
        case 'spacer': {
            $h = (int)jvb_dev($s['height'] ?? null, 'd', 40);
            $inner = '<div class="jvb-spacer" style="height:' . max(0, $h) . 'px" aria-hidden="true"></div>';
            break;
        }
        case 'divider': {
            $w = min(100, max(5, (float)($s['width_pct'] ?? 100)));
            $th = max(1, min(20, (int)($s['thickness'] ?? 1)));
            $ls = in_array($s['line_style'] ?? '', ['solid', 'dashed', 'dotted', 'double'], true) ? $s['line_style'] : 'solid';
            $color = jvb_color($s['color'] ?? 'border') ?: 'var(--jvb-border)';
            $align = jvb_dev($s['align'] ?? null, 'd', '');
            $ml = $align === 'center' ? 'margin-left:auto;margin-right:auto;' : ($align === 'right' ? 'margin-left:auto;' : '');
            $inner = '<hr class="jvb-divider" style="width:' . $w . '%;border:0;border-top:' . $th . 'px ' . $ls . ' ' . jvb_e($color) . ';' . $ml . '">';
            break;
        }
        case 'video': {
            $v = jvb_video_embed((string)($s['src'] ?? ''));
            if ($v === null) { $inner = '<div class="jvb-video jvb-video--empty">No valid video URL</div>'; break; }
            $ratio = preg_match('/^\d+\/\d+$/', (string)($s['ratio'] ?? '')) ? $s['ratio'] : '16/9';
            $radius = (isset($s['radius']) && is_numeric($s['radius'])) ? 'border-radius:' . (int)$s['radius'] . 'px;overflow:hidden;' : '';
            if (!empty($s['facade']) && $v['type'] !== 'file' && $v['thumb'] !== '') {
                $inner = '<div class="jvb-video jvb-video--facade" data-embed="' . jvb_e($v['embed']) . '" style="aspect-ratio:' . $ratio . ';' . $radius . '">'
                    . '<img src="' . jvb_e($v['thumb']) . '" alt="" loading="lazy"><button class="jvb-video__play" aria-label="Play video">' . jvb_lucide('play', 32) . '</button></div>';
            } elseif ($v['type'] === 'file') {
                $inner = '<div class="jvb-video" style="aspect-ratio:' . $ratio . ';' . $radius . '"><video src="' . jvb_e($v['embed']) . '" controls></video></div>';
            } else {
                $inner = '<div class="jvb-video" style="aspect-ratio:' . $ratio . ';' . $radius . '"><iframe src="' . jvb_e($v['embed']) . '" loading="lazy" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe></div>';
            }
            break;
        }
        case 'html': {
            $inner = (string)($s['html'] ?? '');
            break;
        }
        case 'shortcode': {
            $inner = jvb_expand_shortcodes((string)($s['shortcode'] ?? ''), $pdo);
            break;
        }
        case 'accordion': {
            $items = is_array($s['items'] ?? null) ? $s['items'] : [];
            $color = jvb_color($s['icon_color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $out = '<div class="jvb-accordion" style="--jvb-acc:' . jvb_e($color) . '">';
            foreach ($items as $i => $it) {
                $open = ($i === 0 && !empty($s['first_open'])) ? ' open' : '';
                $out .= '<details class="jvb-accordion__item"' . $open . '><summary>' . jvb_e($it['title'] ?? '') . '</summary><div class="jvb-accordion__body">' . jvb_expand_shortcodes((string)($it['content'] ?? ''), $pdo) . '</div></details>';
            }
            $inner = $out . '</div>';
            break;
        }
        case 'tabs': {
            $items = is_array($s['items'] ?? null) ? $s['items'] : [];
            if (!$items) { $inner = ''; break; }
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $uid = 't' . substr(md5($id), 0, 6);
            $out = '<div class="jvb-tabs" style="--jvb-tab:' . jvb_e($color) . '"><div class="jvb-tabs__nav" role="tablist">';
            foreach ($items as $i => $it) {
                $out .= '<button role="tab" id="' . $uid . '-tab' . $i . '" aria-controls="' . $uid . '-pnl' . $i . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '"' . ($i === 0 ? ' class="is-active"' : '') . '>' . jvb_e($it['title'] ?? '') . '</button>';
            }
            $out .= '</div>';
            foreach ($items as $i => $it) {
                $out .= '<div role="tabpanel" id="' . $uid . '-pnl' . $i . '" aria-labelledby="' . $uid . '-tab' . $i . '" class="jvb-tabs__panel' . ($i === 0 ? ' is-active' : '') . '"' . ($i > 0 ? ' hidden' : '') . '>' . jvb_expand_shortcodes((string)($it['content'] ?? ''), $pdo) . '</div>';
            }
            $inner = $out . '</div>';
            break;
        }
        case 'counter': {
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $inner = '<div class="jvb-counter" style="color:' . jvb_e($color) . '"><span class="jvb-counter__num" data-jvb-count="' . (float)($s['number'] ?? 0) . '" data-jvb-dur="' . max(200, (int)($s['duration'] ?? 1500)) . '" data-jvb-prefix="' . jvb_e($s['prefix'] ?? '') . '" data-jvb-suffix="' . jvb_e($s['suffix'] ?? '') . '">' . jvb_e($s['prefix'] ?? '') . '0' . jvb_e($s['suffix'] ?? '') . '</span></div>';
            break;
        }
        case 'countdown': {
            $target = strtotime((string)($s['target'] ?? ''));
            if ($target === false) { $inner = '<div class="jvb-countdown jvb-countdown--empty">Set a target date</div>'; break; }
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $labels = !empty($s['labels']);
            $box = function (string $k, string $label) use ($labels): string {
                return '<div class="jvb-countdown__box"><span class="jvb-countdown__num" data-jvb-cd="' . $k . '">00</span>' . ($labels ? '<span class="jvb-countdown__label">' . $label . '</span>' : '') . '</div>';
            };
            $inner = '<div class="jvb-countdown" data-jvb-target="' . $target . '" style="--jvb-cd:' . jvb_e($color) . '">'
                . $box('d', 'Days') . $box('h', 'Hours') . $box('m', 'Minutes') . $box('s', 'Seconds') . '</div>';
            break;
        }
        case 'gallery': {
            $images = is_array($s['images'] ?? null) ? $s['images'] : [];
            if (!$images) { $inner = '<div class="jvb-gallery jvb-gallery--empty">No images</div>'; break; }
            $cols = (int)jvb_dev($s['columns'] ?? null, 'd', 3);
            $gap = is_numeric($s['gap'] ?? null) ? (int)$s['gap'] : 12;
            $radius = (isset($s['radius']) && is_numeric($s['radius'])) ? 'border-radius:' . (int)$s['radius'] . 'px;' : '';
            $lb = !empty($s['lightbox']);
            $out = '<div class="jvb-gallery" style="grid-template-columns:repeat(' . max(1, $cols) . ',1fr);gap:' . $gap . 'px">';
            foreach ($images as $img) {
                $url = (string)($img['url'] ?? '');
                if ($url === '') continue;
                $tag = $lb ? 'a' : 'div';
                $href = $lb ? ' href="' . jvb_e($url) . '" data-jvb-lightbox' : '';
                $out .= '<' . $tag . ' class="jvb-gallery__item"' . $href . '><img src="' . jvb_e($url) . '" alt="' . jvb_e($img['alt'] ?? '') . '" loading="lazy" style="' . $radius . '"></' . $tag . '>';
            }
            $inner = $out . '</div>';
            break;
        }
        case 'testimonial': {
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $stars = '';
            $r = max(0, min(5, (int)($s['rating'] ?? 0)));
            for ($i = 0; $i < 5; $i++) $stars .= '<span class="jvb-star' . ($i < $r ? ' is-on' : '') . '">★</span>';
            $avatar = !empty($s['avatar']) ? '<img class="jvb-testimonial__avatar" src="' . jvb_e($s['avatar']) . '" alt="' . jvb_e($s['name'] ?? '') . '" loading="lazy">' : '';
            $inner = '<figure class="jvb-testimonial" style="--jvb-tst:' . jvb_e($color) . '">'
                . '<div class="jvb-testimonial__stars">' . $stars . '</div>'
                . '<blockquote>' . jvb_e($s['quote'] ?? '') . '</blockquote>'
                . '<figcaption>' . $avatar . '<span><strong>' . jvb_e($s['name'] ?? '') . '</strong><small>' . jvb_e($s['role'] ?? '') . '</small></span></figcaption></figure>';
            break;
        }
        case 'pricing': {
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $hl = !empty($s['highlight']);
            $feats = '';
            foreach (preg_split('/\r?\n/', (string)($s['features'] ?? '')) as $f) {
                $f = trim($f);
                if ($f !== '') $feats .= '<li>' . jvb_lucide('check', 16) . '<span>' . jvb_e($f) . '</span></li>';
            }
            $badge = ($hl && trim((string)($s['badge'] ?? '')) !== '') ? '<div class="jvb-pricing__badge">' . jvb_e($s['badge']) . '</div>' : '';
            $inner = '<div class="jvb-pricing' . ($hl ? ' is-highlight' : '') . '" style="--jvb-prc:' . jvb_e($color) . '">' . $badge
                . '<div class="jvb-pricing__plan">' . jvb_e($s['plan'] ?? '') . '</div>'
                . '<div class="jvb-pricing__price"><span class="jvb-pricing__cur">' . jvb_e($s['currency'] ?? '') . '</span>' . jvb_e($s['price'] ?? '') . '<span class="jvb-pricing__per">' . jvb_e($s['period'] ?? '') . '</span></div>'
                . '<ul class="jvb-pricing__features">' . $feats . '</ul>'
                . '<a class="jvb-btn jvb-btn--solid jvb-btn--md jvb-btn--full" href="' . jvb_e($s['btn_url'] ?? '#') . '" style="--jvb-btn:' . jvb_e($color) . '">' . jvb_e($s['btn_text'] ?? 'Get Started') . '</a></div>';
            break;
        }
        case 'iconbox': {
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $layout = ($s['layout'] ?? '') === 'left' ? 'left' : 'top';
            $body = '<span class="jvb-iconbox__icon" style="color:' . jvb_e($color) . '">' . jvb_lucide((string)($s['icon'] ?? 'zap'), 32) . '</span>'
                . '<div class="jvb-iconbox__body"><h3 class="jvb-iconbox__title">' . jvb_e($s['title'] ?? '') . '</h3><p>' . jvb_e($s['text'] ?? '') . '</p></div>';
            $inner = '<div class="jvb-iconbox jvb-iconbox--' . $layout . '">' . $body . '</div>';
            if (!empty($s['url'])) $inner = '<a class="jvb-iconbox-link" href="' . jvb_e($s['url']) . '">' . $inner . '</a>';
            break;
        }
        case 'posts': {
            $inner = jvb_render_posts_element($pdo, $s);
            break;
        }
        case 'form': {
            $fid = (int)($s['form_id'] ?? 0);
            if ($fid > 0 && function_exists('fb_get_form') && function_exists('fb_render_form')) {
                $form = fb_get_form($pdo, $fid);
                if (is_array($form) && ($form['status'] ?? '') === 'active' && ($form['deleted_at'] ?? null) === null) {
                    $inner = '<div class="jvb-form">' . fb_render_form($pdo, $form) . '</div>';
                } else {
                    $inner = '<div class="jvb-form jvb-form--empty">Form not available</div>';
                }
            } else {
                $inner = '<div class="jvb-form jvb-form--empty">' . ($fid > 0 ? 'Form Builder plugin not active' : 'Select a form in the panel') . '</div>';
            }
            break;
        }
        default: {
            $inner = '';
        }
    }

    if ($inner === '') return '';
    $align = jvb_dev($s['align'] ?? null, 'd', '');
    $wrapCls = 'jvb-el jvb-el--' . preg_replace('/[^a-z0-9_-]/', '', $type);
    if ($align !== null && in_array($align, ['left', 'center', 'right'], true)) $wrapCls .= ' jvb-ta-' . $align;
    return '<div' . jvb_node_attrs($s, $wrapCls) . ' data-jvb="' . jvb_e($id) . '" data-jvb-type="' . jvb_e($type) . '">' . $inner . '</div>';
}

// Dynamic posts grid element
function jvb_render_posts_element(PDO $pdo, array $s): string {
    $count = max(1, min(24, (int)($s['count'] ?? 3)));
    $cols = max(1, min(6, (int)jvb_dev($s['columns'] ?? null, 'd', 3)));
    $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
    $order = match ($s['order'] ?? 'newest') { 'oldest' => 'p.created_at ASC', 'title' => 'p.title ASC', default => 'p.created_at DESC' };

    $sql = "SELECT p.id, p.title, p.slug, p.content, p.thumbnail, p.youtube, p.created_at FROM `posts` p";
    $args = [];
    $cat = trim((string)($s['category'] ?? ''));
    if ($cat !== '') {
        $sql .= " JOIN `post_categories` pc ON pc.post_id = p.id JOIN `categories` c ON c.id = pc.category_id AND c.slug = ? AND c.is_deleted = 0";
        $args[] = $cat;
    }
    $sql .= " WHERE p.type = 'article' AND p.status = 'published' AND p.is_deleted = 0 ORDER BY {$order} LIMIT " . $count;
    try {
        $st = $pdo->prepare($sql);
        $st->execute($args);
        $posts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return '<div class="jvb-posts jvb-posts--empty">Could not load posts</div>';
    }
    if (!$posts) return '<div class="jvb-posts jvb-posts--empty">No posts found</div>';

    $out = '<div class="jvb-posts" style="grid-template-columns:repeat(' . $cols . ',1fr);--jvb-pst:' . jvb_e($color) . '">';
    foreach ($posts as $p) {
        $url = function_exists('get_post_permalink') ? get_post_permalink($p) : '/' . rawurlencode((string)$p['slug']) . '/';
        $img = '';
        if (!empty($s['show_image'])) {
            $thumb = '';
            if (!empty($p['thumbnail']) && !str_starts_with((string)$p['thumbnail'], '/private/')) $thumb = (string)$p['thumbnail'];
            elseif (!empty($p['youtube']) && preg_match('#youtu\.be/([\w-]+)|[?&]v=([\w-]+)#', (string)$p['youtube'], $m)) {
                $vid = $m[1] !== '' ? $m[1] : $m[2];
                $thumb = 'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg';
            }
            $img = $thumb !== '' ? '<a class="jvb-posts__img" href="' . jvb_e($url) . '"><img src="' . jvb_e($thumb) . '" alt="" loading="lazy"></a>' : '';
        }
        $excerpt = '';
        if (!empty($s['show_excerpt'])) {
            $txt = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)$p['content'])));
            $excerpt = '<p>' . jvb_e(mb_strimwidth($txt, 0, 140, '…')) . '</p>';
        }
        $out .= '<article class="jvb-posts__card">' . $img
            . '<div class="jvb-posts__body"><h3><a href="' . jvb_e($url) . '">' . jvb_e($p['title']) . '</a></h3>'
            . $excerpt . '<a class="jvb-posts__more" href="' . jvb_e($url) . '">Read more →</a></div></article>';
    }
    return $out . '</div>';
}

// ---------------- Section / column / layout ----------------

function jvb_render_column(PDO $pdo, array $col, array $ctx): string {
    $s = is_array($col['settings'] ?? null) ? $col['settings'] : [];
    $id = (string)($col['id'] ?? '');
    $els = '';
    foreach ((array)($col['elements'] ?? []) as $el) {
        if (is_array($el)) $els .= jvb_render_element($pdo, $el, $ctx);
    }
    if ($els === '' && !empty($ctx['canvas'])) {
        $els = '<div class="jvb-col__dropzone"><div class="jvb-col__empty">+ Drop elements here</div></div>';
    }
    return '<div' . jvb_node_attrs($s, 'jvb-col') . ' data-jvb="' . jvb_e($id) . '" data-jvb-kind="col">' . $els . '</div>';
}

function jvb_render_row(PDO $pdo, array $row, array $ctx): string {
    $s = is_array($row['settings'] ?? null) ? $row['settings'] : [];
    $id = (string)($row['id'] ?? '');
    $gap = (int)($s['gap'] ?? 20);
    $align = in_array($s['align'] ?? '', ['center', 'start', 'end', 'space-between'], true) ? $s['align'] : '';
    $wrap = ($s['wrap'] ?? '') === 'wrap';
    $rowCls = 'jvb-row';
    if ($align === 'center') $rowCls .= ' jvb-row--center';
    elseif ($align === 'start') $rowCls .= ' jvb-row--start';
    elseif ($align === 'end') $rowCls .= ' jvb-row--end';
    elseif ($align === 'space-between') $rowCls .= ' jvb-row--between';
    if ($wrap) $rowCls .= ' jvb-row--wrap';
    $cols = '';
    foreach ((array)($row['cols'] ?? []) as $col) {
        if (is_array($col)) $cols .= jvb_render_column($pdo, $col, $ctx);
    }
    if ($cols === '' && !empty($ctx['canvas'])) {
        $cols = '<div class="jvb-col__dropzone"><div class="jvb-col__empty">+ Drop elements here</div></div>';
    }
    return '<div' . jvb_node_attrs($s, $rowCls) . ' data-jvb="' . jvb_e($id) . '" data-jvb-kind="row" style="--jvb-row-gap:' . $gap . 'px">' . $cols . '</div>';
}

function jvb_render_section(PDO $pdo, array $sec, array $ctx): string {
    $s = is_array($sec['settings'] ?? null) ? $sec['settings'] : [];
    $id = (string)($sec['id'] ?? '');
    $layout = in_array($s['layout'] ?? '', ['boxed', 'full', 'stretch'], true) ? $s['layout'] : 'boxed';

    $rowsHtml = '';
    foreach ((array)($sec['rows'] ?? []) as $row) {
        if (is_array($row)) $rowsHtml .= jvb_render_row($pdo, $row, $ctx);
    }
    if ($rowsHtml === '' && !empty($ctx['canvas'])) {
        $rowsHtml = '<div class="jvb-col__dropzone"><div class="jvb-section__empty">+ Add a row</div></div>';
    }

    $hasOverlay = jvb_color($s['overlay_color'] ?? '') !== '' && (float)($s['overlay_opacity'] ?? 0) > 0;
    $overlay = $hasOverlay ? '<div class="jvb-section__overlay" aria-hidden="true"></div>' : '';

    $shapeTop = '';
    if (!empty($s['shape_top']['type']) && $s['shape_top']['type'] !== 'none') {
        $st = $s['shape_top'];
        $shapeTop = '<div class="jvb-shape-wrap jvb-shape-wrap--top"' . (!empty($st['height']) ? ' style="height:' . (int)$st['height'] . 'px"' : '') . '>'
            . jvb_shape_svg((string)$st['type'], (string)($st['color'] ?? 'surface'), !empty($st['flip'])) . '</div>';
    }
    $shapeBottom = '';
    if (!empty($s['shape_bottom']['type']) && $s['shape_bottom']['type'] !== 'none') {
        $sb = $s['shape_bottom'];
        $shapeBottom = '<div class="jvb-shape-wrap jvb-shape-wrap--bottom"' . (!empty($sb['height']) ? ' style="height:' . (int)$sb['height'] . 'px"' : '') . '>'
            . jvb_shape_svg((string)$sb['type'], (string)($sb['color'] ?? 'surface'), empty($sb['flip'])) . '</div>';
    }

    return '<section' . jvb_node_attrs($s, 'jvb-section jvb-section--' . $layout) . ' data-jvb="' . jvb_e($id) . '" data-jvb-kind="section">'
        . $overlay . $shapeTop
        . '<div class="jvb-section__inner">' . $rowsHtml . '</div>'
        . $shapeBottom . '</section>';
}

// Collect all generated CSS for a layout (scoped under .jvb-page).
function jvb_layout_css(array $layout, array $tokens): string {
    $css = '';
    $walk = function (array $node, string $kind) use (&$walk, &$css): void {
        $s = is_array($node['settings'] ?? null) ? $node['settings'] : [];
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($node['id'] ?? ''));
        if ($id === '') return;
        $sel = '.jvb-page [data-jvb="' . $id . '"]';
        if ($kind === 'col') {
            $css .= jvb_column_css($s, $sel);
        }
        if ($kind === 'section') {
            $css .= jvb_section_bg_css($s, $sel);
            // Section padding goes to .jvb-section__inner (overrides default 80px/24px)
            $innerSel = $sel . '>.jvb-section__inner';
            foreach (['d', 't', 'm'] as $dev) {
                $padRule = jvb_css_spacing($s['padding'] ?? null, 'padding', $dev);
                if ($padRule !== '') {
                    if ($dev === 't') $css .= '@media(max-width:' . JVB_BP_TABLET . 'px){' . $innerSel . '{' . $padRule . '}}';
                    elseif ($dev === 'm') $css .= '@media(max-width:' . JVB_BP_MOBILE . 'px){' . $innerSel . '{' . $padRule . '}}';
                    else $css .= $innerSel . '{' . $padRule . '}';
                }
            }
        }
        $css .= jvb_wrap_css($sel, jvb_node_css($s, $kind === 'element' ? 'element' : $kind));
        foreach ((array)($node['rows'] ?? []) as $r) if (is_array($r)) $walk($r, 'row');
        foreach ((array)($node['cols'] ?? []) as $c) if (is_array($c)) $walk($c, 'col');
        foreach ((array)($node['elements'] ?? []) as $e) if (is_array($e)) $walk($e, 'element');
    };
    foreach ((array)($layout['sections'] ?? []) as $sec) {
        if (is_array($sec)) $walk($sec, 'section');
    }
    // Custom CSS (admin-authored; scoped by requiring .jvb-page prefix knowledge)
    $custom = trim((string)($layout['settings']['custom_css'] ?? ''));
    if ($custom !== '') {
        // strip closing tags for safety
        $custom = str_ireplace('</style', '', $custom);
        $css .= "\n/* custom */\n" . $custom;
    }
    return $css;
}

// Main entry: render full layout to HTML.
function jvb_render_layout(PDO $pdo, array $layout, array $post = [], array $opts = []): string {
    static $tokensCache = null;
    if ($tokensCache === null) $tokensCache = jvb_get_tokens($pdo);
    $tokens = $tokensCache;

    $ctx = ['tokens' => $tokens, 'post' => $post, 'canvas' => !empty($opts['canvas'])];

    $sections = '';
    foreach ((array)($layout['sections'] ?? []) as $sec) {
        if (is_array($sec)) $sections .= jvb_render_section($pdo, $sec, $ctx);
    }
    if ($sections === '' && !empty($opts['canvas'])) {
        $sections = '<div class="jvb-canvas-empty"><p>This page is empty.</p><p>Drag a section layout from the left panel to start building.</p></div>';
    }

    $css = jvb_tokens_css($tokens) . jvb_layout_css($layout, $tokens);
    $postId = (int)($post['id'] ?? 0);

    // Base component stylesheet, linked once per request (link-in-body is
    // supported by all browsers; wp_head fires before our content filter on pages).
    static $baseLinked = false;
    $baseLink = '';
    if (!$baseLinked && empty($opts['canvas'])) {
        $baseLinked = true;
        $baseLink = '<link rel="stylesheet" href="' . jvb_asset_url('frontend.css') . '">' . "\n";
    }

    // jvb-anim-on activates animation initial states only when JS is available.
    $script = '<script>(function(c){c.classList.add("jvb-anim-on")})(document.currentScript.parentNode)</script>';

    return $baseLink . '<div class="jvb-page" data-jvb-post="' . $postId . '">'
        . '<style>' . $css . '</style>'
        . $script
        . $sections
        . '</div>';
}
