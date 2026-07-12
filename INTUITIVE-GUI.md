# FEAT: Intuitive GUI — Section > Row > Column > Element

Branch: `feat/intuitivegui`
Base: `main` (v2.1.0)
Goal: Restructure layout hierarchy from Section > Column > Element to Section > Row > Column > Element, inspired by Divi/WordPress Tag Div and MS Word table UX.

## Architecture Change

### Current (v2.1)
```
Section
  └── Column (width %)
        └── Element
```

### Target (v3.0)
```
Section
  └── Row (horizontal group)
        └── Column (width ratio)
              └── Element
```

- Section: full-width container — background, spacing, layout settings
- Row: horizontal flex group — can have multiple per section
- Column: width ratio within a row — flex-grow based
- Element: content — heading, text, image, button, etc.

### Data Model

```json
{
  "sections": [{
    "id": "sec_xxx",
    "type": "section",
    "settings": { "bg_color": "#fff", "padding": {...}, "full_width": false },
    "rows": [{
      "id": "row_xxx",
      "type": "row",
      "settings": { "gap": 20, "align": "stretch" },
      "cols": [{
        "id": "col_xxx",
        "type": "column",
        "settings": { "width": 50 },
        "elements": [{
          "id": "el_xxx",
          "type": "heading",
          "settings": { "text": "Hello" }
        }]
      }]
    }]
  }]
}
```

### Migration (v2 → v3)

Existing layouts have `section.cols[]`. Migration wraps them:
```
section.cols → section.rows = [{ id: newId(), type: 'row', settings: {}, cols: section.cols }]
```
- Runs once per layout on first load in builder
- `settings.jvb_schema_version` tracks migration state (2 → 3)
- Non-destructive: old `cols` key removed only after rows confirmed

## UX Design

### Default Behavior
- "Add Section" creates Section + 1 Row + 1 Column (full hierarchy)
- User sees a blank canvas area ready for element drop
- No need to understand rows/columns until they want more

### + Buttons (contextual, visible on hover/select)

**Section level:**
- `+` at bottom edge of section → adds new Row at bottom
- `+` at top edge of section → adds new Row at top

**Row level:**
- `+` at right edge of row → adds Column to the right
- `+` at left edge of row → adds Column to the left
- `+` between columns → inserts Column at that position
- Drag handle (⠿) → reorder row within section

**Column level:**
- `+` at bottom → adds Element (opens palette drag target)
- Existing drag-drop from palette works as before

### Section Tools (existing floating toolbar, extended)
- Move up/down
- Duplicate (deep copy with new IDs)
- Delete
- Add Row above / Add Row below

### Row Tools (new floating toolbar)
- Move up/down (within section)
- Duplicate row (with all columns and elements)
- Delete row
- Add Column left / Add Column right

### Column Tools (existing, unchanged)
- Width presets (25/33/50/66/75/100)
- Delete column

## Files to Modify

### PHP
- `public/render.php` — `jvb_render_layout()`, `jvb_render_section()`, add `jvb_render_row()`
- `admin/ajax.php` — migration logic in `load` action
- `plugin.php` — schema version constant

### JavaScript
- `assets/frame.js` — BIGGEST changes:
  - Node model: add row type, update all traversal
  - `findNode()` — handle row nesting
  - `renderTree()` — render rows between sections and columns
  - Selection: click row → select row
  - Drag-drop: drop elements into columns (through rows)
  - Row reorder within section
  - Column reorder within row
  - Mutation helpers: `addRow()`, `addCol()`, `deleteRow()`, `dupRow()`
- `assets/builder.js`:
  - Panel: row settings (gap, alignment)
  - `findNode` / `findParent` — updated for row nesting
  - Undo/redo: snapshot includes rows

### CSS
- `assets/frame.css` — row styles, + button positions, row tools toolbar
- `assets/builder.css` — panel row settings fields
- `assets/frontend.css` — row flex layout for public render

## Implementation Order

1. [ ] Data model + migration (ajax.php load action)
2. [ ] Frame render (frame.js renderTree — section > row > col > element)
3. [ ] Frame CSS (row layout, + buttons)
4. [ ] Node traversal (findNode, findParent, getNodeArr)
5. [ ] Row mutation helpers (add/delete/duplicate/reorder)
6. [ ] Section tools extended (add row above/below)
7. [ ] Row tools toolbar (move/dup/delete/add-col)
8. [ ] + buttons on rows (left/right for columns)
9. [ ] Panel row settings (gap, alignment)
10. [ ] Builder JS updates (panel rendering, undo)
11. [ ] Frontend render (public/render.php row output)
12. [ ] Frontend CSS (row flex layout)
13. [ ] E2E tests updated
14. [ ] Migration edge cases (empty sections, missing cols)

## Compatibility

- v2 layouts auto-migrated on builder load (transparent to user)
- Published v2 layouts rendered with migration wrapper in `jvb_render_layout()`
- Frontend render handles BOTH v2 (cols directly) and v3 (rows) during transition
- `schema_version` in settings prevents double-migration
