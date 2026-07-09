<link rel="stylesheet" href="/static/vendor/jyavani-builder/builder.css">
<div class="jvb-admin-wrap">
  <div class="jvb-toolbar">
    <div class="jvb-toolbar-left">
      <h2>Page Builder</h2>
      <select id="jvb-post-select" class="jvb-select">
        <option value="">— Select a page/page to edit —</option>
      </select>
      <span id="jvb-post-title" class="jvb-post-title"></span>
    </div>
    <div class="jvb-toolbar-right">
      <button id="jvb-btn-save" class="jvb-btn jvb-btn-primary" onclick="JVB.save()">Save Layout</button>
      <button id="jvb-btn-preview" class="jvb-btn" onclick="JVB.preview()">Preview</button>
    </div>
  </div>

  <div class="jvb-editor-layout">
    <div class="jvb-palette">
      <h3>Elements</h3>
      <div class="jvb-palette-items">
        <div class="jvb-palette-item" data-type="heading" draggable="true">
          <span class="jvb-palette-icon">H</span>
          <span>Heading</span>
        </div>
        <div class="jvb-palette-item" data-type="text" draggable="true">
          <span class="jvb-palette-icon">T</span>
          <span>Text</span>
        </div>
        <div class="jvb-palette-item" data-type="image" draggable="true">
          <span class="jvb-palette-icon">🖼</span>
          <span>Image</span>
        </div>
        <div class="jvb-palette-item" data-type="button" draggable="true">
          <span class="jvb-palette-icon">🔘</span>
          <span>Button</span>
        </div>
        <div class="jvb-palette-item" data-type="divider" draggable="true">
          <span class="jvb-palette-icon">—</span>
          <span>Divider</span>
        </div>
        <div class="jvb-palette-item" data-type="spacer" draggable="true">
          <span class="jvb-palette-icon">⤢</span>
          <span>Spacer</span>
        </div>
        <div class="jvb-palette-item" data-type="video" draggable="true">
          <span class="jvb-palette-icon">▶</span>
          <span>Video</span>
        </div>
        <div class="jvb-palette-item" data-type="html" draggable="true">
          <span class="jvb-palette-icon">&lt;/&gt;</span>
          <span>HTML</span>
        </div>
        <div class="jvb-palette-item" data-type="shortcode" draggable="true">
          <span class="jvb-palette-icon">[ ]</span>
          <span>Shortcode</span>
        </div>
      </div>

      <hr>

      <h3>Structure</h3>
      <button class="jvb-btn jvb-btn-block" onclick="JVB.addRow()">+ Add Row</button>

      <hr>

      <div class="jvb-export-section">
        <button class="jvb-btn jvb-btn-block" onclick="JVB.clearLayout()" style="background:#e74c3c;color:#fff;border:0;">Clear Layout</button>
      </div>
    </div>

    <div class="jvb-canvas" id="jvb-canvas">
      <div class="jvb-canvas-empty">
        <p>Select a post on the top bar, then start building.</p>
        <p>Drag elements from the palette into columns, or click "+ Add Row" to begin.</p>
      </div>
    </div>

    <div class="jvb-settings-panel" id="jvb-settings-panel">
      <div class="jvb-settings-header">
        <h3 id="jvb-settings-title">Settings</h3>
        <button class="jvb-settings-close" onclick="JVB.closeSettings()">✕</button>
      </div>
      <div class="jvb-settings-body" id="jvb-settings-body">
        <p class="jvb-settings-hint">Click on any element, column, or row to edit its settings.</p>
      </div>
    </div>
  </div>
</div>

<script src="/static/vendor/jyavani-builder/builder.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  JVB.init();
});
</script>
