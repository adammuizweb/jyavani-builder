# Jy Builder — AI Landing Page Generation Spec

> **Purpose**: This document teaches an AI assistant how to generate valid JSON layouts
> for the **Jy Builder** plugin (Jyavani CMS). Paste this file into any AI chat along with
> your landing page description, and the AI will output a JSON layout you can import
> directly into the builder — then fine-tune visually.

---

## How to Use

1. Copy this entire file into your AI chat (ChatGPT, Claude, Gemini, etc.)
2. Add your request, e.g.:
   - *"Create a landing page for a SaaS product called CloudSync"*
   - *"Buat landing page untuk toko online batik"*
   - *"Make a portfolio landing page for a photographer"*
3. The AI will output a JSON block
4. In Jy Builder, open the builder and paste the JSON via **Import** (or save it as a template)
5. Edit visually — change text, colors, images, reorder sections

---

## Architecture Overview

Jy Builder uses a **4-level hierarchy**:

```
Section (full-width horizontal band)
  └─ Row (horizontal flex container)
       └─ Column (vertical container, width %)
            └─ Element (content block: heading, button, image, etc.)
```

- A **Section** has 1+ rows
- A **Row** has 1+ columns
- A **Column** has 0+ elements
- An **Element** is a leaf node (heading, button, image, etc.)

---

## JSON Layout Format (v3)

The root layout object:

```json
{
  "v": 3,
  "settings": {
    "custom_css": ""
  },
  "sections": [ /* array of Section objects */ ]
}
```

### Section

```json
{
  "id": "s_abc123",
  "settings": {
    "layout": "boxed",
    "min_height": "",
    "bg_color": "",
    "bg_from": "",
    "bg_to": "",
    "bg_angle": 135,
    "bg_image": "",
    "bg_size": "cover",
    "bg_position": "center",
    "bg_attachment": "",
    "overlay_color": "",
    "overlay_opacity": 0,
    "padding": { "d": { "t": 80, "b": 80, "unit": "px" } },
    "animation": "",
    "anim_delay": 0,
    "shape_top": "none",
    "shape_top_color": "",
    "shape_bottom": "none",
    "shape_bottom_color": "",
    "class": "",
    "css_id": "",
    "hide_on": []
  },
  "rows": [ /* array of Row objects */ ]
}
```

#### Section settings reference

| Key | Type | Values | Description |
|-----|------|--------|-------------|
| `layout` | string | `"boxed"`, `"full"`, `"stretch"` | `boxed` = theme container width; `full` = background bleeds full-width, content boxed; `stretch` = everything edge-to-edge |
| `min_height` | string | `"80vh"`, `"600px"`, `""` | Minimum section height |
| `bg_color` | string | token name or hex | Solid background color. Leave empty for no color. |
| `bg_from` / `bg_to` | string | token name or hex | Gradient endpoints. Set BOTH for gradient background. |
| `bg_angle` | number | 0–360 | Gradient angle (degrees). Default 135. |
| `bg_image` | string | URL | Background image URL. Takes priority over gradient/color. |
| `bg_size` | string | `"cover"`, `"contain"`, `"auto"` | Background-size |
| `bg_position` | string | `"center"`, `"top center"`, etc. | Background-position |
| `bg_attachment` | string | `""`, `"fixed"` | `"fixed"` = parallax |
| `overlay_color` | string | token or hex | Overlay color on top of background |
| `overlay_opacity` | number | 0–1 | Overlay opacity |
| `padding` | object | per-device spacing | See Spacing format below |
| `animation` | string | `""`, `"fade"`, `"fade-up"`, `"fade-down"`, `"fade-left"`, `"fade-right"`, `"zoom-in"`, `"flip-up"` | Entrance animation |
| `anim_delay` | number | ms | Animation delay |
| `shape_top` / `shape_bottom` | string | `"none"`, `"wave"`, `"tilt"`, `"curve"`, `"triangle"`, `"zigzag"` | SVG shape dividers |
| `shape_top_color` / `shape_bottom_color` | string | token or hex | Shape fill color |
| `class` | string | CSS class | Custom CSS class |
| `css_id` | string | CSS ID | Anchor target (e.g. `"contact"` → `#contact`) |
| `hide_on` | array | `["desktop"]`, `["tablet"]`, `["mobile"]` | Hide on specific devices |

