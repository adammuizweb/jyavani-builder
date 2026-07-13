# Jy Builder

Visual drag-and-drop builder for [Jyavani CMS](https://github.com/jyavani/jyavani). Works with articles, pages, and themes.

![Version](https://img.shields.io/badge/version-3.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-purple)
![License](https://img.shields.io/badge/license-MIT-green)

## Features

- **WYSIWYG iframe canvas** with inline text editing
- **Section > Row > Column > Element** hierarchy (Divi-style)
- **20 element types**: heading, richtext, image, button, icon, spacer, divider, video, accordion, tabs, counter, countdown, gallery, testimonial, pricing, iconbox, posts grid, form, HTML, shortcode
- **Drag-and-drop** palette with pointer-based cross-iframe DnD
- **Responsive controls** — per-device settings for desktop, tablet, mobile
- **Design tokens** — 8 color tokens, typography scale, spacing system
- **Draft/publish workflow** — edits never touch live until published
- **Revisions** — automatic snapshots on each publish (cap 20)
- **Template library** — save sections as reusable templates
- **Entrance animations** — fade, slide, zoom, flip
- **Shape dividers** — wave, tilt, curve, triangle, zigzag SVG
- **Section backgrounds** — color, gradient, image with overlay
- **Edge-to-edge layouts** — stretch sections with zero padding
- **HTML import** — auto-converts existing post content to builder layout
- **AI generation** — paste `ai.md` into any AI chat to generate layouts
- **Role-based access** — author/editor own posts only, admin all
- **Mobile-friendly builder** — slide-over panels, tap-to-insert
- **Zero dependencies** — no build tools, no npm, pure PHP + vanilla JS

## Installation

1. Download the latest release zip
2. Upload via **Dashboard → Plugins → Upload Plugin**
3. Activate — the "Jy Builder" menu appears under Pages

Or manually:

```bash
cd /path/to/jyavani/plugins/
unzip jyavani-builder.zip
```

## Usage

### Creating a page

1. Go to **Jy Builder** in the sidebar
2. Click **New** or select an existing post
3. Drag sections and elements from the left panel
4. Edit text inline, adjust settings in the right panel
5. Click **Publish** when ready

### AI-assisted landing pages

1. Copy the contents of [`ai.md`](ai.md)
2. Paste into any AI chat (ChatGPT, Claude, Gemini)
3. Describe your landing page: *"Create a landing page for a SaaS product"*
4. Import the generated JSON into the builder
5. Fine-tune visually

### Element types

| Group | Elements |
|-------|----------|
| Basic | Heading, Rich Text, Image, Button, Icon, Spacer, Divider, Video |
| Content | Accordion, Tabs, Counter, Countdown, Gallery, Testimonial, Pricing, Icon Box |
| Dynamic | Posts Grid, Form |
| Advanced | HTML, Shortcode |

### Section layouts

| Layout | Behavior |
|--------|----------|
| **Boxed** | Content constrained to theme container (1200px default) |
| **Full** | Background bleeds to viewport, content constrained |
| **Stretch** | Everything edge-to-edge |

Set Content Padding to 0 for true edge-to-edge sections.

### Keyboard shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+Z` | Undo |
| `Ctrl+Y` | Redo |
| `Ctrl+B` | Toggle elements panel |

## Architecture

```
Section (full-width horizontal band)
  └─ Row (horizontal flex container)
       └─ Column (vertical container, width %)
            └─ Element (content block)
```

- **Storage**: `jvb_layouts` table (draft/published JSON per post)
- **Revisions**: `jvb_revisions` table (snapshots on publish)
- **Templates**: `jvb_templates` table (section/page library)
- **Design tokens**: stored in CMS settings (`jvb_design_tokens`)
- **Frontend**: `post_content` filter renders published layout
- **Fallback**: rendered HTML synced to `posts.content` on publish

## Requirements

- Jyavani CMS ≥ 2.1.3
- PHP ≥ 8.1
- PDO, JSON extensions

## Author

[Adam Muiz](https://jyavani.com/member/adammuiz/)

## License

MIT
