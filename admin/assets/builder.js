/* ── Jyavani Page Builder JS Engine ── */

const JVB = (() => {
  'use strict';

  let state = { rows: [] };
  let selected = null; // {type:'row'|'col'|'el', index, colIndex, elIndex}
  let currentPost = null; // { id, title, slug, type }
  let nextId = 1;

  function uid() { return 'jvb-' + (nextId++); }

  const elementDefaults = {
    heading:  { text: 'New Heading', tag: 'h2', align: 'left', class: '', text_color: '', margin: '' },
    text:     { content: '<p>Write your text here...</p>', class: '', text_color: '', padding: '', margin: '' },
    image:    { src: 'https://via.placeholder.com/600x400', alt: '', caption: '', link: '', class: '', align: 'left' },
    button:   { text: 'Click Me', url: '#', size: 'md', style: 'solid', new_tab: false, class: '', align: 'left' },
    divider:  { width: '100%', class: '' },
    spacer:   { height: '20px', class: '' },
    video:    { src: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', aspect_ratio: '16/9', class: '' },
    html:     { html: '<div>Your custom HTML here</div>', class: '' },
    shortcode: { shortcode: '[post_cat_shortcode category="news" layout="cards" limit="3"]', class: '' },
    paragraph: { content: '<p>Write your paragraph here.</p>', class: '', margin: '' },
    css:      { code: '/* CSS code here */', class: '' },
    script:   { code: '// JavaScript code here', class: '' },
  };

  function createElement(type) {
    const defaults = elementDefaults[type] || elementDefaults.text;
    return { id: uid(), type, settings: { ...defaults } };
  }

  function createColumn(width) {
    return { id: uid(), width, settings: { class: '', bg_color: '', padding: '8px', text_align: '' }, elements: [] };
  }

  function createRow() {
    return {
      id: uid(),
      settings: { class: '', bg_color: '', text_color: '', padding: '16px', margin: '', full_width: false, bg_image: '' },
      columns: [createColumn(12)],
    };
  }

  // ── Render ──

  function render() {
    const canvas = document.getElementById('jvb-canvas');
    canvas.innerHTML = '';
    if (state.rows.length === 0) {
      canvas.innerHTML = `
        <div class="jvb-canvas-empty">
          <p>No rows yet.</p>
          <p><button class="jvb-btn jvb-btn-primary" onclick="JVB.addRow()">+ Add Row</button> to start building.</p>
        </div>`;
      return;
    }
    state.rows.forEach((row, ri) => {
      const rowEl = document.createElement('div');
      rowEl.className = 'jvb-row-block' + (selected?.type === 'row' && selected.index === ri ? ' jvb-row-selected' : '');
      rowEl.dataset.ri = ri;
      rowEl.innerHTML = `
        <div class="jvb-row-header">
          <span>Row ${ri + 1}${row.settings.full_width ? ' (Full Width)' : ''}</span>
          <div class="jvb-row-actions">
            <button onclick="JVB.selectRow(${ri})" title="Settings">⚙</button>
            <button onclick="JVB.addColumn(${ri})" title="Add Column">+Col</button>
            <button onclick="JVB.duplicateRow(${ri})" title="Duplicate">⧉</button>
            <button onclick="JVB.moveRow(${ri},'up')" title="Move Up">↑</button>
            <button onclick="JVB.moveRow(${ri},'down')" title="Move Down">↓</button>
            <button onclick="JVB.deleteRow(${ri})" title="Delete">✕</button>
          </div>
        </div>
        <div class="jvb-row-body">
          <div class="jvb-columns-grid" data-ri="${ri}">`;
      row.columns.forEach((col, ci) => {
        rowEl.querySelector('.jvb-columns-grid').appendChild(renderColumn(col, ri, ci));
      });
      rowEl.querySelector('.jvb-columns-grid').innerHTML += '</div></div>';
      canvas.appendChild(rowEl);
    });
  }

  function renderColumn(col, ri, ci) {
    const colEl = document.createElement('div');
    colEl.className = 'jvb-col-block' + (selected?.type === 'col' && selected.index === ri && selected.colIndex === ci ? ' jvb-col-selected' : '');
    colEl.style.flex = col.width || 12;
    colEl.dataset.ri = ri;
    colEl.dataset.ci = ci;

    let elsHtml = '';
    if (col.elements.length === 0) {
      elsHtml = '<div class="jvb-col-empty">Drop elements here</div>';
    } else {
      col.elements.forEach((el, ei) => {
        const isSel = selected?.type === 'el' && selected.index === ri && selected.colIndex === ci && selected.elIndex === ei;
        const label = el.type.charAt(0).toUpperCase() + el.type.slice(1);
        elsHtml += `<div class="jvb-element-block${isSel ? ' jvb-el-selected' : ''}" data-ri="${ri}" data-ci="${ci}" data-ei="${ei}" onclick="JVB.selectElement(${ri},${ci},${ei})">
          <div class="jvb-el-label">${label}</div>
          <div class="jvb-el-actions">
            <button onclick="event.stopPropagation();JVB.duplicateElement(${ri},${ci},${ei})" title="Duplicate">⧉</button>
            <button onclick="event.stopPropagation();JVB.deleteElement(${ri},${ci},${ei})" title="Delete">✕</button>
          </div>
        </div>`;
      });
    }

    colEl.innerHTML = `
      <div class="jvb-col-header">
        <span>${col.width || 12}/12</span>
        <div class="jvb-col-actions">
          <button onclick="JVB.selectCol(${ri},${ci})" title="Settings">⚙</button>
          <button onclick="JVB.splitColumn(${ri},${ci})" title="Split">⇄</button>
          <button onclick="JVB.deleteColumn(${ri},${ci})" title="Delete">✕</button>
        </div>
      </div>
      <div class="jvb-col-elements" data-ri="${ri}" data-ci="${ci}">${elsHtml}</div>`;

    return colEl;
  }

  // ── Settings Panel ──

  function openSettings(type, data) {
    const panel = document.getElementById('jvb-settings-body');
    const title = document.getElementById('jvb-settings-title');
    let html = '';

    if (type === 'row') {
      title.textContent = 'Row Settings';
      const s = data.settings;
      html = `
        <div class="jvb-setting-group"><label>Background Color</label>
          <div class="jvb-color-input-wrap"><input type="color" value="${s.bg_color || '#ffffff'}" data-key="bg_color"><input type="text" value="${s.bg_color || ''}" data-key="bg_color" oninput="JVB.updateRowSetting(this.dataset.key,this.value)"></div>
        </div>
        <div class="jvb-setting-group"><label>Text Color</label>
          <div class="jvb-color-input-wrap"><input type="color" value="${s.text_color || '#000000'}" data-key="text_color"><input type="text" value="${s.text_color || ''}" data-key="text_color" oninput="JVB.updateRowSetting(this.dataset.key,this.value)"></div>
        </div>
        <div class="jvb-setting-group"><label>Background Image URL</label><input type="text" value="${s.bg_image || ''}" data-key="bg_image" oninput="JVB.updateRowSetting(this.dataset.key,this.value)"></div>
        <div class="jvb-setting-group"><label>Padding</label><input type="text" value="${s.padding || ''}" data-key="padding" oninput="JVB.updateRowSetting(this.dataset.key,this.value)"></div>
        <div class="jvb-setting-group"><label>Margin</label><input type="text" value="${s.margin || ''}" data-key="margin" oninput="JVB.updateRowSetting(this.dataset.key,this.value)"></div>
        <div class="jvb-setting-group"><label>CSS Class</label><input type="text" value="${s.class || ''}" data-key="class" oninput="JVB.updateRowSetting(this.dataset.key,this.value)"></div>
        <div class="jvb-setting-group"><label><input type="checkbox" ${s.full_width ? 'checked' : ''} onchange="JVB.updateRowSetting('full_width',this.checked)"> Full Width</label></div>`;
    } else if (type === 'col') {
      title.textContent = 'Column Settings';
      const s = data.settings;
      html = `
        <div class="jvb-setting-group"><label>Column Width (1-12)</label><input type="number" min="1" max="12" value="${data.width || 12}" onchange="JVB.updateColSetting('width',parseInt(this.value)||12)"></div>
        <div class="jvb-setting-group"><label>Background Color</label>
          <div class="jvb-color-input-wrap"><input type="color" value="${s.bg_color || '#ffffff'}" data-key="bg_color"><input type="text" value="${s.bg_color || ''}" data-key="bg_color" oninput="JVB.updateColSetting(this.dataset.key,this.value)"></div>
        </div>
        <div class="jvb-setting-group"><label>Padding</label><input type="text" value="${s.padding || ''}" data-key="padding" oninput="JVB.updateColSetting(this.dataset.key,this.value)"></div>
        <div class="jvb-setting-group"><label>Text Align</label>
          <select data-key="text_align" onchange="JVB.updateColSetting(this.dataset.key,this.value)">
            <option value="" ${s.text_align === '' ? 'selected' : ''}>Default</option>
            <option value="left" ${s.text_align === 'left' ? 'selected' : ''}>Left</option>
            <option value="center" ${s.text_align === 'center' ? 'selected' : ''}>Center</option>
            <option value="right" ${s.text_align === 'right' ? 'selected' : ''}>Right</option>
          </select>
        </div>
        <div class="jvb-setting-group"><label>CSS Class</label><input type="text" value="${s.class || ''}" data-key="class" oninput="JVB.updateColSetting(this.dataset.key,this.value)"></div>`;
    } else if (type === 'el') {
      const s = data.settings;
      const t = data.type;
      title.textContent = t.charAt(0).toUpperCase() + t.slice(1) + ' Settings';

      html = `<div class="jvb-setting-group"><label>CSS Class</label><input type="text" value="${s.class || ''}" data-key="class" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>`;

      if (t === 'heading') {
        html = `
          <div class="jvb-setting-group"><label>Text</label><input type="text" value="${s.text || ''}" data-key="text" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          <div class="jvb-setting-group"><label>Tag</label>
            <select data-key="tag" onchange="JVB.updateElSetting(this.dataset.key,this.value)">
              ${['h1','h2','h3','h4','h5','h6'].map(tag => `<option value="${tag}" ${s.tag === tag ? 'selected' : ''}>${tag}</option>`).join('')}
            </select>
          </div>
          <div class="jvb-setting-group"><label>Align</label>
            <select data-key="align" onchange="JVB.updateElSetting(this.dataset.key,this.value)">
              <option value="left" ${s.align === 'left' ? 'selected' : ''}>Left</option>
              <option value="center" ${s.align === 'center' ? 'selected' : ''}>Center</option>
              <option value="right" ${s.align === 'right' ? 'selected' : ''}>Right</option>
            </select>
          </div>
          <div class="jvb-setting-group"><label>Text Color</label>
            <div class="jvb-color-input-wrap"><input type="color" value="${s.text_color || '#000000'}" data-key="text_color"><input type="text" value="${s.text_color || ''}" data-key="text_color" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          </div>
          <div class="jvb-setting-group"><label>Margin</label><input type="text" value="${s.margin || ''}" data-key="margin" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>` + html;
      } else if (t === 'text') {
        html = `
          <div class="jvb-setting-group"><label>Content (HTML)</label><textarea data-key="content" oninput="JVB.updateElSetting(this.dataset.key,this.value)">${s.content || ''}</textarea></div>
          <div class="jvb-setting-group"><label>Text Color</label>
            <div class="jvb-color-input-wrap"><input type="color" value="${s.text_color || '#000000'}" data-key="text_color"><input type="text" value="${s.text_color || ''}" data-key="text_color" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          </div>
          <div class="jvb-setting-group"><label>Padding</label><input type="text" value="${s.padding || ''}" data-key="padding" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          <div class="jvb-setting-group"><label>Margin</label><input type="text" value="${s.margin || ''}" data-key="margin" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>` + html;
      } else if (t === 'image') {
        html = `
          <div class="jvb-setting-group"><label>Image URL</label><input type="text" value="${s.src || ''}" data-key="src" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          <div class="jvb-setting-group"><label>Alt Text</label><input type="text" value="${s.alt || ''}" data-key="alt" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          <div class="jvb-setting-group"><label>Caption</label><input type="text" value="${s.caption || ''}" data-key="caption" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          <div class="jvb-setting-group"><label>Link URL</label><input type="text" value="${s.link || ''}" data-key="link" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          <div class="jvb-setting-group"><label>Align</label>
            <select data-key="align" onchange="JVB.updateElSetting(this.dataset.key,this.value)">
              <option value="left" ${s.align === 'left' ? 'selected' : ''}>Left</option>
              <option value="center" ${s.align === 'center' ? 'selected' : ''}>Center</option>
              <option value="right" ${s.align === 'right' ? 'selected' : ''}>Right</option>
            </select>
          </div>` + html;
      } else if (t === 'button') {
        html = `
          <div class="jvb-setting-group"><label>Button Text</label><input type="text" value="${s.text || ''}" data-key="text" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          <div class="jvb-setting-group"><label>URL</label><input type="text" value="${s.url || ''}" data-key="url" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          <div class="jvb-setting-group"><label>Size</label>
            <select data-key="size" onchange="JVB.updateElSetting(this.dataset.key,this.value)">
              <option value="sm" ${s.size === 'sm' ? 'selected' : ''}>Small</option>
              <option value="md" ${s.size === 'md' ? 'selected' : ''}>Medium</option>
              <option value="lg" ${s.size === 'lg' ? 'selected' : ''}>Large</option>
            </select>
          </div>
          <div class="jvb-setting-group"><label>Style</label>
            <select data-key="style" onchange="JVB.updateElSetting(this.dataset.key,this.value)">
              <option value="solid" ${s.style === 'solid' ? 'selected' : ''}>Solid</option>
              <option value="outline" ${s.style === 'outline' ? 'selected' : ''}>Outline</option>
              <option value="ghost" ${s.style === 'ghost' ? 'selected' : ''}>Ghost</option>
            </select>
          </div>
          <div class="jvb-setting-group"><label>Align</label>
            <select data-key="align" onchange="JVB.updateElSetting(this.dataset.key,this.value)">
              <option value="left" ${s.align === 'left' ? 'selected' : ''}>Left</option>
              <option value="center" ${s.align === 'center' ? 'selected' : ''}>Center</option>
              <option value="right" ${s.align === 'right' ? 'selected' : ''}>Right</option>
            </select>
          </div>
          <div class="jvb-setting-group"><label><input type="checkbox" ${s.new_tab ? 'checked' : ''} onchange="JVB.updateElSetting('new_tab',this.checked)"> Open in new tab</label></div>` + html;
      } else if (t === 'divider') {
        html = `
          <div class="jvb-setting-group"><label>Width (e.g. 80%, 400px)</label><input type="text" value="${s.width || '100%'}" data-key="width" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>` + html;
      } else if (t === 'spacer') {
        html = `
          <div class="jvb-setting-group"><label>Height (e.g. 40px)</label><input type="text" value="${s.height || '20px'}" data-key="height" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>` + html;
      } else if (t === 'video') {
        html = `
          <div class="jvb-setting-group"><label>Video URL (YouTube/Vimeo)</label><input type="text" value="${s.src || ''}" data-key="src" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>
          <div class="jvb-setting-group"><label>Aspect Ratio (e.g. 16/9)</label><input type="text" value="${s.aspect_ratio || '16/9'}" data-key="aspect_ratio" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>` + html;
      } else if (t === 'paragraph') {
        html = `
          <div class="jvb-setting-group">
            <label>Content</label>
            <textarea data-key="content" oninput="JVB.updateElSetting(this.dataset.key,this.value)" style="min-height:100px">${s.content || ''}</textarea>
          </div>
          <div class="jvb-setting-group">
            <button class="jvb-btn jvb-btn-primary jvb-btn-block" onclick="JVB.openQuillEditor('content', (JVB._qSel=JVB._qSel||getSelectedTarget()).target.settings.content, function(v){ var t=JVB._qSel||getSelectedTarget(); t.target.settings.content=v; JVB.updateElSetting('content',v); JVB._qSel=null; })">✎ Edit with Quill</button>
          </div>
          <div class="jvb-setting-group"><label>Margin</label><input type="text" value="${s.margin || ''}" data-key="margin" oninput="JVB.updateElSetting(this.dataset.key,this.value)"></div>` + html;
      } else if (t === 'css') {
        html = `
          <div class="jvb-setting-group">
            <label>CSS Code</label>
            <textarea data-key="code" oninput="JVB.updateElSetting(this.dataset.key,this.value)" style="min-height:120px;font-family:monospace">${s.code || ''}</textarea>
          </div>
          <div class="jvb-setting-group">
            <button class="jvb-btn jvb-btn-primary jvb-btn-block" onclick="JVB.openCodeMirrorEditor('code', (JVB._cmSel=JVB._cmSel||getSelectedTarget()).target.settings.code, 'css', function(v){ var t=JVB._cmSel||getSelectedTarget(); t.target.settings.code=v; JVB.updateElSetting('code',v); JVB._cmSel=null; })">✎ Edit with CodeMirror</button>
          </div>` + html;
      } else if (t === 'script') {
        html = `
          <div class="jvb-setting-group">
            <label>JavaScript Code</label>
            <textarea data-key="code" oninput="JVB.updateElSetting(this.dataset.key,this.value)" style="min-height:120px;font-family:monospace">${s.code || ''}</textarea>
          </div>
          <div class="jvb-setting-group">
            <button class="jvb-btn jvb-btn-primary jvb-btn-block" onclick="JVB.openCodeMirrorEditor('code', (JVB._cmSel=JVB._cmSel||getSelectedTarget()).target.settings.code, 'javascript', function(v){ var t=JVB._cmSel||getSelectedTarget(); t.target.settings.code=v; JVB.updateElSetting('code',v); JVB._cmSel=null; })">✎ Edit with CodeMirror</button>
          </div>` + html;
      } else if (t === 'html') {
        html = `
          <div class="jvb-setting-group">
            <label>HTML</label>
            <textarea data-key="html" oninput="JVB.updateElSetting(this.dataset.key,this.value)" style="min-height:150px;font-family:monospace">${s.html || ''}</textarea>
          </div>
          <div class="jvb-setting-group">
            <button class="jvb-btn jvb-btn-primary jvb-btn-block" onclick="JVB.openCodeMirrorEditor('html', (JVB._cmSel=JVB._cmSel||getSelectedTarget()).target.settings.html, 'htmlmixed', function(v){ var t=JVB._cmSel||getSelectedTarget(); t.target.settings.html=v; JVB.updateElSetting('html',v); JVB._cmSel=null; })">✎ Edit with CodeMirror</button>
          </div>` + html;
      } else if (t === 'shortcode') {
        html = `
          <div class="jvb-setting-group"><label>Shortcode</label><textarea data-key="shortcode" oninput="JVB.updateElSetting(this.dataset.key,this.value)" style="min-height:80px;font-family:monospace">${s.shortcode || ''}</textarea></div>` + html;
      }
    }

    panel.innerHTML = html;
  }

  // ── Mutations ──

  function getSelectedTarget() {
    if (!selected) return null;
    const { type, index, colIndex, elIndex } = selected;
    if (type === 'row') return { target: state.rows[index], path: ['rows', index] };
    if (type === 'col') return { target: state.rows[index].columns[colIndex], path: ['rows', index, 'columns', colIndex] };
    if (type === 'el') return { target: state.rows[index].columns[colIndex].elements[elIndex], path: ['rows', index, 'columns', colIndex, 'elements', elIndex] };
    return null;
  }

  function countElements(rows) {
    let n = 0;
    for (const r of rows) for (const c of r.columns) n += c.elements.length;
    return n;
  }

  // ── HTML Parser (import existing content) ──

  function parseHtmlToLayout(html) {
    if (!html || !html.trim()) return null;
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const nodes = Array.from(doc.body.childNodes);
    const elements = [];
    let complexBuffer = '';

    function flushComplex() {
      if (!complexBuffer.trim()) return;
      elements.push({ type: 'html', settings: { html: complexBuffer.trim(), class: '' } });
      complexBuffer = '';
    }

    for (const node of nodes) {
      if (node.nodeType === 3) {
        const text = node.textContent.trim();
        if (text) { complexBuffer += text; }
        continue;
      }
      if (node.nodeType !== 1) { complexBuffer += node.outerHTML || ''; continue; }

      const tag = node.tagName.toLowerCase();

      if (tag === 'p') {
        flushComplex();
        elements.push({ type: 'paragraph', settings: { content: node.innerHTML, class: '', margin: '' } });
      } else if (['h1','h2','h3','h4','h5','h6'].includes(tag)) {
        flushComplex();
        elements.push({ type: 'heading', settings: { text: node.textContent, tag, align: 'left', class: '', text_color: '', margin: '' } });
      } else if (tag === 'img') {
        flushComplex();
        elements.push({ type: 'image', settings: { src: node.getAttribute('src') || '', alt: node.getAttribute('alt') || '', caption: '', link: '', class: '', align: 'left' } });
      } else if (tag === 'hr') {
        flushComplex();
        elements.push({ type: 'divider', settings: { width: '100%', class: '' } });
      } else if (tag === 'iframe') {
        flushComplex();
        elements.push({ type: 'video', settings: { src: node.getAttribute('src') || '', aspect_ratio: '16/9', class: '' } });
      } else if (['script','style'].includes(tag)) {
        const typeAttr = tag === 'script' ? (node.getAttribute('type') || 'text/javascript') : '';
        const code = node.textContent;
        if (tag === 'style') {
          elements.push({ type: 'css', settings: { code, class: '' } });
        } else {
          elements.push({ type: 'script', settings: { code, class: '' } });
        }
      } else {
        complexBuffer += node.outerHTML || '';
      }
    }
    flushComplex();

    if (elements.length === 0) return null;

    return {
      rows: [{
        id: uid(),
        settings: { class: '', bg_color: '', text_color: '', padding: '16px', margin: '', full_width: false, bg_image: '' },
        columns: [{
          id: uid(), width: 12,
          settings: { class: '', bg_color: '', padding: '8px', text_align: '' },
          elements,
        }]
      }]
    };
  }

  // ── Overlay Editors (Quill / CodeMirror) ──

  let _overlayQuill = null;
  let _overlayCM = null;
  let _overlayTarget = null; // { key, onSave }

  function closeOverlay() {
    const el = document.getElementById('jvb-editor-overlay');
    if (el) el.remove();
    if (_overlayQuill) { try { _overlayQuill = null; } catch(e) {} }
    _overlayCM = null;
    _overlayTarget = null;
  }

  function buildOverlay(title) {
    closeOverlay();
    const div = document.createElement('div');
    div.id = 'jvb-editor-overlay';
    div.className = 'jvb-editor-overlay';
    div.innerHTML = `<div class="jvb-editor-overlay-inner">
      <div class="jvb-editor-overlay-header">
        <span class="jvb-editor-overlay-title">${title}</span>
        <div class="jvb-editor-overlay-actions">
          <button class="jvb-btn" onclick="JVB._closeEditorOverlay()">Cancel</button>
          <button class="jvb-btn jvb-btn-primary" onclick="JVB._saveEditorOverlay()">Save</button>
        </div>
      </div>
      <div class="jvb-editor-overlay-body" id="jvb-editor-overlay-body"></div>
    </div>`;
    document.body.appendChild(div);
    return div;
  }

  // ── Public API ──

  return {
    init() {
      const sel = document.getElementById('jvb-post-select');
      const posts = window.JVB_POSTS || [];
      sel.innerHTML = '<option value="">— Select a post/page —</option>';
      posts.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = `[${p.type}] ${p.title} (${p.slug})`;
        sel.appendChild(opt);
      });
      sel.addEventListener('change', () => this.loadPost(sel.value));

      // Drag from palette
      document.querySelectorAll('.jvb-palette-item').forEach(item => {
        item.addEventListener('dragstart', e => {
          e.dataTransfer.setData('text/plain', item.dataset.type);
          e.dataTransfer.effectAllowed = 'copy';
        });
      });

      // Canvas drop handling
      const canvas = document.getElementById('jvb-canvas');
      canvas.addEventListener('dragover', e => e.preventDefault());
      canvas.addEventListener('drop', e => {
        e.preventDefault();
        const type = e.dataTransfer.getData('text/plain');
        if (!type) return;
        const colEl = e.target.closest('.jvb-col-elements');
        if (!colEl) return;
        const ri = parseInt(colEl.dataset.ri);
        const ci = parseInt(colEl.dataset.ci);
        const row = state.rows[ri];
        if (!row) return;
        const col = row.columns[ci];
        if (!col) return;
        const el = createElement(type);
        col.elements.push(el);
        this.closeSettings();
        render();
      });
    },

    addRow() {
      state.rows.push(createRow());
      this.closeSettings();
      render();
    },

    addColumn(ri) {
      const row = state.rows[ri];
      if (!row || row.columns.length >= 12) return;
      const newWidth = Math.max(1, Math.floor(12 / (row.columns.length + 1)));
      row.columns.forEach(c => { c.width = newWidth; });
      row.columns.push(createColumn(newWidth));
      this.closeSettings();
      render();
    },

    splitColumn(ri, ci) {
      const row = state.rows[ri];
      if (!row || row.columns.length >= 12) return;
      const col = row.columns[ci];
      const half = Math.max(1, Math.floor(col.width / 2));
      col.width = half;
      row.columns.splice(ci + 1, 0, createColumn(half));
      this.closeSettings();
      render();
    },

    deleteRow(ri) {
      if (!confirm('Delete this row?')) return;
      state.rows.splice(ri, 1);
      this.closeSettings();
      render();
    },

    deleteColumn(ri, ci) {
      const row = state.rows[ri];
      if (!row || row.columns.length <= 1) return;
      row.columns.splice(ci, 1);
      const newWidth = Math.max(1, Math.floor(12 / row.columns.length));
      row.columns.forEach(c => { c.width = newWidth; });
      this.closeSettings();
      render();
    },

    deleteElement(ri, ci, ei) {
      const row = state.rows[ri];
      if (!row) return;
      const col = row.columns[ci];
      if (!col) return;
      col.elements.splice(ei, 1);
      this.closeSettings();
      render();
    },

    duplicateRow(ri) {
      const row = state.rows[ri];
      if (!row) return;
      const copy = JSON.parse(JSON.stringify(row));
      copy.id = uid();
      copy.columns.forEach(c => { c.id = uid(); c.elements.forEach(e => e.id = uid()); });
      state.rows.splice(ri + 1, 0, copy);
      render();
    },

    duplicateElement(ri, ci, ei) {
      const row = state.rows[ri];
      if (!row) return;
      const col = row.columns[ci];
      if (!col) return;
      const el = col.elements[ei];
      if (!el) return;
      const copy = JSON.parse(JSON.stringify(el));
      copy.id = uid();
      col.elements.splice(ei + 1, 0, copy);
      render();
    },

    moveRow(ri, dir) {
      if (dir === 'up' && ri > 0) { [state.rows[ri - 1], state.rows[ri]] = [state.rows[ri], state.rows[ri - 1]]; }
      if (dir === 'down' && ri < state.rows.length - 1) { [state.rows[ri], state.rows[ri + 1]] = [state.rows[ri + 1], state.rows[ri]]; }
      render();
    },

    selectRow(ri) {
      selected = { type: 'row', index: ri };
      render();
      openSettings('row', state.rows[ri]);
    },

    selectCol(ri, ci) {
      selected = { type: 'col', index: ri, colIndex: ci };
      render();
      openSettings('col', state.rows[ri].columns[ci]);
    },

    selectElement(ri, ci, ei) {
      selected = { type: 'el', index: ri, colIndex: ci, elIndex: ei };
      render();
      openSettings('el', state.rows[ri].columns[ci].elements[ei]);
    },

    closeSettings() {
      selected = null;
      document.getElementById('jvb-settings-body').innerHTML = '<p class="jvb-settings-hint">Click any element, column, or row to edit.</p>';
      document.getElementById('jvb-settings-title').textContent = 'Settings';
      render();
    },

    updateRowSetting(key, value) {
      const t = getSelectedTarget();
      if (!t || !selected || selected.type !== 'row') return;
      if (key === 'full_width') { t.target.settings.full_width = !!value; }
      else { t.target.settings[key] = value; }
    },

    updateColSetting(key, value) {
      const t = getSelectedTarget();
      if (!t || !selected || selected.type !== 'col') return;
      if (key === 'width') { t.target.width = value; }
      else { t.target.settings[key] = value; }
      render();
    },

    updateElSetting(key, value) {
      const t = getSelectedTarget();
      if (!t || !selected || selected.type !== 'el') return;
      t.target.settings[key] = value;
    },

    clearLayout() {
      if (!confirm('Clear entire layout? This cannot be undone.')) return;
      state.rows = [];
      this.closeSettings();
      render();
    },

    // ── Import Existing Content ──

    importContent() {
      const postId = document.getElementById('jvb-post-select').value;
      if (!postId) { alert('Select a post/page first.'); return; }
      const adminPath = window.JVB_ADMIN_PATH || '';
      fetch(window.location.pathname + '?page=admin/tools/jyavani-builder&action=jyavani_builder_import&post_id=' + postId)
        .then(r => r.json())
        .then(res => {
          if (!res.success) { alert(res.message); return; }
          if (!res.content || !res.content.trim()) { alert('This post has no content to import.'); return; }
          const layout = parseHtmlToLayout(res.content);
          if (!layout) { alert('Could not parse content into layout.'); return; }
          if (state.rows.length > 0) {
            if (!confirm('This will replace your current layout. Continue?')) return;
          }
          state.rows = layout.rows;
          this.closeSettings();
          render();
          alert('Content imported! ' + countElements(state.rows) + ' elements created.');
        })
        .catch(err => { alert('Failed to import: ' + err.message); });
    },

    // ── Overlay Editors ──

    _closeEditorOverlay() { closeOverlay(); },

    _saveEditorOverlay() {
      if (!_overlayTarget) return;
      let val = '';
      if (_overlayQuill) {
        val = _overlayQuill.root.innerHTML;
      } else if (_overlayCM) {
        val = _overlayCM.getValue();
      }
      _overlayTarget.onSave(val);
      closeOverlay();
    },

    openQuillEditor(key, initialContent, onSave) {
      buildOverlay('Edit Content — Quill Editor');
      const body = document.getElementById('jvb-editor-overlay-body');
      const editorDiv = document.createElement('div');
      editorDiv.id = 'jvb-quill-editor';
      body.appendChild(editorDiv);

      _overlayQuill = new Quill('#jvb-quill-editor', {
        modules: {
          toolbar: [
            [{ header: [1,2,3,4,5,6,false] }],
            ['bold','italic','underline','strike'],
            [{ color: [] }, { background: [] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            [{ indent: '-1' }, { indent: '+1' }],
            [{ align: [] }],
            ['blockquote','code-block'],
            ['link','image'],
            [{ size: ['small', false, 'large', 'huge'] }],
            ['clean']
          ]
        },
        theme: 'snow',
        placeholder: 'Write content here...'
      });
      _overlayQuill.root.innerHTML = initialContent || '';
      _overlayTarget = { key, onSave };
    },

    openCodeMirrorEditor(key, initialContent, mode, onSave) {
      buildOverlay('Edit Code — ' + mode.toUpperCase());
      const body = document.getElementById('jvb-editor-overlay-body');
      const ta = document.createElement('textarea');
      ta.id = 'jvb-cm-textarea';
      body.appendChild(ta);

      // Init after DOM attach
      requestAnimationFrame(() => {
        _overlayCM = CodeMirror.fromTextArea(ta, {
          mode: mode || 'htmlmixed',
          lineNumbers: true,
          styleActiveLine: true,
          matchBrackets: true,
          autoCloseBrackets: true,
          indentUnit: 2,
          lineWrapping: true,
          viewportMargin: Infinity,
          theme: 'dracula',
        });
        _overlayCM.setSize('100%', '100%');
        _overlayCM.setValue(initialContent || '');
        _overlayCM.refresh();
      });
      _overlayTarget = { key, onSave: onSave || (() => {}) };
    },

    // ── Load/Save ──

    loadPost(postId) {
      if (!postId) { state.rows = []; currentPost = null; this.closeSettings(); render(); return; }
      const adminPath = window.JVB_ADMIN_PATH || '';
      fetch(`${window.location.pathname}?page=admin/tools/jyavani-builder&action=jyavani_builder_load&post_id=${postId}`)
        .then(r => r.json())
        .then(res => {
          if (!res.success) { alert(res.message); return; }
          currentPost = res.post || null;
          state.rows = res.data?.rows || [];
          document.getElementById('jvb-post-title').textContent = 'Editing: ' + (res.post?.title || '');
          this.closeSettings();
          render();
        })
        .catch(err => { alert('Failed to load: ' + err.message); });
    },

    save() {
      const sel = document.getElementById('jvb-post-select');
      const postId = sel.value;
      if (!postId) { alert('Select a post/page first.'); return; }

      const data = { rows: state.rows };
      const btn = document.getElementById('jvb-btn-save');
      btn.disabled = true;
      btn.textContent = 'Saving...';

      const formData = new FormData();
      formData.append('action', 'jyavani_builder_save');
      formData.append('post_id', postId);
      formData.append('builder_data', JSON.stringify(data));

      fetch(window.location.pathname + '?page=admin/tools/jyavani-builder', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
          btn.disabled = false;
          btn.textContent = 'Save Layout';
          if (res.success) { alert('Layout saved!'); }
          else { alert('Error: ' + res.message); }
        })
        .catch(err => {
          btn.disabled = false;
          btn.textContent = 'Save Layout';
          alert('Failed to save: ' + err.message);
        });
    },

    preview() {
      if (!currentPost) { alert('Load a post/page first.'); return; }
      const baseUrl = window.location.origin;
      const slug = currentPost.slug;
      if (!slug) { alert('No slug available for preview.'); return; }
      window.open(baseUrl + '/' + encodeURIComponent(slug) + '/', '_blank');
    },
  };
})();
