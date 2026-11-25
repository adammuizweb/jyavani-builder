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
            'defaults' => ['text' => 'Heading', 'tag' => 'h2', 'link' => '', 'color' => '', 'align' => ['d' => ''], 'typography' => ['d' => ['size' => '', 'weight' => '700']], 'inner_class' => '', 'inner_id' => ''],
            'inline' => 'text',
            'fields' => [
                ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
                ['key' => 'tag', 'label' => 'HTML Tag', 'type' => 'select', 'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6']],
                ['key' => 'link', 'label' => 'Link URL (optional)', 'type' => 'text'],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
                ['key' => 'color', 'label' => 'Text Color', 'type' => 'color', 'token' => true],
                ['key' => 'typography', 'label' => 'Typography', 'type' => 'typography', 'devices' => true],
                ['key' => 'inner_class', 'label' => 'Semantic CSS class (optional)', 'type' => 'text'],
                ['key' => 'inner_id', 'label' => 'Semantic HTML ID (optional)', 'type' => 'text'],
                ['key' => 'margin', 'label' => 'Margin', 'type' => 'spacing4', 'devices' => true],
            ],
        ],
        'richtext' => [
            'label' => 'Rich Text', 'group' => 'basic', 'icon' => 'file-text',
            'defaults' => ['content' => '<p>Write your content here…</p>', 'inner_class' => '', 'inner_target' => 'container'],
            'inline' => 'content',
            'fields' => [
                ['key' => 'content', 'label' => 'Content', 'type' => 'richtext'],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
                ['key' => 'color', 'label' => 'Text Color', 'type' => 'color', 'token' => true],
                ['key' => 'typography', 'label' => 'Typography', 'type' => 'typography', 'devices' => true],
                ['key' => 'inner_class', 'label' => 'Semantic CSS class (optional)', 'type' => 'text'],
                ['key' => 'inner_target', 'label' => 'Semantic class target', 'type' => 'select', 'options' => ['container' => 'Content container', 'first-child' => 'First content element']],
                ['key' => 'margin', 'label' => 'Margin', 'type' => 'spacing4', 'devices' => true],
            ],
        ],
        'image' => [
            'label' => 'Image', 'group' => 'basic', 'icon' => 'image',
            'defaults' => ['src' => '', 'alt' => '', 'caption' => '', 'link' => '', 'width_pct' => ['d' => 100], 'radius' => '', 'align' => ['d' => ''], 'inner_class' => ''],
            'fields' => [
                ['key' => 'src', 'label' => 'Image', 'type' => 'media'],
                ['key' => 'alt', 'label' => 'Alt Text', 'type' => 'text'],
                ['key' => 'caption', 'label' => 'Caption', 'type' => 'text'],
                ['key' => 'link', 'label' => 'Link URL', 'type' => 'text'],
                ['key' => 'width_pct', 'label' => 'Width %', 'type' => 'slider', 'min' => 10, 'max' => 100, 'devices' => true],
                ['key' => 'radius', 'label' => 'Corner Radius (px)', 'type' => 'number'],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
                ['key' => 'inner_class', 'label' => 'Semantic CSS class (optional)', 'type' => 'text'],
            ],
        ],
        'button' => [
            'label' => 'Button', 'group' => 'basic', 'icon' => 'mouse-pointer',
            'defaults' => ['text' => 'Click Me', 'url' => '#', 'target' => '', 'btn_style' => 'solid', 'size' => 'md', 'color' => 'primary', 'align' => ['d' => 'left'], 'full' => false, 'icon_pack' => 'core', 'icon' => '', 'icon_position' => 'right', 'css_class' => ''],
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
                ['key' => 'icon', 'label' => 'Icon (optional)', 'type' => 'iconpicker', 'pack_key' => 'icon_pack'],
                ['key' => 'icon_position', 'label' => 'Icon position', 'type' => 'select', 'options' => ['left' => 'Left', 'right' => 'Right']],
                ['key' => 'margin', 'label' => 'Margin', 'type' => 'spacing4', 'devices' => true],
            ],
        ],
        'icon' => [
            'label' => 'Icon', 'group' => 'basic', 'icon' => 'star',
            'defaults' => ['icon_pack' => 'core', 'name' => 'star', 'size' => 32, 'color' => 'primary', 'link' => '', 'align' => ['d' => '']],
            'fields' => [
                ['key' => 'name', 'label' => 'Icon', 'type' => 'iconpicker', 'pack_key' => 'icon_pack'],
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
        'profile_quote' => [
            'label' => 'Profile Quote', 'group' => 'content', 'icon' => 'quote',
            'defaults' => [
                'kicker' => 'Featured perspective', 'heading' => 'A meaningful point of view', 'heading_tag' => 'h2',
                'quote' => '<p>Share an editorial quote or personal perspective here.</p>', 'name' => 'Profile name',
                'role_one' => 'Role or title', 'role_two' => '', 'cta_text' => 'View profile', 'cta_url' => '#',
                'image' => '', 'image_alt' => '', 'image_link' => '', 'portrait_position' => 'right',
                'surface_color' => 'secondary', 'text_color' => '#ffffff', 'accent_color' => 'accent',
            ],
            'fields' => [
                ['key' => 'kicker', 'label' => 'Kicker', 'type' => 'text'],
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                ['key' => 'heading_tag', 'label' => 'Heading tag', 'type' => 'select', 'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4']],
                ['key' => 'quote', 'label' => 'Quote', 'type' => 'richtext'],
                ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'role_one', 'label' => 'Primary role / title', 'type' => 'text'],
                ['key' => 'role_two', 'label' => 'Secondary role / title', 'type' => 'text'],
                ['key' => 'cta_text', 'label' => 'CTA label', 'type' => 'text'],
                ['key' => 'cta_url', 'label' => 'CTA URL', 'type' => 'text'],
                ['key' => 'image', 'label' => 'Portrait', 'type' => 'media'],
                ['key' => 'image_alt', 'label' => 'Portrait alt text', 'type' => 'text'],
                ['key' => 'image_link', 'label' => 'Portrait link', 'type' => 'text'],
                ['key' => 'portrait_position', 'label' => 'Portrait position', 'type' => 'select', 'options' => ['right' => 'Right', 'left' => 'Left']],
                ['key' => 'surface_color', 'label' => 'Surface color', 'type' => 'color', 'token' => true],
                ['key' => 'text_color', 'label' => 'Text color', 'type' => 'color', 'token' => true],
                ['key' => 'accent_color', 'label' => 'Accent color', 'type' => 'color', 'token' => true],
            ],
        ],
        'tabs' => [
            'label' => 'Tabs', 'group' => 'content', 'icon' => 'panel-top',
            'defaults' => [
                'items' => [
                    ['title' => 'Tab 1', 'content' => '<p>Content…</p>', 'list_items' => []],
                    ['title' => 'Tab 2', 'content' => '<p>Content…</p>', 'list_items' => []],
                ],
                'aria_label' => 'Content tabs', 'variant' => 'underline', 'nav_align' => 'start', 'color' => 'primary',
                'active_text_color' => '#ffffff', 'inactive_text_color' => 'muted', 'panel_bg_color' => 'surface',
                'panel_text_color' => 'text', 'panel_border_color' => 'border', 'panel_max_width' => '64rem',
                'panel_radius' => '1.5rem', 'panel_shadow' => '', 'list_style' => 'plain',
            ],
            'fields' => [
                ['key' => 'items', 'label' => 'Tabs', 'type' => 'repeater', 'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'content', 'label' => 'Content', 'type' => 'richtext'],
                    ['key' => 'list_items', 'label' => 'Structured list', 'type' => 'repeater', 'fields' => [
                        ['key' => 'text', 'label' => 'Text', 'type' => 'textarea'],
                    ], 'item_label' => 'text'],
                ], 'item_label' => 'title'],
                ['key' => 'aria_label', 'label' => 'Tab list accessible label', 'type' => 'text'],
                ['key' => 'variant', 'label' => 'Presentation', 'type' => 'select', 'options' => ['underline' => 'Underline', 'pills-card' => 'Pills + card']],
                ['key' => 'nav_align', 'label' => 'Tab alignment', 'type' => 'select', 'options' => ['start' => 'Start', 'center' => 'Center', 'end' => 'End', 'stretch' => 'Stretch']],
                ['key' => 'color', 'label' => 'Active background / accent', 'type' => 'color', 'token' => true],
                ['key' => 'active_text_color', 'label' => 'Active tab text', 'type' => 'color', 'token' => true],
                ['key' => 'inactive_text_color', 'label' => 'Inactive tab text', 'type' => 'color', 'token' => true],
                ['key' => 'panel_bg_color', 'label' => 'Panel background', 'type' => 'color', 'token' => true],
                ['key' => 'panel_text_color', 'label' => 'Panel text', 'type' => 'color', 'token' => true],
                ['key' => 'panel_border_color', 'label' => 'Panel border', 'type' => 'color', 'token' => true],
                ['key' => 'panel_max_width', 'label' => 'Panel max width', 'type' => 'text'],
                ['key' => 'panel_radius', 'label' => 'Panel radius', 'type' => 'text'],
                ['key' => 'panel_padding', 'label' => 'Panel padding', 'type' => 'spacing4', 'devices' => true],
                ['key' => 'nav_margin', 'label' => 'Tab navigation margin', 'type' => 'spacing4', 'devices' => true],
                ['key' => 'panel_shadow', 'label' => 'Panel shadow', 'type' => 'select', 'options' => ['' => 'None', 'small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']],
                ['key' => 'list_style', 'label' => 'Structured list style', 'type' => 'select', 'options' => ['plain' => 'Plain', 'numbered-rows' => 'Numbered rows']],
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
        'carousel' => [
            'label' => 'Carousel', 'group' => 'content', 'icon' => 'image',
            'defaults' => [
                'presentation' => 'images', 'images' => [], 'cards' => [], 'slides' => [], 'aria_label' => 'Featured content',
                'heading_tag' => 'h2', 'per_view' => ['d' => 1, 't' => 1, 'm' => 1], 'gap' => 16,
                'ratio' => '16/9', 'effect' => 'slide', 'autoplay' => false, 'delay' => 4000, 'loop' => true,
                'nav' => true, 'dots' => true, 'progress' => true, 'slider_height' => [], 'content_vertical' => '',
                'content_align' => '', 'content_max_width' => '', 'content_padding' => [], 'overlay_color' => '',
                'overlay_opacity' => '', 'overlay_direction' => '', 'title_size' => [], 'title_color' => '',
                'accent_color' => '', 'description_color' => '', 'primary_text_color' => '', 'secondary_color' => '',
                'controls_color' => '', 'transition_duration' => '', 'radius' => '',
                'item_width' => ['d' => 144, 't' => 144, 'm' => 112],
                'media_height' => ['d' => 224, 't' => 224, 'm' => 144],
                'item_overlap' => ['d' => 24, 't' => 24, 'm' => 0],
                'rail_gap' => ['d' => 0, 't' => 0, 'm' => 8], 'center_on' => 'mobile',
                'snap' => 'proximity', 'hover_effect' => 'scale', 'card_radius' => 8,
                'card_bg_color' => 'transparent', 'rail_title_color' => 'text', 'text_color' => 'muted',
            ],
            'fields' => [
                ['key' => 'presentation', 'label' => 'Presentation', 'type' => 'select', 'options' => ['images' => 'Image carousel', 'content-slides' => 'Content slides', 'card-rail' => 'Card rail']],
                ['key' => 'images', 'label' => 'Image slides', 'type' => 'gallery', 'show_if' => ['presentation' => ['', 'images']]],
                ['key' => 'slides', 'label' => 'Content slides', 'type' => 'repeater', 'show_if' => ['presentation' => 'content-slides'], 'fields' => [
                    ['key' => 'image', 'label' => 'Background image', 'type' => 'media'],
                    ['key' => 'image_alt', 'label' => 'Image alt text', 'type' => 'text'],
                    ['key' => 'image_position', 'label' => 'Image focal position', 'type' => 'text'],
                    ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'accent', 'label' => 'Accent text', 'type' => 'text'],
                    ['key' => 'title_after', 'label' => 'Title continuation', 'type' => 'text'],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    ['key' => 'primary_label', 'label' => 'Primary button label', 'type' => 'text'],
                    ['key' => 'primary_url', 'label' => 'Primary button URL', 'type' => 'text'],
                    ['key' => 'secondary_label', 'label' => 'Secondary button label', 'type' => 'text'],
                    ['key' => 'secondary_url', 'label' => 'Secondary button URL', 'type' => 'text'],
                ], 'item_label' => 'title'],
                ['key' => 'cards', 'label' => 'Cards', 'type' => 'repeater', 'show_if' => ['presentation' => 'card-rail'], 'fields' => [
                    ['key' => 'key', 'label' => 'Card key', 'type' => 'text'],
                    ['key' => 'image', 'label' => 'Image', 'type' => 'media'],
                    ['key' => 'image_alt', 'label' => 'Image alt text', 'type' => 'text'],
                    ['key' => 'image_position', 'label' => 'Image focal position', 'type' => 'text'],
                    ['key' => 'image_scale', 'label' => 'Image scale (%)', 'type' => 'slider', 'min' => 50, 'max' => 125],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'text', 'label' => 'Supporting text', 'type' => 'textarea'],
                    ['key' => 'url', 'label' => 'Card URL', 'type' => 'text'],
                    ['key' => 'link_label', 'label' => 'Accessible link label (optional)', 'type' => 'text'],
                    ['key' => 'meta_image', 'label' => 'Secondary image', 'type' => 'media'],
                    ['key' => 'meta_image_alt', 'label' => 'Secondary image alt text', 'type' => 'text'],
                    ['key' => 'initial', 'label' => 'Initially centered card', 'type' => 'toggle'],
                ], 'item_label' => 'title'],
                ['key' => 'aria_label', 'label' => 'Accessible label', 'type' => 'text', 'show_if' => ['presentation' => ['content-slides', 'card-rail']]],
                ['key' => 'heading_tag', 'label' => 'Slide heading level', 'type' => 'select', 'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3'], 'show_if' => ['presentation' => 'content-slides']],
                ['key' => 'item_width', 'label' => 'Card width (px)', 'type' => 'slider', 'min' => 80, 'max' => 400, 'devices' => true, 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'media_height', 'label' => 'Media height (px)', 'type' => 'slider', 'min' => 80, 'max' => 500, 'devices' => true, 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'item_overlap', 'label' => 'Card overlap (px)', 'type' => 'slider', 'min' => 0, 'max' => 100, 'devices' => true, 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'rail_gap', 'label' => 'Card gap (px)', 'type' => 'slider', 'min' => 0, 'max' => 80, 'devices' => true, 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'center_on', 'label' => 'Center initial card', 'type' => 'select', 'options' => ['none' => 'Never', 'mobile' => 'Mobile only', 'all' => 'All devices'], 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'snap', 'label' => 'Scroll snap', 'type' => 'select', 'options' => ['none' => 'None', 'proximity' => 'Proximity', 'mandatory' => 'Mandatory'], 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'hover_effect', 'label' => 'Hover effect', 'type' => 'select', 'options' => ['none' => 'None', 'lift' => 'Lift', 'scale' => 'Scale'], 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'card_radius', 'label' => 'Card radius (px)', 'type' => 'number', 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'card_bg_color', 'label' => 'Card background', 'type' => 'color', 'token' => true, 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'rail_title_color', 'label' => 'Card title color', 'type' => 'color', 'token' => true, 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'text_color', 'label' => 'Card text color', 'type' => 'color', 'token' => true, 'show_if' => ['presentation' => 'card-rail']],
                ['key' => 'per_view', 'label' => 'Slides per view', 'type' => 'slider', 'min' => 1, 'max' => 4, 'devices' => true, 'show_if' => ['presentation' => ['', 'images']]],
                ['key' => 'gap', 'label' => 'Gap (px)', 'type' => 'number', 'show_if' => ['presentation' => ['', 'images']]],
                ['key' => 'ratio', 'label' => 'Slide ratio', 'type' => 'select', 'options' => ['16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1', '3/4' => '3:4', '21/9' => '21:9'], 'show_if' => ['presentation' => ['', 'images']]],
                ['key' => 'effect', 'label' => 'Effect', 'type' => 'select', 'options' => ['slide' => 'Slide', 'fade' => 'Fade'], 'show_if' => ['presentation' => ['', 'images']]],
                ['key' => 'autoplay', 'label' => 'Autoplay', 'type' => 'toggle', 'show_if' => ['presentation' => ['', 'images', 'content-slides']]],
                ['key' => 'delay', 'label' => 'Autoplay delay (ms)', 'type' => 'number', 'show_if' => ['autoplay' => true, 'presentation' => ['', 'images', 'content-slides']]],
                ['key' => 'loop', 'label' => 'Loop', 'type' => 'toggle', 'show_if' => ['presentation' => ['', 'images', 'content-slides']]],
                ['key' => 'nav', 'label' => 'Navigation arrows', 'type' => 'toggle', 'show_if' => ['presentation' => ['', 'images', 'content-slides']]],
                ['key' => 'dots', 'label' => 'Pagination dots', 'type' => 'toggle', 'show_if' => ['presentation' => ['', 'images', 'content-slides']]],
                ['key' => 'progress', 'label' => 'Progress bar', 'type' => 'toggle', 'show_if' => ['presentation' => 'content-slides']],
                ['key' => 'radius', 'label' => 'Corner radius (px)', 'type' => 'number', 'show_if' => ['presentation' => ['', 'images']]],
            ],
            'style_label' => 'Content Slider',
            'style_show_if' => ['presentation' => 'content-slides'],
            'style_fields' => [
                ['key' => 'slider_height', 'label' => 'Slider height (px/rem/vh)', 'type' => 'text', 'devices' => true],
                ['key' => 'content_vertical', 'label' => 'Content vertical position', 'type' => 'select', 'options' => ['' => 'Theme default', 'top' => 'Top', 'center' => 'Center', 'bottom' => 'Bottom']],
                ['key' => 'content_align', 'label' => 'Content alignment', 'type' => 'select', 'options' => ['' => 'Theme default', 'left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
                ['key' => 'content_max_width', 'label' => 'Content max width (px/rem)', 'type' => 'text'],
                ['key' => 'content_padding', 'label' => 'Content padding (CSS shorthand)', 'type' => 'text', 'devices' => true],
                ['key' => 'overlay_color', 'label' => 'Overlay color', 'type' => 'color', 'token' => true],
                ['key' => 'overlay_opacity', 'label' => 'Overlay opacity (%)', 'type' => 'number', 'min' => 0, 'max' => 100],
                ['key' => 'overlay_direction', 'label' => 'Overlay direction', 'type' => 'select', 'options' => ['' => 'Theme default', 'right' => 'Left to right', 'left' => 'Right to left', 'bottom' => 'Top to bottom', 'top' => 'Bottom to top']],
                ['key' => 'title_size', 'label' => 'Title size (px/rem)', 'type' => 'text', 'devices' => true],
                ['key' => 'title_color', 'label' => 'Title color', 'type' => 'color', 'token' => true],
                ['key' => 'accent_color', 'label' => 'Accent color', 'type' => 'color', 'token' => true],
                ['key' => 'description_color', 'label' => 'Description color', 'type' => 'color', 'token' => true],
                ['key' => 'primary_text_color', 'label' => 'Primary button text', 'type' => 'color', 'token' => true],
                ['key' => 'secondary_color', 'label' => 'Secondary button color', 'type' => 'color', 'token' => true],
                ['key' => 'controls_color', 'label' => 'Navigation color', 'type' => 'color', 'token' => true],
                ['key' => 'transition_duration', 'label' => 'Image transition (ms)', 'type' => 'number', 'min' => 0, 'max' => 20000],
            ],
        ],
        'card' => [
            'label' => 'Card', 'group' => 'content', 'icon' => 'panel-top',
            'defaults' => ['image' => '', 'img_alt' => '', 'badge' => '', 'title' => 'Card title', 'text' => 'Supporting text for this card.', 'btn_text' => '', 'btn_url' => '', 'action_icon_pack' => 'core', 'action_icon' => '', 'layout' => 'top', 'radius' => 12, 'shadow' => true, 'color' => 'primary', 'align' => ['d' => '']],
            'fields' => [
                ['key' => 'image', 'label' => 'Image', 'type' => 'media'],
                ['key' => 'img_alt', 'label' => 'Image alt text', 'type' => 'text'],
                ['key' => 'badge', 'label' => 'Badge text (optional)', 'type' => 'text'],
                ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['key' => 'text', 'label' => 'Text', 'type' => 'textarea'],
                ['key' => 'btn_text', 'label' => 'Button text (optional)', 'type' => 'text'],
                ['key' => 'btn_url', 'label' => 'Button or card URL', 'type' => 'text'],
                ['key' => 'action_icon', 'label' => 'Card action icon (optional)', 'type' => 'iconpicker', 'pack_key' => 'action_icon_pack'],
                ['key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'options' => ['top' => 'Image top', 'left' => 'Image left', 'background' => 'Image background']],
                ['key' => 'radius', 'label' => 'Corner radius (px)', 'type' => 'number'],
                ['key' => 'shadow', 'label' => 'Shadow', 'type' => 'toggle'],
                ['key' => 'color', 'label' => 'Accent color', 'type' => 'color', 'token' => true],
                ['key' => 'align', 'label' => 'Alignment', 'type' => 'align', 'devices' => true],
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
            'defaults' => ['icon_pack' => 'core', 'icon' => 'zap', 'title' => 'Feature', 'text' => 'Describe this feature briefly.', 'url' => '', 'layout' => 'top', 'color' => 'primary', 'align' => ['d' => '']],
            'fields' => [
                ['key' => 'icon', 'label' => 'Icon', 'type' => 'iconpicker', 'pack_key' => 'icon_pack'],
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
        'preset_collection' => [
            'label' => 'Published Preset', 'group' => 'dynamic', 'icon' => 'grid-3x3',
            'defaults' => [
                'preset_id' => 0, 'preset_slug' => '', 'layout' => '', 'show_header' => true,
                'title' => '', 'summary' => '', 'more_text' => '', 'more_url' => '',
                'limit' => '', 'max_items' => '',
            ],
            'fields' => [
                ['key' => 'preset_id', 'label' => 'Content Preset', 'type' => 'presetpicker'],
                ['key' => 'layout', 'label' => 'Block Style', 'type' => 'select', 'options' => jvb_preset_layout_options()],
                ['key' => 'show_header', 'label' => 'Show section header', 'type' => 'toggle'],
                ['key' => 'title', 'label' => 'Section Title', 'type' => 'text', 'show_if' => ['show_header' => true]],
                ['key' => 'summary', 'label' => 'Summary', 'type' => 'textarea', 'show_if' => ['show_header' => true]],
                ['key' => 'more_text', 'label' => 'More Link Label', 'type' => 'text', 'show_if' => ['show_header' => true]],
                ['key' => 'more_url', 'label' => 'More Link URL', 'type' => 'text', 'show_if' => ['show_header' => true]],
                ['key' => 'limit', 'label' => 'Posts per page (optional)', 'type' => 'number'],
                ['key' => 'max_items', 'label' => 'Maximum total posts (optional)', 'type' => 'number'],
                ['key' => 'margin', 'label' => 'Margin', 'type' => 'spacing4', 'devices' => true],
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
        if (is_numeric($n)) $value = $n . $unit;
        elseif ($prop === 'margin' && $n === 'auto') $value = 'auto';
        else continue;
        $property = $prop === 'border-width' ? 'border-' . $name . '-width' : $prop . '-' . $name;
        $sides[] = $property . ':' . $value;
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

function jvb_css_length($value): string {
    $value = trim((string)$value);
    $parts = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts || count($parts) > 4) return '';
    foreach ($parts as $part) {
        if (!preg_match('/^(?:0|[-+]?(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vw|vh|ch|ex))$/i', $part)) return '';
    }
    return implode(' ', $parts);
}

function jvb_css_position($value): string {
    $value = trim((string)$value);
    if ($value === '') return 'center';
    $parts = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts || count($parts) > 2) return 'center';
    foreach ($parts as $part) {
        if (!preg_match('/^(?:left|right|top|bottom|center|[-+]?(?:\d+(?:\.\d+)?|\.\d+)%?)$/i', $part)) return 'center';
    }
    return implode(' ', $parts);
}

function jvb_css_shadow($value): string {
    return match ((string)$value) {
        'small' => '0 4px 12px rgb(15 23 42 / .12)',
        'medium' => '0 10px 28px rgb(15 23 42 / .16)',
        'large' => '0 18px 42px rgb(15 23 42 / .2)',
        default => '',
    };
}

// Collect per-node CSS rules for one device set. Returns ['base' => [], 't' => [], 'm' => []]
function jvb_node_css(array $s, string $kind, string $elementType = ''): array {
    $rules = ['d' => '', 't' => '', 'm' => ''];
    foreach (['d', 't', 'm'] as $dev) {
        $r = '';
        // spacing — section padding goes to .jvb-section__inner (handled in jvb_layout_css)
        if ($kind !== 'section') {
            $r .= jvb_css_spacing($s['spacing']['padding'] ?? ($s['padding'] ?? null), 'padding', $dev);
        }
        $r .= jvb_css_spacing($s['spacing']['margin'] ?? ($s['margin'] ?? null), 'margin', $dev);
        $maxWidth = jvb_dev($s['max_width'] ?? null, $dev);
        if ($maxWidth !== null && ($length = jvb_css_length($maxWidth)) !== '') $r .= 'max-width:' . $length . ';';
        // Row alignment controls the flex cross-axis, not text alignment.
        // Text alignment belongs to columns and elements, where the Builder
        // exposes it as a style setting.
        $al = jvb_dev($s['align'] ?? null, $dev);
        if ($kind !== 'row' && $al !== null && in_array($al, ['left', 'center', 'right', 'justify'], true)) $r .= 'text-align:' . $al . ';';
        // typography
        $r .= jvb_css_typography($s['typography'] ?? null, $dev);
        // width (elements)
        if ($kind === 'element' && $elementType !== 'divider') {
            $w = jvb_dev($s['width_pct'] ?? null, $dev);
            if ($w !== null && is_numeric($w) && (float)$w < 100) $r .= 'width:' . (float)$w . '%;';
        }
        // colors (device-independent but allowed here for simplicity — only emit on desktop)
        $borderWidth = $s['border_width'] ?? null;
        if (is_array($borderWidth)) $r .= jvb_css_spacing($borderWidth, 'border-width', $dev);
        if ($dev === 'd') {
            if (($c = jvb_color($s['color'] ?? '')) !== '') $r .= 'color:' . $c . ';';
            if (($c = jvb_color($s['bg_color'] ?? '')) !== '') $r .= 'background-color:' . $c . ';';
            if (($c = jvb_color($s['border_color'] ?? '')) !== '') $r .= 'border-color:' . $c . ';';
            if (!is_array($borderWidth) && $borderWidth !== null && ($length = jvb_css_length($borderWidth)) !== '') $r .= 'border-width:' . $length . ';';
            $borderStyle = in_array($s['border_style'] ?? '', ['solid', 'dashed', 'dotted', 'double', 'none'], true) ? $s['border_style'] : '';
            if ($borderStyle !== '') $r .= 'border-style:' . $borderStyle . ';';
            if (($radius = jvb_css_length($s['border_radius'] ?? '')) !== '') $r .= 'border-radius:' . $radius . ';';
            if (($shadow = jvb_css_shadow($s['box_shadow'] ?? '')) !== '') $r .= 'box-shadow:' . $shadow . ';';
            if (is_numeric($s['opacity'] ?? null)) $r .= 'opacity:' . min(1, max(0, (float)$s['opacity']) ) . ';';
            if (in_array($s['overflow'] ?? '', ['visible', 'hidden', 'clip', 'auto'], true)) $r .= 'overflow:' . $s['overflow'] . ';';
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

function jvb_tabs_css(array $settings, string $selector): string {
    $css = '';
    foreach (['d', 't', 'm'] as $dev) {
        $panel = jvb_css_spacing($settings['panel_padding'] ?? null, 'padding', $dev);
        $nav = jvb_css_spacing($settings['nav_margin'] ?? null, 'margin', $dev);
        $rules = '';
        if ($nav !== '') $rules .= $selector . ' .jvb-tabs__nav{' . $nav . '}';
        if ($panel !== '') $rules .= $selector . ' .jvb-tabs__panel{' . $panel . '}';
        if ($rules === '') continue;
        if ($dev === 't') $css .= '@media(max-width:' . JVB_BP_TABLET . 'px){' . $rules . '}';
        elseif ($dev === 'm') $css .= '@media(max-width:' . JVB_BP_MOBILE . 'px){' . $rules . '}';
        else $css .= $rules;
    }
    return $css;
}

// Column width rules (flex-basis percentages)
function jvb_column_css(array $s, string $sel): string {
    $out = '';
    foreach (['d', 't', 'm'] as $dev) {
        $w = jvb_dev($s['width'] ?? null, $dev, $dev === 'd' ? 100 : null);
        $ruleBody = '';
        if ($w !== null && is_numeric($w)) {
            // flex-grow ratio: distributes row width proportionally after the flex
            // gap is subtracted — width% would overflow the row and wrap columns.
            // Keep the saved Builder ratio authoritative when a theme adds a
            // shorthand flex rule to a custom column class.
            $ruleBody .= 'flex-grow:' . (float)$w . '!important;';
        }
        $valign = jvb_dev($s['valign'] ?? null, $dev, '');
        $justify = match ($valign) {
            'center' => 'center',
            'end' => 'flex-end',
            'space-between' => 'space-between',
            default => '',
        };
        if ($justify !== '') $ruleBody .= 'justify-content:' . $justify . ';';
        if ($ruleBody === '') continue;
        $rule = $sel . '{' . $ruleBody . '}';
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
    if (!empty($s['aria_labelledby']) && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', (string)$s['aria_labelledby'])) {
        $out .= ' aria-labelledby="' . jvb_e($s['aria_labelledby']) . '"';
    }
    $anim = (string)($s['animation'] ?? '');
    $allowed = ['fade', 'fade-up', 'fade-down', 'fade-left', 'fade-right', 'zoom-in', 'flip-up'];
    if (in_array($anim, $allowed, true)) {
        $out .= ' data-jvb-anim="' . $anim . '"';
        if (!empty($s['anim_delay']) && is_numeric($s['anim_delay'])) $out .= ' data-jvb-delay="' . (int)$s['anim_delay'] . '"';
    }
    return $out;
}

function jvb_inner_class(array $s): string {
    return trim((string)preg_replace('/[^a-zA-Z0-9 _-]/', '', (string)($s['inner_class'] ?? '')));
}

function jvb_inner_id(array $s): string {
    $id = (string)($s['inner_id'] ?? '');
    return preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $id) ? $id : '';
}

function jvb_first_child_class(string $html, string $class): string {
    if ($class === '') return $html;
    return (string)preg_replace_callback(
        '/^(\s*<([a-z][a-z0-9]*)\b)([^>]*)(>)/i',
        static function (array $match) use ($class): string {
            $attrs = $match[3];
            if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $attrs, $classMatch)) {
                $newClass = trim($classMatch[2] . ' ' . $class);
                $attrs = (string)preg_replace('/\bclass\s*=\s*(["\'])(.*?)\1/i', 'class="' . jvb_e($newClass) . '"', $attrs, 1);
            } else {
                $attrs .= ' class="' . jvb_e($class) . '"';
            }
            return $match[1] . $attrs . $match[4];
        },
        $html,
        1
    );
}

// ---------------- Lucide icons (plugin set first, then core set) ----------------

function jvb_lucide_dirs(): array {
    // Core icon set — resolve via PUBLIC_PATH when available (hybrid installs
    // may use public_html/ instead of public/ as the webroot).
    if (defined('PUBLIC_PATH')) {
        $core = PUBLIC_PATH . '/static/icons/lucide/';
    } else {
        $core = realpath(dirname(__DIR__, 3) . '/public_html/static/icons/lucide')
              ?: (dirname(__DIR__, 3) . '/public/static/icons/lucide/');
    }
    return [
        __DIR__ . '/../assets/icons/',
        $core,
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

// Heroicons Outline is bundled as a trusted, generated SVG sprite. Layouts
// persist only the pack and icon identifiers, never arbitrary SVG markup.
function jvb_heroicon_symbols(): array {
    static $symbols = null;
    if ($symbols !== null) return $symbols;
    $symbols = [];
    $file = dirname(__DIR__) . '/assets/icon-packs/heroicons-outline.svg';
    if (!is_file($file)) return $symbols;
    $svg = (string)file_get_contents($file);
    if (preg_match_all('~<symbol id="jvb-hero-([a-z0-9]+(?:-[a-z0-9]+)*)"[^>]*>(.*?)</symbol>~s', $svg, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) $symbols[$match[1]] = $match[2];
    }
    ksort($symbols, SORT_STRING);
    return $symbols;
}

function jvb_available_heroicons(): array {
    return array_keys(jvb_heroicon_symbols());
}

function jvb_icon_pack_id(mixed $pack): string {
    return $pack === 'heroicons' ? 'heroicons' : 'core';
}

function jvb_content_icon(string $pack, string $name, int $size = 24): string {
    $pack = jvb_icon_pack_id($pack);
    $size = max(8, min(256, $size));
    if ($pack === 'core') return jvb_lucide($name, $size);

    $symbols = jvb_heroicon_symbols();
    $name = strtolower(trim($name));
    if (!isset($symbols[$name])) $name = 'star';
    if (!isset($symbols[$name])) return jvb_lucide('circle', $size);
    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size
        . '" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
        . $symbols[$name] . '</svg>';
}

function jvb_icon_pack_catalog(): array {
    return [
        [
            'id' => 'core',
            'label' => 'Core / Lucide',
            'icons' => jvb_available_icons(),
            'sprite' => '',
        ],
        [
            'id' => 'heroicons',
            'label' => 'Jy Builder / Heroicons Outline',
            'icons' => jvb_available_heroicons(),
            'sprite' => function_exists('jvb_asset_url') ? jvb_asset_url('heroicons-outline.svg') : '',
        ],
    ];
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

function jvb_safe_rich_html(string $html): string {
    if (function_exists('cms_sanitize_restricted_html')) return cms_sanitize_restricted_html($html);
    return nl2br(jvb_e(trim(strip_tags($html))));
}

function jvb_preset_layout_options(): array {
    return [
        '' => 'Use Preset Layout',
        'cards' => 'Cards',
        'grid' => 'Grid',
        'list' => 'Editorial List',
        'mini' => 'Compact List',
        'sliderpage' => 'Slider',
    ];
}

function jvb_preset_picker_rows(PDO $pdo, array $rows): array {
    $presets = [];
    foreach ($rows as $row) {
        $slug = strtolower(trim((string)($row['slug'] ?? '')));
        $valid = function_exists('shortcode_preset_slug_is_valid')
            ? shortcode_preset_slug_is_valid($slug)
            : ($slug !== '' && preg_match('/\A[a-z0-9_-]+\z/', $slug) === 1);
        if (!$valid) continue;

        try {
            $config = function_exists('shortcode_preset_config_loaded')
                ? shortcode_preset_config_loaded((string)($row['meta'] ?? ''), $row, $pdo, ['scope' => 'jyavani_builder_picker'])
                : json_decode((string)($row['meta'] ?? ''), true);
        } catch (Throwable $error) {
            error_log('[jyavani-builder] Could not load preset ' . $slug . ' for the picker: ' . $error->getMessage());
            $config = [];
        }
        $presets[(int)($row['id'] ?? 0)] = [
            'id' => (int)($row['id'] ?? 0),
            'title' => (string)($row['title'] ?? $slug),
            'slug' => $slug,
            'layout' => is_array($config) ? trim((string)($config['layout'] ?? '')) : '',
        ];
    }
    return $presets;
}

function jvb_published_presets(PDO $pdo, string $query = '', int $limit = 100, array $includeIds = []): array {
    $query = substr(trim($query), 0, 191);
    $limit = max(1, min(100, $limit));
    $params = [];
    $sql = "SELECT id, title, slug, meta FROM posts WHERE type = 'sc_preset' AND status = 'published' AND is_deleted = 0";
    if ($query !== '') {
        $sql .= " AND (title LIKE ? ESCAPE '!' OR slug LIKE ? ESCAPE '!')";
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);
        $params[] = '%' . $escaped . '%';
        $params[] = '%' . $escaped . '%';
    }
    $sql .= " ORDER BY title ASC, id ASC LIMIT {$limit}";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $ids = [];
        foreach ($includeIds as $id) {
            $validId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validId !== false) $ids[(int)$validId] = true;
            if (count($ids) >= 200) break;
        }
        if ($ids !== []) {
            $values = array_keys($ids);
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $included = $pdo->prepare("SELECT id, title, slug, meta FROM posts WHERE id IN ({$placeholders}) AND type = 'sc_preset' AND status = 'published' AND is_deleted = 0");
            $included->execute($values);
            $rows = array_merge($rows, $included->fetchAll(PDO::FETCH_ASSOC));
        }
    } catch (Throwable $error) {
        error_log('[jyavani-builder] Could not list published presets: ' . $error->getMessage());
        return [];
    }

    $presets = array_values(jvb_preset_picker_rows($pdo, $rows));
    usort($presets, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']) ?: ($a['id'] <=> $b['id']));
    return $presets;
}

function jvb_published_presets_by_slugs(PDO $pdo, array $slugs): array {
    $valid = [];
    foreach ($slugs as $slug) {
        if (!is_string($slug)) continue;
        $slug = strtolower(trim($slug));
        $isValid = function_exists('shortcode_preset_slug_is_valid')
            ? shortcode_preset_slug_is_valid($slug)
            : ($slug !== '' && preg_match('/\A[a-z0-9_-]+\z/', $slug) === 1);
        if ($isValid) $valid[$slug] = true;
        if (count($valid) > 200) throw new InvalidArgumentException('Too many Content Presets to resolve.');
    }
    if ($valid === []) return [];
    $values = array_keys($valid);
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $stmt = $pdo->prepare("SELECT id, title, slug, meta FROM posts WHERE slug IN ({$placeholders}) AND type = 'sc_preset' AND status = 'published' AND is_deleted = 0");
    $stmt->execute($values);
    return array_values(jvb_preset_picker_rows($pdo, $stmt->fetchAll(PDO::FETCH_ASSOC)));
}

function jvb_layout_preset_ids(mixed $value): array {
    $ids = [];
    $walk = static function (mixed $node) use (&$walk, &$ids): void {
        if (!is_array($node)) return;
        if (($node['type'] ?? null) === 'preset_collection') {
            $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
            $presetId = filter_var($settings['preset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($presetId !== false) $ids[(int)$presetId] = true;
        }
        foreach ($node as $child) {
            if (is_array($child)) $walk($child);
        }
    };
    $walk($value);
    $result = array_keys($ids);
    sort($result, SORT_NUMERIC);
    return $result;
}

function jvb_layout_references_preset(mixed $value, int $presetId): bool {
    return $presetId > 0 && in_array($presetId, jvb_layout_preset_ids($value), true);
}

function jvb_lock_layout_presets(PDO $pdo, array $layout): array {
    if (!$pdo->inTransaction()) throw new LogicException('Content Preset validation requires an active transaction.');
    $ids = jvb_layout_preset_ids($layout);
    if ($ids === []) return $layout;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, slug FROM posts WHERE id IN ({$placeholders}) AND type = 'sc_preset' AND status = 'published' AND is_deleted = 0 ORDER BY id";
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') $sql .= ' FOR UPDATE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $slugs = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $slugs[(int)($row['id'] ?? 0)] = strtolower(trim((string)($row['slug'] ?? '')));
    }
    $found = array_keys($slugs);
    sort($found, SORT_NUMERIC);
    if ($found !== $ids) throw new RuntimeException('A selected Content Preset is unavailable or no longer published.');

    $bind = static function (array &$node) use (&$bind, $slugs): void {
        if (($node['type'] ?? null) === 'preset_collection') {
            if (!isset($node['settings']) || !is_array($node['settings'])) $node['settings'] = [];
            $presetId = filter_var($node['settings']['preset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($presetId !== false && isset($slugs[(int)$presetId])) $node['settings']['preset_slug'] = $slugs[(int)$presetId];
        }
        foreach ($node as &$child) {
            if (is_array($child)) $bind($child);
        }
        unset($child);
    };
    $bind($layout);
    return $layout;
}

function jvb_preset_is_referenced(PDO $pdo, int $presetId): bool {
    if ($presetId <= 0) return false;
    $numberNeedle = '%"preset_id":' . $presetId . '%';
    $stringNeedle = '%"preset_id":"' . $presetId . '"%';
    $queries = [
        ["SELECT draft_json, published_json FROM jvb_layouts WHERE draft_json LIKE ? OR draft_json LIKE ? OR published_json LIKE ? OR published_json LIKE ?", [$numberNeedle, $stringNeedle, $numberNeedle, $stringNeedle]],
        ["SELECT layout_json FROM jvb_templates WHERE layout_json LIKE ? OR layout_json LIKE ?", [$numberNeedle, $stringNeedle]],
        ["SELECT layout_json FROM jvb_revisions WHERE layout_json LIKE ? OR layout_json LIKE ?", [$numberNeedle, $stringNeedle]],
    ];
    foreach ($queries as [$sql, $params]) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                foreach ($row as $raw) {
                    if (!is_string($raw) || $raw === '') continue;
                    $layout = json_decode($raw, true);
                    if (is_array($layout) && jvb_layout_references_preset($layout, $presetId)) return true;
                }
            }
        } catch (PDOException $error) {
            $state = is_array($error->errorInfo ?? null) ? (string)($error->errorInfo[0] ?? '') : (string)$error->getCode();
            if ($state === '42S02' || str_contains(strtolower($error->getMessage()), 'no such table')) continue;
            throw $error;
        }
    }
    return false;
}

function jvb_safe_frontend_url(string $url): string {
    $url = trim($url);
    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) return '';
    if ($url[0] === '#' || $url[0] === '?') return $url;
    if ($url[0] === '/' && !str_starts_with($url, '//')) return $url;
    $parts = parse_url($url);
    $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
    return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

function jvb_render_content_slider(array $settings): string {
    $slides = is_array($settings['slides'] ?? null) ? array_values(array_filter($settings['slides'], 'is_array')) : [];
    if ($slides === []) return '<div class="jvb-content-slider jvb-content-slider--empty">Add content slides to this carousel.</div>';

    $ariaLabel = trim((string)($settings['aria_label'] ?? 'Featured content')) ?: 'Featured content';
    $headingTag = in_array($settings['heading_tag'] ?? '', ['h1', 'h2', 'h3'], true) ? (string)$settings['heading_tag'] : 'h2';
    $autoplay = !empty($settings['autoplay']);
    $loop = !empty($settings['loop']);
    $nav = !empty($settings['nav']);
    $dots = !empty($settings['dots']);
    $progress = !empty($settings['progress']);
    $delay = is_numeric($settings['delay'] ?? null) ? min(60000, max(1000, (int)$settings['delay'])) : 4000;
    $count = count($slides);
    $style = [];
    foreach (['slider_height' => '--jvb-content-height', 'content_padding' => '--jvb-content-padding', 'title_size' => '--jvb-content-title-size'] as $key => $variable) {
        foreach (['d', 't', 'm'] as $device) {
            $value = jvb_dev($settings[$key] ?? null, $device);
            $length = $value !== null ? jvb_css_length($value) : '';
            if ($length !== '') $style[] = $variable . '-' . $device . ':' . $length;
        }
    }
    $contentMax = jvb_css_length($settings['content_max_width'] ?? '');
    if ($contentMax !== '') $style[] = '--jvb-content-max:' . $contentMax;
    $vertical = match ((string)($settings['content_vertical'] ?? '')) {
        'top' => 'flex-start', 'center' => 'center', 'bottom' => 'flex-end', default => '',
    };
    if ($vertical !== '') $style[] = '--jvb-content-vertical:' . $vertical;
    $alignment = (string)($settings['content_align'] ?? '');
    if (in_array($alignment, ['left', 'center', 'right'], true)) {
        $style[] = '--jvb-content-align:' . $alignment;
        $style[] = '--jvb-content-justify:' . ($alignment === 'left' ? 'flex-start' : ($alignment === 'right' ? 'flex-end' : 'center'));
    }
    foreach ([
        'title_color' => '--jvb-content-title', 'accent_color' => '--jvb-content-accent',
        'description_color' => '--jvb-content-description', 'primary_text_color' => '--jvb-content-primary-text',
        'secondary_color' => '--jvb-content-secondary', 'controls_color' => '--jvb-content-controls',
    ] as $key => $variable) {
        $color = jvb_color($settings[$key] ?? '');
        if ($color !== '') $style[] = $variable . ':' . $color;
    }
    $overlayColor = jvb_color($settings['overlay_color'] ?? '');
    $overlayOpacity = is_numeric($settings['overlay_opacity'] ?? null) ? min(100, max(0, (float)$settings['overlay_opacity'])) / 100 : null;
    $overlayAngle = match ((string)($settings['overlay_direction'] ?? '')) {
        'left' => '270deg', 'bottom' => '180deg', 'top' => '0deg', 'right' => '90deg', default => '',
    };
    $customOverlay = $overlayColor !== '' || $overlayOpacity !== null || $overlayAngle !== '';
    if ($overlayColor !== '') $style[] = '--jvb-content-overlay-color:' . $overlayColor;
    if ($overlayOpacity !== null) $style[] = '--jvb-content-overlay-opacity:' . $overlayOpacity;
    if ($overlayAngle !== '') $style[] = '--jvb-content-overlay-angle:' . $overlayAngle;
    if (is_numeric($settings['transition_duration'] ?? null)) {
        $style[] = '--jvb-content-transition:' . min(20000, max(0, (int)$settings['transition_duration'])) . 'ms';
    }

    $button = static function (string $class, string $label, string $rawUrl, bool $icon = false): string {
        $url = jvb_safe_frontend_url($rawUrl);
        $label = trim($label);
        if ($url === '' || $label === '') return '';
        $external = preg_match('#^https?://#i', $url) === 1;
        return '<a class="jvb-content-slide__button jvb-content-slide__button--' . $class . '" href="' . jvb_e($url) . '"'
            . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . jvb_e($label)
            . ($icon ? '<span class="jvb-content-slide__button-icon" aria-hidden="true">' . jvb_lucide('external-link', 16) . '</span>' : '')
            . '</a>';
    };

    $out = '<section class="jvb-content-slider' . ($customOverlay ? ' has-custom-overlay' : '') . '" aria-label="' . jvb_e($ariaLabel) . '" aria-roledescription="carousel" tabindex="0"'
        . ($style !== [] ? ' style="' . jvb_e(implode(';', $style)) . '"' : '')
        . ' data-jvb-content-slider data-jvb-autoplay="' . ($autoplay ? '1' : '0') . '" data-jvb-loop="' . ($loop ? '1' : '0') . '" data-jvb-delay="' . $delay . '">'
        . '<div class="jvb-content-slider__track" aria-live="off">';
    foreach ($slides as $index => $slide) {
        $active = $index === 0;
        $image = jvb_safe_frontend_url((string)($slide['image'] ?? ''));
        $position = jvb_css_position($slide['image_position'] ?? 'center center');
        $title = trim((string)($slide['title'] ?? ''));
        $accent = trim((string)($slide['accent'] ?? ''));
        $titleAfter = trim((string)($slide['title_after'] ?? ''));
        $description = trim((string)($slide['description'] ?? ''));
        $primary = $button('primary', (string)($slide['primary_label'] ?? ''), (string)($slide['primary_url'] ?? ''), true);
        $secondary = $button('secondary', (string)($slide['secondary_label'] ?? ''), (string)($slide['secondary_url'] ?? ''));
        $out .= '<article class="jvb-content-slide' . ($active ? ' is-active' : '') . '" data-jvb-content-slide role="group" aria-roledescription="slide"'
            . ' aria-label="' . ($index + 1) . ' of ' . $count . '" aria-hidden="' . ($active ? 'false' : 'true') . '"' . ($active ? '' : ' inert') . '>';
        if ($image !== '') {
            $out .= '<img class="jvb-content-slide__image" src="' . jvb_e($image) . '" alt="' . jvb_e((string)($slide['image_alt'] ?? '')) . '"'
                . ' loading="' . ($active ? 'eager' : 'lazy') . '"' . ($active ? ' fetchpriority="high"' : '')
                . ' decoding="async" style="object-position:' . jvb_e($position) . '">';
        }
        $out .= '<div class="jvb-content-slide__shade" aria-hidden="true"></div><div class="jvb-content-slide__content">';
        $eyebrow = trim((string)($slide['eyebrow'] ?? ''));
        if ($eyebrow !== '') $out .= '<p class="jvb-content-slide__eyebrow">' . jvb_e($eyebrow) . '</p>';
        if ($title !== '' || $accent !== '' || $titleAfter !== '') {
            $out .= '<' . $headingTag . ' class="jvb-content-slide__title">' . jvb_e($title)
                . ($accent !== '' ? ' <span>' . jvb_e($accent) . '</span>' : '')
                . ($titleAfter !== '' ? '<br>' . jvb_e($titleAfter) : '') . '</' . $headingTag . '>';
        }
        if ($description !== '') $out .= '<p class="jvb-content-slide__description">' . jvb_e($description) . '</p>';
        if ($primary !== '' || $secondary !== '') $out .= '<div class="jvb-content-slide__actions">' . $primary . $secondary . '</div>';
        $out .= '</div></article>';
    }
    $out .= '</div>';
    if ($count > 1 && ($nav || $dots)) {
        $out .= '<div class="jvb-content-slider__controls">';
        if ($nav) $out .= '<button class="jvb-content-slider__arrow" type="button" data-jvb-content-prev aria-label="Previous slide">&larr;</button>';
        if ($dots) {
            $out .= '<div class="jvb-content-slider__dots" role="tablist" aria-label="Choose slide">';
            foreach ($slides as $index => $_slide) {
                $out .= '<button class="jvb-content-slider__dot' . ($index === 0 ? ' is-active' : '') . '" type="button" data-jvb-content-dot="' . $index
                    . '" role="tab" aria-label="Slide ' . ($index + 1) . '" aria-selected="' . ($index === 0 ? 'true' : 'false') . '" tabindex="' . ($index === 0 ? '0' : '-1') . '"></button>';
            }
            $out .= '</div>';
        }
        if ($nav) $out .= '<button class="jvb-content-slider__arrow" type="button" data-jvb-content-next aria-label="Next slide">&rarr;</button>';
        $out .= '</div>';
    }
    if ($count > 1 && $progress) $out .= '<div class="jvb-content-slider__progress" aria-hidden="true"><span></span></div>';
    return $out . '</section>';
}

function jvb_render_card_rail(array $settings): string {
    $cards = is_array($settings['cards'] ?? null) ? $settings['cards'] : [];
    if ($cards === []) return '<div class="jvb-card-rail--empty">Add cards to this rail.</div>';

    $number = static function (array $settings, string $key, string $device, float $fallback, float $min, float $max): float {
        $value = jvb_dev($settings[$key] ?? null, $device, $fallback);
        return is_numeric($value) ? min($max, max($min, (float)$value)) : $fallback;
    };
    $widthD = $number($settings, 'item_width', 'd', 144, 80, 600);
    $widthT = $number($settings, 'item_width', 't', $widthD, 80, 600);
    $widthM = $number($settings, 'item_width', 'm', $widthT, 80, 600);
    $mediaD = $number($settings, 'media_height', 'd', 224, 60, 700);
    $mediaT = $number($settings, 'media_height', 't', $mediaD, 60, 700);
    $mediaM = $number($settings, 'media_height', 'm', $mediaT, 60, 700);
    $overlapD = $number($settings, 'item_overlap', 'd', 0, 0, 200);
    $overlapT = $number($settings, 'item_overlap', 't', $overlapD, 0, 200);
    $overlapM = $number($settings, 'item_overlap', 'm', $overlapT, 0, 200);
    $gapD = $number($settings, 'rail_gap', 'd', 0, 0, 200);
    $gapT = $number($settings, 'rail_gap', 't', $gapD, 0, 200);
    $gapM = $number($settings, 'rail_gap', 'm', $gapT, 0, 200);
    $radius = is_numeric($settings['card_radius'] ?? null) ? min(100, max(0, (float)$settings['card_radius'])) : 8;
    $surface = jvb_color($settings['card_bg_color'] ?? 'surface') ?: 'var(--jvb-surface)';
    $titleColor = jvb_color($settings['rail_title_color'] ?? 'text') ?: 'var(--jvb-text)';
    $textColor = jvb_color($settings['text_color'] ?? 'muted') ?: 'var(--jvb-muted)';
    $snap = in_array($settings['snap'] ?? '', ['none', 'proximity', 'mandatory'], true) ? $settings['snap'] : 'proximity';
    $hover = in_array($settings['hover_effect'] ?? '', ['none', 'lift', 'scale'], true) ? $settings['hover_effect'] : 'none';
    $centerOn = in_array($settings['center_on'] ?? '', ['none', 'mobile', 'all'], true) ? $settings['center_on'] : 'none';
    $ariaLabel = trim((string)($settings['aria_label'] ?? 'Featured content')) ?: 'Featured content';
    $style = '--jvb-rail-width-d:' . $widthD . 'px;--jvb-rail-width-t:' . $widthT . 'px;--jvb-rail-width-m:' . $widthM . 'px'
        . ';--jvb-rail-media-d:' . $mediaD . 'px;--jvb-rail-media-t:' . $mediaT . 'px;--jvb-rail-media-m:' . $mediaM . 'px'
        . ';--jvb-rail-overlap-d:' . $overlapD . 'px;--jvb-rail-overlap-t:' . $overlapT . 'px;--jvb-rail-overlap-m:' . $overlapM . 'px'
        . ';--jvb-rail-gap-d:' . $gapD . 'px;--jvb-rail-gap-t:' . $gapT . 'px;--jvb-rail-gap-m:' . $gapM . 'px'
        . ';--jvb-rail-radius:' . $radius . 'px;--jvb-rail-surface:' . $surface . ';--jvb-rail-title:' . $titleColor . ';--jvb-rail-text:' . $textColor;
    $out = '<section class="jvb-card-rail jvb-card-rail--snap-' . $snap . ' jvb-card-rail--hover-' . $hover . '" aria-label="' . jvb_e($ariaLabel) . '" data-jvb-card-rail data-jvb-center-on="' . $centerOn . '" style="' . jvb_e($style) . '">'
        . '<div class="jvb-card-rail__track" role="list" tabindex="0">';
    foreach ($cards as $index => $card) {
        if (!is_array($card)) continue;
        $title = trim((string)($card['title'] ?? ''));
        $text = trim((string)($card['text'] ?? ''));
        $image = jvb_safe_frontend_url((string)($card['image'] ?? ''));
        $metaImage = jvb_safe_frontend_url((string)($card['meta_image'] ?? ''));
        $url = jvb_safe_frontend_url((string)($card['url'] ?? ''));
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($card['key'] ?? ''));
        $position = jvb_css_position($card['image_position'] ?? 'center top');
        $scale = is_numeric($card['image_scale'] ?? null) ? min(125, max(50, (float)$card['image_scale'])) / 100 : 1;
        $initial = !empty($card['initial']);
        $tag = $url !== '' ? 'a' : 'div';
        $linkLabel = trim((string)($card['link_label'] ?? ''));
        $out .= '<article class="jvb-card-rail__item' . ($initial ? ' is-initial' : '') . '" role="listitem"'
            . ($key !== '' ? ' data-card-key="' . jvb_e($key) . '"' : '')
            . ($initial ? ' data-jvb-rail-initial' : '') . ' style="--jvb-rail-image-position:' . jvb_e($position) . ';--jvb-rail-image-scale:' . $scale . '">'
            . '<' . $tag . ' class="jvb-card-rail__link"' . ($url !== '' ? ' href="' . jvb_e($url) . '"' : '')
            . ($linkLabel !== '' && $url !== '' ? ' aria-label="' . jvb_e($linkLabel) . '"' : '') . '><div class="jvb-card-rail__media">';
        if ($image !== '') $out .= '<img src="' . jvb_e($image) . '" alt="' . jvb_e((string)($card['image_alt'] ?? '')) . '" loading="' . ($initial || $index === 0 ? 'eager' : 'lazy') . '" decoding="async">';
        $out .= '</div><div class="jvb-card-rail__body">';
        if ($title !== '') $out .= '<h3>' . jvb_e($title) . '</h3>';
        if ($text !== '') $out .= '<p>' . jvb_e($text) . '</p>';
        if ($metaImage !== '') $out .= '<img class="jvb-card-rail__meta-image" src="' . jvb_e($metaImage) . '" alt="' . jvb_e((string)($card['meta_image_alt'] ?? '')) . '" loading="lazy" decoding="async">';
        $out .= '</div></' . $tag . '></article>';
    }
    return $out . '</div></section>';
}

function jvb_render_preset_collection(PDO $pdo, array $settings, array $ctx): string {
    $presetId = filter_var($settings['preset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($presetId === false) {
        return !empty($ctx['canvas'])
            ? '<div class="jvb-preset-collection--empty">Select a published Content Preset.</div>'
            : '';
    }

    $layout = trim((string)($settings['layout'] ?? ''));
    if (!array_key_exists($layout, jvb_preset_layout_options())) $layout = '';
    $overrides = $layout !== '' ? ['layout' => $layout] : [];
    $limit = filter_var($settings['limit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $maxItems = filter_var($settings['max_items'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($limit !== false) $overrides['limit'] = min(200, (int)$limit);
    if ($maxItems !== false) $overrides['max_items'] = min(10000, max((int)($overrides['limit'] ?? 1), (int)$maxItems));
    if (!empty($ctx['fallback'])) $overrides['pagination'] = '0';
    $renderContext = [
        'surface' => 'plugin.jyavani-builder',
        'post_id' => (int)($ctx['post']['id'] ?? 0),
        'element_id' => (string)($ctx['element_id'] ?? ''),
        'canvas' => !empty($ctx['canvas']),
    ];
    $html = function_exists('render_shortcode_preset')
        ? render_shortcode_preset($pdo, (int)$presetId, $overrides, $renderContext)
        : '';
    if (trim($html) === '') {
        return !empty($ctx['canvas'])
            ? '<div class="jvb-preset-collection--empty">The selected preset is unavailable or returned no content.</div>'
            : '';
    }

    $header = '';
    if (!empty($settings['show_header'])) {
        $title = trim((string)($settings['title'] ?? ''));
        $summary = trim((string)($settings['summary'] ?? ''));
        $moreText = trim((string)($settings['more_text'] ?? ''));
        $moreUrl = jvb_safe_frontend_url((string)($settings['more_url'] ?? ''));
        if ($title !== '' || $summary !== '' || ($moreText !== '' && $moreUrl !== '')) {
            $copy = ($title !== '' ? '<h2 class="jvb-preset-collection__title">' . jvb_e($title) . '</h2>' : '')
                . ($summary !== '' ? '<p class="jvb-preset-collection__summary">' . jvb_e($summary) . '</p>' : '');
            $more = $moreText !== '' && $moreUrl !== ''
                ? '<a class="jvb-preset-collection__more" href="' . jvb_e($moreUrl) . '">' . jvb_e($moreText) . '</a>'
                : '';
            $header = '<header class="jvb-preset-collection__head"><div>' . $copy . '</div>' . $more . '</header>';
        }
    }

    $layoutClass = $layout !== '' ? ' jvb-preset-collection--' . $layout : '';
    return '<section class="jvb-preset-collection' . $layoutClass . '" data-preset-id="' . (int)$presetId . '">'
        . $header . '<div class="jvb-preset-collection__content">' . $html . '</div></section>';
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
            $semanticClass = trim('jvb-heading ' . jvb_inner_class($s));
            $semanticId = jvb_inner_id($s);
            $inner = '<' . $tag . ' class="' . jvb_e($semanticClass) . '"' . ($semanticId !== '' ? ' id="' . jvb_e($semanticId) . '"' : '') . ' data-jvb-inline="text">' . $text . '</' . $tag . '>';
            break;
        }
        case 'richtext': {
            $semanticClass = trim('jvb-richtext ' . jvb_inner_class($s));
            $content = jvb_expand_shortcodes((string)($s['content'] ?? ''), $pdo);
            $innerClass = jvb_inner_class($s);
            if (($s['inner_target'] ?? 'container') === 'first-child') {
                $content = jvb_first_child_class($content, $innerClass);
                $semanticClass = 'jvb-richtext';
            }
            $inner = '<div class="' . jvb_e($semanticClass) . '" data-jvb-inline="content">' . $content . '</div>';
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
            $figureClass = trim('jvb-image ' . jvb_inner_class($s));
            $inner = '<figure class="' . jvb_e($figureClass) . '">' . $img . $cap . '</figure>';
            break;
        }
        case 'button': {
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $style = in_array($s['btn_style'] ?? '', ['solid', 'outline', 'ghost'], true) ? $s['btn_style'] : 'solid';
            $size = in_array($s['size'] ?? '', ['sm', 'md', 'lg'], true) ? $s['size'] : 'md';
            $target = !empty($s['target']) ? ' target="_blank" rel="noopener"' : '';
            $full = !empty($s['full']) ? ' jvb-btn--full' : '';
            $extraClass = preg_replace('/[^a-zA-Z0-9 _-]/', '', (string)($s['css_class'] ?? ''));
            $icon = trim((string)($s['icon'] ?? ''));
            $iconHtml = $icon !== '' ? '<span class="jvb-btn__icon" aria-hidden="true">' . jvb_content_icon((string)($s['icon_pack'] ?? 'core'), $icon, 18) . '</span>' : '';
            $textHtml = '<span>' . jvb_e($s['text'] ?? 'Button') . '</span>';
            $label = ($s['icon_position'] ?? 'right') === 'left' ? $iconHtml . $textHtml : $textHtml . $iconHtml;
            $inner = '<a class="jvb-btn jvb-btn--' . $style . ' jvb-btn--' . $size . $full . ($extraClass !== '' ? ' ' . trim($extraClass) : '') . '" href="' . jvb_e($s['url'] ?? '#') . '"' . $target . ' style="--jvb-btn:' . jvb_e($color) . '" data-jvb-inline="text">' . $label . '</a>';
            break;
        }
        case 'icon': {
            $size = max(8, min(256, (int)($s['size'] ?? 32)));
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $svg = jvb_content_icon((string)($s['icon_pack'] ?? 'core'), (string)($s['name'] ?? 'star'), $size);
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
            $w = min(100, max(5, (float)jvb_dev($s['width_pct'] ?? null, 'd', 100)));
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
        case 'profile_quote': {
            $kicker = trim((string)($s['kicker'] ?? ''));
            $heading = trim((string)($s['heading'] ?? ''));
            $headingTag = in_array($s['heading_tag'] ?? '', ['h2', 'h3', 'h4'], true) ? $s['heading_tag'] : 'h2';
            $headingId = 'jvb-profile-quote-' . substr(md5($id), 0, 8);
            $name = trim((string)($s['name'] ?? ''));
            $roleOne = trim((string)($s['role_one'] ?? ''));
            $roleTwo = trim((string)($s['role_two'] ?? ''));
            $ctaText = trim((string)($s['cta_text'] ?? ''));
            $ctaUrl = jvb_safe_frontend_url((string)($s['cta_url'] ?? ''));
            $image = jvb_safe_frontend_url((string)($s['image'] ?? ''));
            $imageAlt = trim((string)($s['image_alt'] ?? ''));
            $imageLink = jvb_safe_frontend_url((string)($s['image_link'] ?? ''));
            $portraitPosition = ($s['portrait_position'] ?? '') === 'left' ? 'left' : 'right';
            $surface = jvb_color($s['surface_color'] ?? 'secondary') ?: 'var(--jvb-secondary)';
            $text = jvb_color($s['text_color'] ?? '#ffffff') ?: '#ffffff';
            $accent = jvb_color($s['accent_color'] ?? 'accent') ?: 'var(--jvb-accent)';
            $out = '<section class="jvb-profile-quote jvb-profile-quote--portrait-' . $portraitPosition . '" style="' . jvb_e('--jvb-profile-surface:' . $surface . ';--jvb-profile-text:' . $text . ';--jvb-profile-accent:' . $accent) . '"'
                . ($heading !== '' ? ' aria-labelledby="' . $headingId . '"' : '') . '><div class="jvb-profile-quote__copy">';
            if ($kicker !== '') $out .= '<p class="jvb-profile-quote__kicker">' . jvb_e($kicker) . '</p>';
            if ($heading !== '') $out .= '<' . $headingTag . ' id="' . $headingId . '" class="jvb-profile-quote__heading">' . jvb_e($heading) . '</' . $headingTag . '>';
            $quote = jvb_safe_rich_html((string)($s['quote'] ?? ''));
            $out .= '<blockquote class="jvb-profile-quote__quote"><span aria-hidden="true">&ldquo;</span>' . jvb_expand_shortcodes($quote, $pdo) . '</blockquote>';
            if ($name !== '' || $roleOne !== '' || $roleTwo !== '') {
                $out .= '<footer class="jvb-profile-quote__person">';
                if ($name !== '') $out .= '<cite>' . jvb_e($name) . '</cite>';
                if ($roleOne !== '') $out .= '<span>' . jvb_e($roleOne) . '</span>';
                if ($roleTwo !== '') $out .= '<span>' . jvb_e($roleTwo) . '</span>';
                $out .= '</footer>';
            }
            if ($ctaText !== '' && $ctaUrl !== '') $out .= '<a class="jvb-profile-quote__cta" href="' . jvb_e($ctaUrl) . '"><span>' . jvb_e($ctaText) . '</span><span aria-hidden="true">&rarr;</span></a>';
            $out .= '</div><figure class="jvb-profile-quote__portrait">';
            $imageHtml = $image !== '' ? '<img src="' . jvb_e($image) . '" alt="' . jvb_e($imageAlt) . '" loading="lazy" decoding="async">' : '<div class="jvb-image--empty">No portrait selected</div>';
            $out .= $imageLink !== '' ? '<a href="' . jvb_e($imageLink) . '"' . ($imageAlt !== '' ? ' aria-label="' . jvb_e($imageAlt) . '"' : '') . '>' . $imageHtml . '</a>' : $imageHtml;
            $inner = $out . '</figure></section>';
            break;
        }
        case 'tabs': {
            $items = is_array($s['items'] ?? null) ? $s['items'] : [];
            if (!$items) { $inner = ''; break; }
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $activeText = jvb_color($s['active_text_color'] ?? '#ffffff') ?: '#ffffff';
            $inactiveText = jvb_color($s['inactive_text_color'] ?? 'muted') ?: 'var(--jvb-muted)';
            $panelBg = jvb_color($s['panel_bg_color'] ?? 'surface') ?: 'var(--jvb-surface)';
            $panelText = jvb_color($s['panel_text_color'] ?? 'text') ?: 'var(--jvb-text)';
            $panelBorder = jvb_color($s['panel_border_color'] ?? 'border') ?: 'var(--jvb-border)';
            $panelMax = jvb_css_length($s['panel_max_width'] ?? '64rem') ?: '64rem';
            $panelRadius = jvb_css_length($s['panel_radius'] ?? '1.5rem') ?: '1.5rem';
            $panelShadow = jvb_css_shadow($s['panel_shadow'] ?? '');
            $variant = in_array($s['variant'] ?? '', ['underline', 'pills-card'], true) ? $s['variant'] : 'underline';
            $navAlign = in_array($s['nav_align'] ?? '', ['start', 'center', 'end', 'stretch'], true) ? $s['nav_align'] : 'start';
            $listStyle = ($s['list_style'] ?? '') === 'numbered-rows' ? 'numbered-rows' : 'plain';
            $ariaLabel = trim((string)($s['aria_label'] ?? 'Content tabs')) ?: 'Content tabs';
            $uid = 't' . substr(md5($id), 0, 6);
            $tabStyle = '--jvb-tab:' . $color . ';--jvb-tab-active-text:' . $activeText . ';--jvb-tab-inactive-text:' . $inactiveText
                . ';--jvb-tabs-panel-bg:' . $panelBg . ';--jvb-tabs-panel-text:' . $panelText . ';--jvb-tabs-panel-border:' . $panelBorder
                . ';--jvb-tabs-panel-max:' . $panelMax . ';--jvb-tabs-panel-radius:' . $panelRadius
                . ($panelShadow !== '' ? ';--jvb-tabs-panel-shadow:' . $panelShadow : '');
            $out = '<div class="jvb-tabs jvb-tabs--' . $variant . ' jvb-tabs--align-' . $navAlign . ' jvb-tabs--list-' . $listStyle . '" style="' . jvb_e($tabStyle) . '"><div class="jvb-tabs__nav" role="tablist" aria-label="' . jvb_e($ariaLabel) . '">';
            foreach ($items as $i => $it) {
                $out .= '<button type="button" role="tab" id="' . $uid . '-tab' . $i . '" aria-controls="' . $uid . '-pnl' . $i . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '" tabindex="' . ($i === 0 ? '0' : '-1') . '"' . ($i === 0 ? ' class="is-active"' : '') . '>' . jvb_e($it['title'] ?? '') . '</button>';
            }
            $out .= '</div>';
            foreach ($items as $i => $it) {
                $out .= '<div role="tabpanel" id="' . $uid . '-pnl' . $i . '" aria-labelledby="' . $uid . '-tab' . $i . '" class="jvb-tabs__panel' . ($i === 0 ? ' is-active' : '') . '"' . ($i > 0 ? ' hidden' : '') . '><div class="jvb-tabs__content">' . jvb_expand_shortcodes((string)($it['content'] ?? ''), $pdo) . '</div>';
                $listItems = is_array($it['list_items'] ?? null) ? $it['list_items'] : [];
                if ($listItems !== []) {
                    $out .= '<ol class="jvb-tabs__list">';
                    foreach ($listItems as $listIndex => $listItem) {
                        $listText = trim((string)($listItem['text'] ?? ''));
                        if ($listText !== '') $out .= '<li><span class="jvb-tabs__marker" aria-hidden="true">' . str_pad((string)($listIndex + 1), 2, '0', STR_PAD_LEFT) . '</span><p>' . jvb_e($listText) . '</p></li>';
                    }
                    $out .= '</ol>';
                }
                $out .= '</div>';
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
        case 'carousel': {
            if (($s['presentation'] ?? '') === 'content-slides') { $inner = jvb_render_content_slider($s); break; }
            if (($s['presentation'] ?? '') === 'card-rail') { $inner = jvb_render_card_rail($s); break; }
            $images = is_array($s['images'] ?? null) ? $s['images'] : [];
            $pv = is_array($s['per_view'] ?? null) ? $s['per_view'] : [];
            $perD = max(1, min(4, (int)jvb_dev($pv, 'd', 1)));
            $perT = max(1, min(4, (int)jvb_dev($pv, 't', $perD)));
            $perM = max(1, min(4, (int)jvb_dev($pv, 'm', $perT)));
            $gap = is_numeric($s['gap'] ?? null) ? max(0, (int)$s['gap']) : 16;
            $effect = ($s['effect'] ?? '') === 'fade' ? 'fade' : 'slide';
            $delay = is_numeric($s['delay'] ?? null) ? max(1000, (int)$s['delay']) : 4000;
            $loop = !empty($s['loop']);
            $nav = !empty($s['nav']);
            $dots = !empty($s['dots']);
            $radius = (isset($s['radius']) && is_numeric($s['radius'])) ? max(0, (int)$s['radius']) : 0;
            $ratioRaw = preg_replace('/[^0-9\/]/', '', (string)($s['ratio'] ?? '16/9')) ?: '16/9';
            $ratioCss = str_replace('/', ' / ', $ratioRaw);
            $domId = 'jvbcr-' . ($id !== '' ? preg_replace('/[^a-zA-Z0-9_-]/', '', $id) : substr(md5(uniqid('', true)), 0, 8));
            $slides = '';
            foreach ($images as $img) {
                if (!is_array($img)) continue;
                $url = trim((string)($img['url'] ?? ''));
                if ($url === '') continue;
                $slides .= '<div class="swiper-slide jvb-carousel__slide"><img src="' . jvb_e($url) . '" alt="' . jvb_e((string)($img['alt'] ?? '')) . '" loading="lazy"></div>';
            }
            if ($slides === '') { $inner = '<div class="jvb-carousel jvb-carousel--empty">No slides</div>'; break; }
            $cfg = [
                'slidesPerView' => $perM,
                'spaceBetween' => $gap,
                'loop' => $loop,
                'effect' => $effect,
                'breakpoints' => [
                    768 => ['slidesPerView' => $perT],
                    1025 => ['slidesPerView' => $perD],
                ],
            ];
            if ($effect === 'fade') $cfg['fadeEffect'] = ['crossFade' => true];
            if (!empty($s['autoplay'])) $cfg['autoplay'] = ['delay' => $delay, 'disableOnInteraction' => false, 'pauseOnMouseEnter' => true];
            if ($nav) $cfg['navigation'] = ['nextEl' => '#' . $domId . ' .jvb-carousel__next', 'prevEl' => '#' . $domId . ' .jvb-carousel__prev'];
            if ($dots) $cfg['pagination'] = ['el' => '#' . $domId . ' .jvb-carousel__dots', 'clickable' => true];
            $style = '--jvb-cr-ratio:' . $ratioCss . ($radius > 0 ? ';--jvb-cr-radius:' . $radius . 'px' : '');
            // Swiper ships with CMS core (layout.php) — survives plugin uninstall.
            $inner = '<div id="' . jvb_e($domId) . '" class="jvb-carousel swiper" data-jvb-carousel="' . jvb_e(json_encode($cfg, JSON_UNESCAPED_SLASHES)) . '" style="' . $style . '">'
                . '<div class="swiper-wrapper">' . $slides . '</div>'
                . ($nav ? '<div class="jvb-carousel__prev swiper-button-prev"></div><div class="jvb-carousel__next swiper-button-next"></div>' : '')
                . ($dots ? '<div class="jvb-carousel__dots swiper-pagination"></div>' : '')
                . '</div>';
            break;
        }
        case 'card': {
            $color = jvb_color($s['color'] ?? 'primary') ?: 'var(--jvb-primary)';
            $layout = in_array(($s['layout'] ?? ''), ['left', 'background'], true) ? (string)$s['layout'] : 'top';
            $radius = (isset($s['radius']) && is_numeric($s['radius'])) ? max(0, (int)$s['radius']) : 12;
            $imgUrl = trim((string)($s['image'] ?? ''));
            $badge = trim((string)($s['badge'] ?? ''));
            $btnText = trim((string)($s['btn_text'] ?? ''));
            $btnUrl = jvb_safe_frontend_url((string)($s['btn_url'] ?? '')) ?: '#';
            $actionIcon = trim((string)($s['action_icon'] ?? ''));
            $media = '';
            if ($imgUrl !== '') {
                $media = '<div class="jvb-card__media"><img src="' . jvb_e($imgUrl) . '" alt="' . jvb_e((string)($s['img_alt'] ?? '')) . '" loading="lazy">'
                    . ($layout !== 'background' && $badge !== '' ? '<span class="jvb-card__badge" style="--jvb-card:' . jvb_e($color) . '">' . jvb_e($badge) . '</span>' : '')
                    . '</div>';
            }
            if ($layout === 'background') {
                $badgeHtml = $badge !== '' ? '<span class="jvb-card__badge">' . jvb_e($badge) . '</span>' : '';
                $action = $actionIcon !== '' ? '<span class="jvb-card__action" aria-hidden="true">' . jvb_content_icon((string)($s['action_icon_pack'] ?? 'core'), $actionIcon, 28) . '</span>' : '';
                $inner = '<a class="jvb-card jvb-card--background' . (!empty($s['shadow']) ? ' jvb-card--shadow' : '') . '" href="' . jvb_e($btnUrl) . '" style="--jvb-card:' . jvb_e($color) . ';--jvb-card-radius:' . $radius . 'px">'
                    . $media . '<span class="jvb-card__body"><span class="jvb-card__copy">' . $badgeHtml
                    . '<strong class="jvb-card__title">' . jvb_e((string)($s['title'] ?? '')) . '</strong>'
                    . '<span class="jvb-card__text">' . jvb_e((string)($s['text'] ?? '')) . '</span></span>' . $action . '</span></a>';
                break;
            }
            $btn = $btnText !== ''
                ? '<a class="jvb-btn jvb-btn--solid jvb-btn--md" href="' . jvb_e($btnUrl) . '" style="--jvb-btn:' . jvb_e($color) . '">' . jvb_e($btnText) . '</a>'
                : '';
            $inner = '<div class="jvb-card jvb-card--' . $layout . (!empty($s['shadow']) ? ' jvb-card--shadow' : '') . '" style="--jvb-card-radius:' . $radius . 'px">'
                . $media
                . '<div class="jvb-card__body">'
                . '<h3 class="jvb-card__title">' . jvb_e((string)($s['title'] ?? '')) . '</h3>'
                . '<p class="jvb-card__text">' . jvb_e((string)($s['text'] ?? '')) . '</p>'
                . $btn
                . '</div></div>';
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
            $body = '<span class="jvb-iconbox__icon" style="color:' . jvb_e($color) . '">' . jvb_content_icon((string)($s['icon_pack'] ?? 'core'), (string)($s['icon'] ?? 'zap'), 32) . '</span>'
                . '<div class="jvb-iconbox__body"><h3 class="jvb-iconbox__title">' . jvb_e($s['title'] ?? '') . '</h3><p>' . jvb_e($s['text'] ?? '') . '</p></div>';
            $inner = '<div class="jvb-iconbox jvb-iconbox--' . $layout . '">' . $body . '</div>';
            if (!empty($s['url'])) $inner = '<a class="jvb-iconbox-link" href="' . jvb_e($s['url']) . '">' . $inner . '</a>';
            break;
        }
        case 'preset_collection': {
            $ctx['element_id'] = $id;
            $inner = jvb_render_preset_collection($pdo, $s, $ctx);
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
        return '<div class="jvb-posts jvb-posts--empty">' . jvb_e(function_exists('__') ? __('Could not load posts') : 'Could not load posts') . '</div>';
    }
    if (!$posts) return '<div class="jvb-posts jvb-posts--empty">' . jvb_e(function_exists('__') ? __('No posts found') : 'No posts found') . '</div>';

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
            . $excerpt . '<a class="jvb-posts__more" href="' . jvb_e($url) . '">' . jvb_e(function_exists('__') ? __('Read more →') : 'Read more →') . '</a></div></article>';
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
        . '<div class="jvb-section__inner' . (($innerClass = jvb_inner_class($s)) !== '' ? ' ' . jvb_e($innerClass) : '') . '">' . $rowsHtml . '</div>'
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
        $nodeKind = $kind === 'element' ? 'element' : $kind;
        $styleSel = ($kind === 'element' && ($node['type'] ?? '') === 'button') ? $sel . '>.jvb-btn' : $sel;
        $elementType = $kind === 'element' ? (string)($node['type'] ?? '') : '';
        $css .= jvb_wrap_css($styleSel, jvb_node_css($s, $nodeKind, $elementType));
        if ($kind === 'element' && ($node['type'] ?? '') === 'tabs') $css .= jvb_tabs_css($s, $sel);
        if (in_array($kind, ['section', 'element'], true)) {
            $custom = trim((string)($s['custom_css'] ?? ''));
            if ($custom !== '') {
                $custom = str_ireplace(['</style', '{', '}'], ['', '', ''], $custom);
                $customSel = ($kind === 'element' && ($node['type'] ?? '') === 'button') ? $sel . '>.jvb-btn' : $sel;
                $css .= $customSel . '{' . $custom . '}';
            }
        }
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

    $ctx = [
        'tokens' => $tokens,
        'post' => $post,
        'canvas' => !empty($opts['canvas']),
        'fallback' => !empty($opts['inline_css']),
    ];

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
    // With 'inline_css' the stylesheet is embedded instead — used for the DB
    // fallback copy so published pages stay fully styled even if the plugin
    // (and its static assets) is later uninstalled.
    static $baseLinked = false;
    $baseLink = '';
    if (!$baseLinked && empty($opts['canvas'])) {
        $baseLinked = true;
        if (!empty($opts['inline_css'])) {
            $cssFile = dirname(__DIR__) . '/assets/frontend.css';
            $baseLink = is_file($cssFile) ? '<style>' . file_get_contents($cssFile) . '</style>' . "\n" : '';
        } elseif (empty($GLOBALS['_jvb_base_stylesheet_linked'])) {
            $GLOBALS['_jvb_base_stylesheet_linked'] = true;
            $baseLink = '<link rel="stylesheet" href="' . jvb_asset_url('frontend.css') . '">' . "\n";
        }
    }

    // jvb-anim-on activates animation initial states only when JS is available.
    $script = '<script>(function(c){c.classList.add("jvb-anim-on")})(document.currentScript.parentNode)</script>';

    return $baseLink . '<div class="jvb-page" data-jvb-post="' . $postId . '">'
        . '<style>' . $css . '</style>'
        . $script
        . $sections
        . '</div>';
}