### Row

```json
{
  "id": "r_def456",
  "settings": {
    "gap": 20,
    "align": "",
    "wrap": "wrap",
    "bg_color": "",
    "padding": {},
    "margin": {},
    "class": "",
    "css_id": ""
  },
  "cols": [ /* array of Column objects */ ]
}
```

#### Row settings reference

| Key | Type | Values | Description |
|-----|------|--------|-------------|
| `gap` | number | px | Gap between columns |
| `align` | string | `""`, `"center"`, `"start"`, `"end"`, `"space-between"` | Vertical alignment of columns |
| `wrap` | string | `""`, `"wrap"` | `"wrap"` = columns stack on mobile |
| `bg_color` | string | token or hex | Row background color |
| `padding` / `margin` | object | per-device spacing | See Spacing format |
| `class` / `css_id` | string | | Custom CSS |

### Column

```json
{
  "id": "c_ghi789",
  "settings": {
    "width": { "d": 50, "t": 100, "m": 100 },
    "bg_color": "",
    "padding": {},
    "align": { "d": "" },
    "valign": "",
    "class": "",
    "css_id": "",
    "hide_on": []
  },
  "elements": [ /* array of Element objects */ ]
}
```

#### Column settings reference

| Key | Type | Values | Description |
|-----|------|--------|-------------|
| `width` | object | per-device % | `{"d": 50, "t": 100, "m": 100}` — desktop 50%, tablet/mobile 100% |
| `bg_color` | string | token or hex | Column background |
| `padding` | object | per-device spacing | See Spacing format |
| `align` | object | per-device | `{"d": "center"}` — text alignment |
| `valign` | string | `""`, `"center"`, `"end"`, `"space-between"` | Vertical alignment of elements |
| `class` / `css_id` | string | | Custom CSS |
| `hide_on` | array | | Hide on devices |

---

## Element Types (22 available)

Each element has: `id`, `type`, `settings` (type-specific).

### Basic Elements

#### heading
```json
{
  "id": "e_001", "type": "heading",
  "settings": {
    "text": "Your Headline",
    "tag": "h2",
    "link": "",
    "color": "",
    "align": { "d": "center" },
    "typography": { "d": { "size": 40, "weight": "800" }, "m": { "size": 28 } },
    "margin": {}
  }
}
```
- `tag`: `"h1"` through `"h6"`
- `typography`: `{size: px, weight: 100-900, unit: "px"|"rem"|"em", line: number, spacing: px, transform: "uppercase"|"lowercase"|"capitalize"|"none"}`
- `color`: token name or hex (empty = inherit)

#### richtext
```json
{
  "id": "e_002", "type": "richtext",
  "settings": {
    "content": "<p>Your paragraph text here.</p>",
    "align": { "d": "" },
    "color": "",
    "typography": {},
    "margin": {}
  }
}
```
- `content`: HTML string (supports `<p>`, `<ul>`, `<ol>`, `<strong>`, `<em>`, `<a>`, `<blockquote>`)

#### image
```json
{
  "id": "e_003", "type": "image",
  "settings": {
    "src": "https://example.com/photo.jpg",
    "alt": "Description",
    "caption": "",
    "link": "",
    "width_pct": { "d": 100 },
    "radius": "",
    "align": { "d": "" }
  }
}
```

#### button
```json
{
  "id": "e_004", "type": "button",
  "settings": {
    "text": "Get Started",
    "url": "#",
    "target": false,
    "btn_style": "solid",
    "size": "lg",
    "color": "primary",
    "align": { "d": "center" },
    "full": false,
    "margin": {}
  }
}
```
- `btn_style`: `"solid"`, `"outline"`, `"ghost"`
- `size`: `"sm"`, `"md"`, `"lg"`
- `color`: token name or hex
- `full`: true = full-width button

#### icon
```json
{
  "id": "e_005", "type": "icon",
  "settings": {
    "name": "star",
    "size": 32,
    "color": "primary",
    "link": "",
    "align": { "d": "" }
  }
}
```
- `name`: any [Lucide icon](https://lucide.dev/icons) name (kebab-case)

#### spacer
```json
{
  "id": "e_006", "type": "spacer",
  "settings": { "height": { "d": 40, "m": 20 } }
}
```

#### divider
```json
{
  "id": "e_007", "type": "divider",
  "settings": {
    "width_pct": 100,
    "thickness": 1,
    "line_style": "solid",
    "color": "border",
    "align": { "d": "" },
    "margin": {}
  }
}
```
- `line_style`: `"solid"`, `"dashed"`, `"dotted"`, `"double"`

#### video
```json
{
  "id": "e_008", "type": "video",
  "settings": {
    "src": "https://www.youtube.com/watch?v=...",
    "ratio": "16/9",
    "facade": true,
    "radius": ""
  }
}
```
- Supports YouTube, Vimeo, direct video files (.mp4, .webm, .ogg)
- `ratio`: `"16/9"`, `"4/3"`, `"1/1"`, `"21/9"`

### Content Elements

#### accordion
```json
{
  "id": "e_009", "type": "accordion",
  "settings": {
    "items": [
      { "title": "Question 1", "content": "<p>Answer 1</p>" },
      { "title": "Question 2", "content": "<p>Answer 2</p>" }
    ],
    "first_open": true,
    "icon_color": "primary"
  }
}
```

#### tabs
```json
{
  "id": "e_010", "type": "tabs",
  "settings": {
    "items": [
      { "title": "Tab 1", "content": "<p>Content 1</p>" },
      { "title": "Tab 2", "content": "<p>Content 2</p>" }
    ],
    "color": "primary"
  }
}
```

#### counter
```json
{
  "id": "e_011", "type": "counter",
  "settings": {
    "number": 500,
    "prefix": "",
    "suffix": "+",
    "duration": 1500,
    "color": "primary",
    "align": { "d": "center" },
    "typography": { "d": { "size": 48, "weight": "700" } }
  }
}
```

#### countdown
```json
{
  "id": "e_012", "type": "countdown",
  "settings": {
    "target": "2026-12-31T23:59:59",
    "labels": true,
    "color": "primary",
    "align": { "d": "center" }
  }
}
```

#### gallery
```json
{
  "id": "e_013", "type": "gallery",
  "settings": {
    "images": [
      { "src": "https://example.com/1.jpg", "alt": "Photo 1" },
      { "src": "https://example.com/2.jpg", "alt": "Photo 2" }
    ],
    "columns": { "d": 3, "t": 2, "m": 1 },
    "gap": 12,
    "radius": "",
    "lightbox": true
  }
}
```

#### carousel
```json
{
  "id": "e_021", "type": "carousel",
  "settings": {
    "images": [
      { "url": "https://example.com/1.jpg", "alt": "Slide 1" },
      { "url": "https://example.com/2.jpg", "alt": "Slide 2" }
    ],
    "per_view": { "d": 1, "t": 1, "m": 1 },
    "gap": 16,
    "ratio": "16/9",
    "effect": "slide",
    "autoplay": false,
    "delay": 4000,
    "loop": true,
    "nav": true,
    "dots": true,
    "radius": ""
  }
}
```
- Swiper-based (Swiper ships with CMS core — keeps working even if the plugin is uninstalled)
- `per_view`: slides visible per device (1–4)
- `ratio`: `"16/9"`, `"4/3"`, `"1/1"`, `"3/4"`, `"21/9"`
- `effect`: `"slide"` or `"fade"`
- `autoplay`: true = auto-advance every `delay` ms (pauses on hover)

#### card
```json
{
  "id": "e_022", "type": "card",
  "settings": {
    "image": "https://example.com/photo.jpg",
    "img_alt": "Photo",
    "badge": "New",
    "title": "Card title",
    "text": "Supporting text for this card.",
    "btn_text": "Learn more",
    "btn_url": "#",
    "layout": "top",
    "radius": 12,
    "shadow": true,
    "color": "primary"
  }
}
```
- `layout`: `"top"` (image above) or `"left"` (image beside, 40% width)
- `badge`: optional floating label on the image (omit/empty = none)
- `btn_text`/`btn_url`: optional button (omit btn_text = no button)

#### testimonial
```json
{
  "id": "e_014", "type": "testimonial",
  "settings": {
    "quote": "This product changed everything for us.",
    "name": "Jane Doe",
    "role": "CEO, Company",
    "avatar": "",
    "rating": 5,
    "color": "primary",
    "align": { "d": "" }
  }
}
```

#### pricing
```json
{
  "id": "e_015", "type": "pricing",
  "settings": {
    "plan": "Pro",
    "price": "49",
    "currency": "$",
    "period": "/mo",
    "features": "Feature one\nFeature two\nFeature three",
    "btn_text": "Get Started",
    "btn_url": "#",
    "highlight": false,
    "badge": "Popular",
    "color": "primary"
  }
}
```
- `features`: newline-separated list
- `highlight`: true = visually emphasized plan

#### iconbox
```json
{
  "id": "e_016", "type": "iconbox",
  "settings": {
    "icon": "zap",
    "title": "Fast",
    "text": "Optimized for speed from the ground up.",
    "url": "",
    "layout": "top",
    "color": "primary",
    "align": { "d": "center" }
  }
}
```
- `layout`: `"top"` (icon above text) or `"left"` (icon beside text)

### Dynamic Elements

#### posts
```json
{
  "id": "e_017", "type": "posts",
  "settings": {
    "count": 3,
    "columns": { "d": 3, "t": 2, "m": 1 },
    "category": "",
    "order": "newest",
    "show_image": true,
    "show_excerpt": true,
    "color": "primary"
  }
}
```
- `order`: `"newest"`, `"oldest"`, `"title"`

#### form
```json
{
  "id": "e_018", "type": "form",
  "settings": { "form_id": 1 }
}
```
- Requires Form Builder plugin; `form_id` references a form

### Advanced Elements

#### html
```json
{
  "id": "e_019", "type": "html",
  "settings": { "html": "<div>Custom HTML</div>" }
}
```

#### shortcode
```json
{
  "id": "e_020", "type": "shortcode",
  "settings": { "shortcode": "[post_cat_shortcode category=\"news\"]" }
}
```

---

## Per-Device Value Format

Settings marked "per-device" use this object format:

```json
{ "d": "desktop_value", "t": "tablet_value", "m": "mobile_value" }
```

- `d` = desktop (always required)
- `t` = tablet (≤1024px) — inherits from `d` if omitted
- `m` = mobile (≤767px) — inherits from `t` → `d` if omitted

### Spacing format (padding/margin)

```json
{
  "d": { "t": 80, "r": 0, "b": 80, "l": 0, "unit": "px" },
  "m": { "t": 40, "r": 0, "b": 40, "l": 0, "unit": "px" }
}
```
- `t`/`r`/`b`/`l` = top/right/bottom/left values
- `unit`: `"px"`, `"rem"`, `"em"`, `"%"`
- Omit sides you don't need (empty = no spacing)

### Typography format

```json
{
  "d": { "size": 40, "weight": "800", "unit": "px", "line": 1.2, "spacing": 0, "transform": "" },
  "m": { "size": 28 }
}
```

---

## Design Tokens

Colors can reference tokens (resolved to CSS variables) instead of hardcoded hex:

| Token | Default | Usage |
|-------|---------|-------|
| `primary` | `#2563eb` | Main brand color — buttons, accents |
| `secondary` | `#0f172a` | Dark backgrounds, headings |
| `accent` | `#f59e0b` | Highlights, CTAs |
| `text` | `#1e293b` | Body text |
| `muted` | `#64748b` | Secondary text |
| `surface` | `#ffffff` | Card/panel backgrounds |
| `alt` | `#f1f5f9` | Alternate section backgrounds |
| `border` | `#e2e8f0` | Borders, dividers |

**Rule**: Use token names for theme-consistent colors, hex for one-off overrides.
- `"color": "primary"` → `var(--jvb-primary)` (theme-aware)
- `"color": "#ff6600"` → literal hex

---

## ID Format

All nodes need unique IDs. Format: `{prefix}_{8 hex chars}`

| Node | Prefix | Example |
|------|--------|---------|
| Section | `s_` | `s_a1b2c3d4` |
| Row | `r_` | `r_e5f6a7b8` |
| Column | `c_` | `c_9d0e1f2a` |
| Element | `e_` | `e_3b4c5d6e` |

---

## Responsive Best Practices

1. **Column widths**: Always set tablet + mobile to 100 for multi-column layouts:
   ```json
   "width": { "d": 33.33, "t": 100, "m": 100 }
   ```

2. **Typography**: Scale down headings on mobile:
   ```json
   "typography": { "d": { "size": 56, "weight": "800" }, "m": { "size": 34 } }
   ```

3. **Padding**: Reduce section padding on mobile:
   ```json
   "padding": { "d": { "t": 96, "b": 96, "unit": "px" }, "m": { "t": 48, "b": 48, "unit": "px" } }
   ```

4. **Row wrap**: Always set `"wrap": "wrap"` on rows with multiple columns so they stack on mobile.

5. **Hide on device**: Use `hide_on` for elements that don't work on certain screens:
   ```json
   "hide_on": ["mobile"]
   ```

---

## Landing Page Structure Template

A typical landing page follows this section order:

```
1. Hero       — headline + subtitle + CTA button (+ optional image/video)
2. Features   — 3-4 iconbox columns explaining key benefits
3. Social proof — testimonials or counter stats
4. Pricing    — pricing cards (2-3 plans)
5. FAQ        — accordion with common questions
6. CTA        — final call-to-action band
7. Footer     — (handled by theme, not builder)
```

---

## Complete Example: SaaS Landing Page

```json
{
  "v": 3,
  "settings": { "custom_css": "" },
  "sections": [
    {
      "id": "s_hero0001",
      "settings": {
        "layout": "full",
        
        "bg_from": "primary",
        "bg_to": "secondary",
        "bg_angle": 135,
        "padding": { "d": { "t": 120, "b": 120, "unit": "px" }, "m": { "t": 64, "b": 64, "unit": "px" } },
        "animation": "fade"
      },
      "rows": [
        {
          "id": "r_hero0001",
          "settings": { "gap": 20, "wrap": "wrap" },
          "cols": [
            {
              "id": "c_hero0001",
              "settings": { "width": { "d": 100 } },
              "elements": [
                {
                  "id": "e_hero_h1", "type": "heading",
                  "settings": {
                    "text": "Build Something Great",
                    "tag": "h1",
                    "color": "#ffffff",
                    "align": { "d": "center" },
                    "typography": { "d": { "size": 56, "weight": "800" }, "m": { "size": 34 } }
                  }
                },
                {
                  "id": "e_hero_sub", "type": "richtext",
                  "settings": {
                    "content": "<p style=\"text-align:center;color:rgba(255,255,255,.85);font-size:20px\">A short, compelling subtitle that explains your value proposition in one breath.</p>"
                  }
                },
                { "id": "e_hero_sp1", "type": "spacer", "settings": { "height": { "d": 16 } } },
                {
                  "id": "e_hero_btn", "type": "button",
                  "settings": {
                    "text": "Get Started Free",
                    "url": "#",
                    "size": "lg",
                    "color": "accent",
                    "align": { "d": "center" }
                  }
                }
              ]
            }
          ]
        }
      ]
    },
    {
      "id": "s_feat0001",
      "settings": {
        "layout": "boxed",
        "padding": { "d": { "t": 96, "b": 96, "unit": "px" }, "m": { "t": 48, "b": 48, "unit": "px" } },
        "animation": "fade-up"
      },
      "rows": [
        {
          "id": "r_feat_head",
          "settings": { "gap": 20 },
          "cols": [
            {
              "id": "c_feat_head",
              "settings": { "width": { "d": 100 } },
              "elements": [
                {
                  "id": "e_feat_h2", "type": "heading",
                  "settings": {
                    "text": "Everything you need",
                    "tag": "h2",
                    "align": { "d": "center" },
                    "typography": { "d": { "size": 40, "weight": "800" } }
                  }
                },
                { "id": "e_feat_sp", "type": "spacer", "settings": { "height": { "d": 24 } } }
              ]
            }
          ]
        },
        {
          "id": "r_feat_cols",
          "settings": { "gap": 24, "wrap": "wrap" },
          "cols": [
            {
              "id": "c_feat_1",
              "settings": { "width": { "d": 33.33, "t": 100, "m": 100 } },
              "elements": [
                {
                  "id": "e_feat_1", "type": "iconbox",
                  "settings": { "icon": "zap", "title": "Fast", "text": "Optimized for speed from the ground up.", "align": { "d": "center" } }
                }
              ]
            },
            {
              "id": "c_feat_2",
              "settings": { "width": { "d": 33.33, "t": 100, "m": 100 } },
              "elements": [
                {
                  "id": "e_feat_2", "type": "iconbox",
                  "settings": { "icon": "shield", "title": "Secure", "text": "Built-in protection for your content.", "align": { "d": "center" } }
                }
              ]
            },
            {
              "id": "c_feat_3",
              "settings": { "width": { "d": 33.34, "t": 100, "m": 100 } },
              "elements": [
                {
                  "id": "e_feat_3", "type": "iconbox",
                  "settings": { "icon": "puzzle", "title": "Extensible", "text": "Hook into anything with the plugin API.", "align": { "d": "center" } }
                }
              ]
            }
          ]
        }
      ]
    },
    {
      "id": "s_cta00001",
      "settings": {
        "layout": "full",
        
        "bg_color": "secondary",
        "padding": { "d": { "t": 72, "b": 72, "unit": "px" } },
        "animation": "fade-up"
      },
      "rows": [
        {
          "id": "r_cta00001",
          "settings": { "gap": 20, "wrap": "wrap" },
          "cols": [
            {
              "id": "c_cta_text",
              "settings": { "width": { "d": 66.66, "t": 100, "m": 100 } },
              "elements": [
                {
                  "id": "e_cta_h2", "type": "heading",
                  "settings": {
                    "text": "Ready to get started?",
                    "tag": "h2",
                    "color": "#ffffff",
                    "typography": { "d": { "size": 34, "weight": "800" } }
                  }
                },
                {
                  "id": "e_cta_sub", "type": "richtext",
                  "settings": {
                    "content": "<p style=\"color:rgba(255,255,255,.75)\">Join thousands of happy users today.</p>"
                  }
                }
              ]
            },
            {
              "id": "c_cta_btn",
              "settings": { "width": { "d": 33.34, "t": 100, "m": 100 } },
              "elements": [
                { "id": "e_cta_sp", "type": "spacer", "settings": { "height": { "d": 28 } } },
                {
                  "id": "e_cta_btn", "type": "button",
                  "settings": {
                    "text": "Sign Up Free",
                    "url": "#",
                    "color": "accent",
                    "align": { "d": "right", "t": "left", "m": "left" }
                  }
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

---

## AI Generation Rules

When generating a layout, follow these rules strictly:

1. **Always output valid JSON** — no comments, no trailing commas, no markdown wrappers
2. **All IDs must be unique** across the entire layout
3. **Always use v3 format** — sections contain `rows`, rows contain `cols`, cols contain `elements`
4. **Every section must have at least 1 row**, every row at least 1 column
5. **Column widths in a row should sum to ~100%** on desktop
6. **Always set tablet/mobile widths to 100** for multi-column rows
7. **Use design tokens** (`primary`, `accent`, `secondary`, etc.) for theme-consistent colors
8. **Use hex** only for one-off color overrides (e.g., white text on dark backgrounds)
9. **Include responsive typography** — scale down heading sizes on mobile (`m` key)
10. **Include responsive padding** — reduce section padding on mobile
11. **Set `wrap: "wrap"`** on rows with multiple columns
12. **Add entrance animations** sparingly — `fade-up` for content sections, `fade` for hero
13. **Content language**: match the user's request language (Indonesian request → Indonesian content)
14. **Keep text concise** — headlines ≤ 8 words, subtitles ≤ 20 words, feature text ≤ 15 words
15. **Use spacers** between major elements for visual breathing room (16-32px)
16. **Hero section**: always `layout: "full"` with gradient or image background
17. **Content sections**: `layout: "boxed"` for contained width
18. **CTA section**: `layout: "full"` with dark background (`secondary`) for contrast
