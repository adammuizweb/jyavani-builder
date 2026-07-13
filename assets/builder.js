/* Jy Builder — builder.js (v3.0 engine)
 * Full-screen builder app: state tree, history, iframe WYSIWYG, generic settings panel. */
(function () {
  'use strict';

  var boot = window.JVB_BOOT || {};

  // ───────────────────────── State ─────────────────────────
  var S = {
    postId: boot.postId || 0,
    post: boot.post || {},
    layout: { v: 2, settings: { custom_css: '' }, sections: [] },
    status: 'none',
    registry: {},     // type → definition
    icons: [], iconSvgs: {}, tokens: {}, forms: [], categories: [], role: 'editor',
    selected: null,   // { kind: 'section'|'col'|'element', id }
    device: 'desktop',
    dirty: false, saving: false, lastSaved: '',
    undo: [], redo: [],
    lastChangeKey: '', lastChangeAt: 0,
    pendingInsertAfter: null, // section id for next section insert
    uiIcons: {},              // inline SVG strings for JS chrome (from elements API)
    frameReady: false,
    panelTab: 'content',
  };

  // ───────────────────────── Utils ─────────────────────────
  function uid(p) { return (p || 'n') + '_' + Math.random().toString(16).slice(2, 10); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]; }); }

  function toast(msg, type) {
    // type: 'success'|'warning'|'info'|'error' (default 'info')
    // backward compat: toast(msg, true) → error
    if (type === true) type = 'error';
    if (!type) type = 'info';
    if (window.NewNotifToast) {
      window.NewNotifToast.show({ message: msg, type: type, duration: 2600 });
      return;
    }
    var old = $('.jvb-toast'); if (old) old.remove();
    var t = document.createElement('div');
    t.className = 'jvb-toast' + (type === 'error' ? ' err' : '');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 2600);
  }

  // Async confirm using CMS component (fallback to native)
  function confirmAsync(opts) {
    if (window.NewNotifConfirm) {
      return window.NewNotifConfirm.danger(opts);
    }
    return Promise.resolve(confirm(opts.message || opts.title || 'Are you sure?'));
  }

  // ───────────────────────── API ─────────────────────────
  function api(action, payload) {
    payload = payload || {};
    payload.action = action;
    payload.csrf_token = boot.csrf;
    return fetch(boot.ajax, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    }).then(function (r) { return r.json(); });
  }

  // ───────────────────────── Tree traversal ─────────────────────────
  function findNode(id) {
    var secs = S.layout.sections;
    for (var si = 0; si < secs.length; si++) {
      var sec = secs[si];
      if (sec.id === id) return { kind: 'section', node: sec, arr: secs, index: si };
      var rows = sec.rows || [];
      for (var ri = 0; ri < rows.length; ri++) {
        var row = rows[ri];
        if (row.id === id) return { kind: 'row', node: row, arr: rows, index: ri, section: sec };
        var cols = row.cols || [];
        for (var ci = 0; ci < cols.length; ci++) {
          var col = cols[ci];
          if (col.id === id) return { kind: 'col', node: col, arr: cols, index: ci, row: row, section: sec };
          var els = col.elements || [];
          for (var ei = 0; ei < els.length; ei++) {
            if (els[ei].id === id) return { kind: 'element', node: els[ei], arr: els, index: ei, col: col, row: row, section: sec };
          }
        }
      }
    }
    return null;
  }

  function firstColumn() {
    var secs = S.layout.sections;
    if (!secs.length) return null;
    var last = secs[secs.length - 1];
    var rows = last.rows || [];
    if (!rows.length) return null;
    var lastRow = rows[rows.length - 1];
    var cols = lastRow.cols || [];
    if (!cols.length) return null;
    return cols[cols.length - 1];
  }

  // ───────────────────────── History ─────────────────────────
  function snapshot() { return JSON.stringify(S.layout); }

  function pushUndo(coalesceKey) {
    var now = Date.now();
    if (coalesceKey && coalesceKey === S.lastChangeKey && now - S.lastChangeAt < 700) {
      S.lastChangeAt = now;
      return; // same editing session — pre-state already on stack
    }
    S.lastChangeKey = coalesceKey || '';
    S.lastChangeAt = now;
    S.undo.push(snapshot());
    if (S.undo.length > 60) S.undo.shift();
    S.redo = [];
    updateUndoButtons();
  }

  function applySnapshot(snap) {
    S.layout = JSON.parse(snap);
    S.selected = null;
    markDirty(true);
    refreshFrame();
    renderPanel();
  }

  function undo() {
    if (!S.undo.length) return;
    S.redo.push(snapshot());
    applySnapshot(S.undo.pop());
    S.lastChangeKey = '';
    updateUndoButtons();
  }

  function redo() {
    if (!S.redo.length) return;
    S.undo.push(snapshot());
    applySnapshot(S.redo.pop());
    S.lastChangeKey = '';
    updateUndoButtons();
  }

  function updateUndoButtons() {
    $('#jvbUndo').disabled = S.undo.length === 0;
    $('#jvbRedo').disabled = S.redo.length === 0;
  }

  // ───────────────────────── Dirty + autosave ─────────────────────────
  function markDirty(d) {
    S.dirty = d;
    var el = $('#jvbSaveState');
    if (d) { el.textContent = 'Unsaved'; el.className = 'jvb-savestate is-dirty'; }
    scheduleAutosave();
  }

  var autosaveTimer = null;
  function scheduleAutosave() {
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(saveDraft, 2500);
  }

  function saveDraft(silent) {
    if (!S.dirty || S.saving) return Promise.resolve();
    S.saving = true;
    $('#jvbSaveState').textContent = 'Saving…';
    var payload = { post_id: S.postId, layout: S.layout };
    if (S.postId <= 0 && S._pendingPostSettings) {
      payload.title = S._pendingPostSettings.title;
      payload.post_type = S._pendingPostSettings.type;
    }
    return api('save_draft', payload).then(function (res) {
      S.saving = false;
      if (res.success) {
        S.dirty = false;
        S.lastSaved = res.saved_at || '';
        // Standalone: update postId after first save creates the post
        if (res.post_id && res.post_id !== S.postId) {
          S.postId = res.post_id;
          var newUrl = window.location.pathname + '?page=admin/tools/jyavani-builder&view=builder&post_id=' + res.post_id;
          try { history.replaceState(null, '', newUrl); } catch (e) {}
          // Apply pending settings (status, author) after post creation
          if (S._pendingPostSettings) {
            var sp = S._pendingPostSettings;
            S._pendingPostSettings = null;
            var up = { post_id: S.postId };
            if (sp.status) up.status = sp.status;
            if (sp.created_by) up.created_by = sp.created_by;
            api('post_settings', up);
          }
        }
        var el = $('#jvbSaveState');
        el.textContent = 'Saved ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        el.className = 'jvb-savestate is-saved';
      } else {
        $('#jvbSaveState').textContent = 'Save failed';
        if (!silent) toast(res.message || 'Save failed', true);
      }
    }).catch(function () {
      S.saving = false;
      $('#jvbSaveState').textContent = 'Save failed';
    });
  }

  // ───────────────────────── Frame ─────────────────────────
  function refreshFrame() {
    S.frameReady = false;
    api('frame_stash', { layout: S.layout }).then(function (res) {
      if (!res.success) { toast('Frame error', true); return; }
      var url = boot.frameUrl + '&preview_key=' + encodeURIComponent(res.key) + '&_=' + Date.now();
      $('#jvbFrame').src = url;
    });
  }

  function onFrameReady() {
    S.frameReady = true;
    if (S.selected) {
      framePost({ t: 'highlight', id: S.selected.id });
    }
  }

  function framePost(msg) {
    var f = $('#jvbFrame');
    if (f && f.contentWindow) f.contentWindow.postMessage(Object.assign({ source: 'jvb-parent' }, msg), '*');
  }

  // ───────────────────────── Mutations ─────────────────────────
  function makeElement(type) {
    var def = S.registry[type] || {};
    return { id: uid('e'), type: type, settings: clone(def.defaults || {}) };
  }

  function makeColumn(width) {
    return { id: uid('c'), settings: { width: { d: width || 100 } }, elements: [] };
  }

  function makeRow(widths) {
    return {
      id: uid('r'),
      settings: { gap: 20 },
      cols: (widths || [100]).map(function (w) { return makeColumn(w); }),
    };
  }

  function makeSection(widths, rowCount) {
    var rows = [];
    var n = rowCount || 1;
    for (var i = 0; i < n; i++) rows.push(makeRow(widths));
    return {
      id: uid('s'),
      settings: { layout: 'boxed' },
      rows: rows,
    };
  }

  function addElement(type, col, index) {
    pushUndo();
    var el = makeElement(type);
    col.elements = col.elements || [];
    if (index == null || index < 0 || index > col.elements.length) col.elements.push(el);
    else col.elements.splice(index, 0, el);
    markDirty(true);
    refreshFrame();
    S.selected = { kind: 'element', id: el.id };
    renderPanel();
  }

  function insertSection(widths, afterId) {
    pushUndo();
    var sec = makeSection(widths);
    var secs = S.layout.sections;
    if (afterId) {
      var f = findNode(afterId);
      if (f) secs.splice(f.index + 1, 0, sec);
      else secs.push(sec);
    } else secs.push(sec);
    markDirty(true);
    refreshFrame();
    S.selected = { kind: 'section', id: sec.id };
    renderPanel();
    toast('Section added — drag elements from the left panel', 'success');
  }

  function insertSectionMultiRow(rowCount, afterId) {
    pushUndo();
    var sec = makeSection([100], rowCount);
    var secs = S.layout.sections;
    if (afterId) {
      var f = findNode(afterId);
      if (f) secs.splice(f.index + 1, 0, sec);
      else secs.push(sec);
    } else secs.push(sec);
    markDirty(true);
    refreshFrame();
    S.selected = { kind: 'section', id: sec.id };
    renderPanel();
    toast('Section with ' + rowCount + ' rows added', 'success');
  }

  function deleteNode(id) {
    var f = findNode(id);
    if (!f) return;
    pushUndo();
    if (f.kind === 'col' && f.arr.length <= 1) { toast('A row needs at least one column', 'error'); return; }
    if (f.kind === 'row' && f.section && (f.section.rows || []).length <= 1) { toast('A section needs at least one row', 'error'); return; }
    f.arr.splice(f.index, 1);
    if (S.selected && S.selected.id === id) S.selected = null;
    markDirty(true);
    refreshFrame();
    renderPanel();
  }

  // Delete with undo toast — delete immediately, offer 4s undo window
  function deleteWithUndo(id, label) {
    var f = findNode(id);
    if (!f) return;
    if (f.kind === 'col' && f.arr.length <= 1) { toast('A row needs at least one column', 'error'); return; }
    if (f.kind === 'row' && f.section && (f.section.rows || []).length <= 1) { toast('A section needs at least one row', 'error'); return; }
    deleteNode(id);
    if (window.NewNotifToast) {
      var toastEl = window.NewNotifToast.show({ message: label || 'Deleted', type: 'warning', duration: 4000 });
      if (toastEl) {
        var undoBtn = document.createElement('button');
        undoBtn.type = 'button';
        undoBtn.textContent = 'Undo';
        undoBtn.style.cssText = 'margin-left:8px;padding:3px 12px;border:0;background:#0ea5e9;color:#fff;border-radius:4px;cursor:pointer;font-size:12px;font-weight:700;letter-spacing:.02em';
        undoBtn.addEventListener('click', function () {
          undo();
          toastEl.remove();
        });
        var content = toastEl.querySelector('.newnotif-toast__content');
        if (content) content.appendChild(undoBtn);
      }
    } else {
      toast(label || 'Deleted', 'warning');
    }
  }

  function duplicateNode(id) {
    var f = findNode(id);
    if (!f) return;
    pushUndo();
    var copy = clone(f.node);
    (function rekey(n) {
      n.id = uid(n.id.split('_')[0] || 'n');
      (n.rows || []).forEach(rekey);
      (n.cols || []).forEach(function (c) { rekey(c); (c.elements || []).forEach(rekey); });
      (n.elements || []).forEach(rekey);
    })(copy);
    f.arr.splice(f.index + 1, 0, copy);
    markDirty(true);
    refreshFrame();
    S.selected = { kind: f.kind, id: copy.id };
    renderPanel();
  }

  function moveSection(id, dir) {
    var f = findNode(id);
    if (!f || f.kind !== 'section') return;
    var ni = f.index + dir;
    if (ni < 0 || ni >= f.arr.length) return;
    pushUndo();
    f.arr.splice(f.index, 1);
    f.arr.splice(ni, 0, f.node);
    markDirty(true);
    refreshFrame();
  }

  function moveElement(id, colId, targetId, position) {
    var f = findNode(id);
    var tc = findNode(colId);
    if (!f || f.kind !== 'element' || !tc || tc.kind !== 'col') return;
    pushUndo();
    f.arr.splice(f.index, 1);
    var dest = tc.node.elements;
    var idx = dest.length;
    if (targetId) {
      for (var i = 0; i < dest.length; i++) {
        if (dest[i].id === targetId) { idx = position === 'before' ? i : i + 1; break; }
      }
      // if target was after removed element in same array, adjust
      if (f.arr === dest && f.index < idx) idx--;
    }
    dest.splice(Math.max(0, Math.min(idx, dest.length)), 0, f.node);
    markDirty(true);
    refreshFrame();
  }

  function setSetting(node, key, value) {
    node.settings = node.settings || {};
    node.settings[key] = value;
  }

  // ───────────────────────── Panel ─────────────────────────
  var panelBody, panelTitle, panelTabs;

  function currentNode() {
    if (!S.selected) return null;
    return findNode(S.selected.id);
  }

  function renderPanel() {
    panelBody = panelBody || $('#jvbPanelBody');
    panelTitle = panelTitle || $('#jvbPanelTitle');
    panelTabs = panelTabs || $('#jvbPanelTabs');
    var f = currentNode();
    if (!f) {
      panelTitle.textContent = 'Settings';
      panelTabs.hidden = true;
      panelBody.innerHTML = '<p class="jvb-panel__hint">Select a section, column or element on the canvas to edit its settings.</p>';
      return;
    }
    panelTabs.hidden = false;
    $$('#jvbPanelTabs button').forEach(function (b) { b.classList.toggle('is-active', b.dataset.ptab === S.panelTab); });

    if (f.kind === 'element') renderElementPanel(f.node);
    else if (f.kind === 'col') renderColumnPanel(f.node);
    else if (f.kind === 'row') renderRowPanel(f.node);
    else renderSectionPanel(f.node);
  }

  function titleFor(node, kind) {
    if (kind === 'col') return 'Column';
    if (kind === 'section') return 'Section';
    var def = S.registry[node.type] || {};
    return def.label || node.type;
  }

  function fieldWrap(labelText, input, note) {
    var div = document.createElement('div');
    div.className = 'jvb-f';
    if (labelText) {
      var l = document.createElement('label');
      l.textContent = labelText;
      div.appendChild(l);
    }
    div.appendChild(input);
    if (note) {
      var n = document.createElement('div');
      n.className = 'jvb-device-note';
      n.textContent = note;
      div.appendChild(n);
    }
    return div;
  }

  // device sub-tabs for a field
  function deviceField(field, node, renderOne) {
    var wrap = document.createElement('div');
    var devs = [['d', 'Desktop', 'monitor'], ['t', 'Tablet', 'tablet'], ['m', 'Mobile', 'smartphone']];
    var cur = S.device === 'tablet' ? 't' : (S.device === 'mobile' ? 'm' : 'd');
    var tabs = document.createElement('div');
    tabs.className = 'jvb-align-btns';
    tabs.style.marginBottom = '6px';
    var body = document.createElement('div');
    function draw() {
      tabs.innerHTML = '';
      devs.forEach(function (d) {
        var b = document.createElement('button');
        b.type = 'button';
        b.title = d[1];
        var ico = S.uiIcons[d[2]];
        b.innerHTML = ico || d[1][0];
        b.className = d[0] === cur ? 'is-active' : '';
        b.style.flex = 'none';
        b.style.width = '34px';
        b.addEventListener('click', function () { cur = d[0]; draw(); });
        tabs.appendChild(b);
      });
      body.innerHTML = '';
      body.appendChild(renderOne(cur));
    }
    draw();
    wrap.appendChild(tabs);
    wrap.appendChild(body);
    var note = document.createElement('div');
    note.className = 'jvb-device-note';
    note.textContent = 'Empty tablet/mobile values inherit from the larger device.';
    wrap.appendChild(note);
    return wrap;
  }

  function getDev(node, key, dev) {
    var v = (node.settings || {})[key];
    if (v && typeof v === 'object' && !Array.isArray(v)) {
      var order = dev === 'm' ? ['m', 't', 'd'] : (dev === 't' ? ['t', 'd'] : ['d']);
      for (var i = 0; i < order.length; i++) {
        if (v[order[i]] !== undefined && v[order[i]] !== '' && v[order[i]] !== null) return { value: v[order[i]], own: order[i] === dev };
      }
      return { value: undefined, own: false };
    }
    return { value: v, own: dev === 'd' };
  }

  function setDev(node, key, dev, value, coalesceKey) {
    pushUndo(coalesceKey);
    node.settings = node.settings || {};
    var v = node.settings[key];
    if (!v || typeof v !== 'object' || Array.isArray(v)) v = { d: v };
    v[dev] = value;
    node.settings[key] = v;
    markDirty(true);
    refreshFrame();
  }

  // ── field renderers ──
  var FR = {};

  FR.text = function (field, node, dev) {
    var cur = dev ? getDev(node, field.key, dev) : { value: (node.settings || {})[field.key], own: true };
    var inp = document.createElement('input');
    inp.type = 'text';
    inp.value = cur.value == null ? '' : cur.value;
    if (dev && !cur.own) inp.placeholder = String(cur.value == null ? '' : cur.value) + ' (inherited)';
    var ck = 'txt:' + field.key + ':' + node.id;
    inp.addEventListener('input', function () {
      if (dev) setDev(node, field.key, dev, inp.value, ck);
      else { pushUndo(ck); setSetting(node, field.key, inp.value); markDirty(true); refreshFrame(); }
    });
    return inp;
  };

  FR.textarea = function (field, node) {
    var inp = document.createElement('textarea');
    inp.value = (node.settings || {})[field.key] == null ? '' : (node.settings || {})[field.key];
    var ck = 'ta:' + field.key + ':' + node.id;
    inp.addEventListener('input', function () {
      pushUndo(ck); setSetting(node, field.key, inp.value); markDirty(true); refreshFrame();
    });
    return inp;
  };

  FR.number = function (field, node) {
    var inp = document.createElement('input');
    inp.type = 'number';
    var v = (node.settings || {})[field.key];
    inp.value = v == null ? '' : v;
    inp.addEventListener('input', function () {
      pushUndo('num:' + field.key + node.id);
      setSetting(node, field.key, inp.value === '' ? '' : (isNaN(+inp.value) ? inp.value : +inp.value));
      markDirty(true); refreshFrame();
    });
    return inp;
  };

  FR.datetime = function (field, node) {
    var inp = document.createElement('input');
    inp.type = 'datetime-local';
    inp.value = (node.settings || {})[field.key] || '';
    inp.addEventListener('change', function () {
      pushUndo(); setSetting(node, field.key, inp.value); markDirty(true); refreshFrame();
    });
    return inp;
  };

  FR.select = function (field, node) {
    var sel = document.createElement('select');
    var cur = (node.settings || {})[field.key];
    Object.keys(field.options || {}).forEach(function (val) {
      var o = document.createElement('option');
      o.value = val; o.textContent = field.options[val];
      if (String(cur) === val) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener('change', function () {
      pushUndo(); setSetting(node, field.key, sel.value); markDirty(true); refreshFrame(); renderPanel();
    });
    return sel;
  };

  FR.toggle = function (field, node) {
    var wrap = document.createElement('label');
    wrap.className = 'jvb-toggle';
    var txt = document.createElement('span');
    txt.textContent = field.label;
    txt.style.fontSize = '12px';
    var inp = document.createElement('input');
    inp.type = 'checkbox';
    inp.checked = !!(node.settings || {})[field.key];
    var sw = document.createElement('span');
    sw.className = 'jvb-switch';
    inp.addEventListener('change', function () {
      pushUndo(); setSetting(node, field.key, inp.checked); markDirty(true); refreshFrame(); renderPanel();
    });
    wrap.appendChild(txt); wrap.appendChild(inp); wrap.appendChild(sw);
    return wrap;
  };

  FR.slider = function (field, node, dev) {
    var cur = dev ? getDev(node, field.key, dev) : { value: (node.settings || {})[field.key], own: true };
    var row = document.createElement('div');
    row.className = 'jvb-slider-row';
    var inp = document.createElement('input');
    inp.type = 'range';
    inp.min = field.min || 0; inp.max = field.max || 100;
    var out = document.createElement('output');
    function sync(v) { inp.value = v; out.textContent = v + (field.unit || ''); }
    sync(cur.value == null ? (field.min || 0) : cur.value);
    if (dev && !cur.own && cur.value != null) out.textContent += ' ↳';
    inp.addEventListener('input', function () {
      out.textContent = inp.value + (field.unit || '');
      if (dev) setDev(node, field.key, dev, +inp.value, 'sld:' + field.key + node.id);
      else { pushUndo('sld:' + field.key + node.id); setSetting(node, field.key, +inp.value); markDirty(true); refreshFrame(); }
    });
    row.appendChild(inp); row.appendChild(out);
    return row;
  };

  FR.color = function (field, node) {
    var wrap = document.createElement('div');
    var row = document.createElement('div');
    row.className = 'jvb-color';
    var picker = document.createElement('input');
    picker.type = 'color';
    var txt = document.createElement('input');
    txt.type = 'text';
    txt.placeholder = 'Token name or #hex';
    var cur = String((node.settings || {})[field.key] || '');
    txt.value = cur;
    picker.value = /^#[0-9a-fA-F]{6}$/.test(cur) ? cur : '#2563eb';
    picker.addEventListener('input', function () {
      txt.value = picker.value;
      pushUndo('col:' + field.key + node.id); setSetting(node, field.key, picker.value); markDirty(true); refreshFrame();
    });
    txt.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(txt.value)) picker.value = txt.value;
      pushUndo('col:' + field.key + node.id); setSetting(node, field.key, txt.value); markDirty(true); refreshFrame();
    });
    row.appendChild(picker); row.appendChild(txt);
    wrap.appendChild(row);
    // token chips
    if (field.token !== false && S.tokens && S.tokens.colors) {
      var chips = document.createElement('div');
      chips.className = 'jvb-tokens-row';
      Object.keys(S.tokens.colors).forEach(function (name) {
        var c = document.createElement('button');
        c.type = 'button'; c.className = 'jvb-token-chip';
        c.innerHTML = '<i style="background:' + esc(S.tokens.colors[name]) + '"></i>' + esc(name);
        c.addEventListener('click', function () {
          txt.value = name;
          pushUndo(); setSetting(node, field.key, name); markDirty(true); refreshFrame();
        });
        chips.appendChild(c);
      });
      wrap.appendChild(chips);
    }
    return wrap;
  };

  FR.align = function (field, node, dev) {
    var cur = dev ? getDev(node, field.key, dev) : { value: (node.settings || {})[field.key], own: true };
    var alignIcons = { left: 'align-left', center: 'align-center', right: 'align-right', justify: 'align-justify' };
    var row = document.createElement('div');
    row.className = 'jvb-align-btns';
    [['left', 'Left'], ['center', 'Center'], ['right', 'Right'], ['justify', 'Justify']].forEach(function (o) {
      var b = document.createElement('button');
      b.type = 'button';
      b.title = o[1];
      var ico = S.uiIcons[alignIcons[o[0]]];
      b.innerHTML = ico || { left: '⯇', center: '≡', right: '⯈', justify: '☰' }[o[0]];
      b.className = cur.value === o[0] ? 'is-active' : '';
      b.addEventListener('click', function () {
        $$('button', row).forEach(function (x) { x.classList.remove('is-active'); });
        b.classList.add('is-active');
        if (dev) setDev(node, field.key, dev, o[0]);
        else { pushUndo(); setSetting(node, field.key, o[0]); markDirty(true); refreshFrame(); }
      });
      row.appendChild(b);
    });
    return row;
  };

  FR.spacing4 = function (field, node, dev) {
    var cur = dev ? getDev(node, field.key, dev) : { value: (node.settings || {})[field.key], own: true };
    var val = cur.value && typeof cur.value === 'object' ? cur.value : {};
    var grid = document.createElement('div');
    grid.className = 'jvb-spacing-grid';
    var unit = val.unit || 'px';
    [['t', 'Top'], ['r', 'Right'], ['b', 'Bottom'], ['l', 'Left']].forEach(function (side) {
      var inp = document.createElement('input');
      inp.type = 'number';
      inp.placeholder = side[1];
      inp.title = side[1];
      inp.value = val[side[0]] != null ? val[side[0]] : '';
      inp.addEventListener('input', function () {
        var next = Object.assign({}, (node.settings || {})[field.key] && (node.settings || {})[field.key][dev || 'd'] || val);
        next[side[0]] = inp.value === '' ? '' : +inp.value;
        next.unit = next.unit || unit;
        commit(next);
      });
      grid.appendChild(inp);
    });
    function commit(next) {
      if (dev) setDev(node, field.key, dev, next, 'sp4:' + field.key + node.id);
      else {
        pushUndo('sp4:' + field.key + node.id);
        var merged = Object.assign({}, (node.settings || {})[field.key], { d: next });
        setSetting(node, field.key, merged); markDirty(true); refreshFrame();
      }
    }
    return grid;
  };

  FR.typography = function (field, node, dev) {
    var cur = dev ? getDev(node, field.key, dev) : { value: (node.settings || {})[field.key], own: true };
    var val = cur.value && typeof cur.value === 'object' ? cur.value : {};
    var wrap = document.createElement('div');
    function commit(patch) {
      var next = Object.assign({}, val, patch);
      val = next;
      if (dev) setDev(node, field.key, dev, next, 'typ:' + field.key + node.id);
      else {
        pushUndo('typ:' + field.key + node.id);
        var merged = Object.assign({}, (node.settings || {})[field.key], { d: next });
        setSetting(node, field.key, merged); markDirty(true); refreshFrame();
      }
    }
    // size + unit
    var row1 = document.createElement('div'); row1.className = 'jvb-f__row'; row1.style.marginBottom = '6px';
    var size = document.createElement('input'); size.type = 'number'; size.placeholder = 'Size'; size.value = val.size != null ? val.size : '';
    size.addEventListener('input', function () { commit({ size: size.value === '' ? '' : +size.value }); });
    var unitSel = document.createElement('select');
    ['px', 'rem', 'em'].forEach(function (u) {
      var o = document.createElement('option'); o.value = u; o.textContent = u;
      if ((val.unit || 'px') === u) o.selected = true;
      unitSel.appendChild(o);
    });
    unitSel.addEventListener('change', function () { commit({ unit: unitSel.value }); });
    row1.appendChild(size); row1.appendChild(unitSel);
    // weight + line-height
    var row2 = document.createElement('div'); row2.className = 'jvb-f__row'; row2.style.marginBottom = '6px';
    var weight = document.createElement('select');
    [['', 'Weight'], ['400', '400'], ['500', '500'], ['600', '600'], ['700', '700'], ['800', '800']].forEach(function (w) {
      var o = document.createElement('option'); o.value = w[0]; o.textContent = w[1];
      if (String(val.weight || '') === w[0]) o.selected = true;
      weight.appendChild(o);
    });
    weight.addEventListener('change', function () { commit({ weight: weight.value }); });
    var line = document.createElement('input'); line.type = 'number'; line.step = '0.05'; line.placeholder = 'Line height'; line.value = val.line != null ? val.line : '';
    line.addEventListener('input', function () { commit({ line: line.value === '' ? '' : +line.value }); });
    row2.appendChild(weight); row2.appendChild(line);
    // letter spacing + transform
    var row3 = document.createElement('div'); row3.className = 'jvb-f__row';
    var spacing = document.createElement('input'); spacing.type = 'number'; spacing.step = '0.1'; spacing.placeholder = 'Letter px'; spacing.value = val.spacing != null ? val.spacing : '';
    spacing.addEventListener('input', function () { commit({ spacing: spacing.value === '' ? '' : +spacing.value }); });
    var transform = document.createElement('select');
    [['', 'Transform'], ['uppercase', 'AA'], ['lowercase', 'aa'], ['capitalize', 'Aa'], ['none', 'None']].forEach(function (t) {
      var o = document.createElement('option'); o.value = t[0]; o.textContent = t[1];
      if (String(val.transform || '') === t[0]) o.selected = true;
      transform.appendChild(o);
    });
    transform.addEventListener('change', function () { commit({ transform: transform.value }); });
    row3.appendChild(spacing); row3.appendChild(transform);
    wrap.appendChild(row1); wrap.appendChild(row2); wrap.appendChild(row3);
    return wrap;
  };

  FR.media = function (field, node) {
    var wrap = document.createElement('div');
    wrap.className = 'jvb-media';
    var cur = String((node.settings || {})[field.key] || '');
    function draw() {
      wrap.innerHTML = '';
      if (cur) {
        var img = document.createElement('img');
        img.src = cur;
        wrap.appendChild(img);
      }
      var btns = document.createElement('div');
      btns.className = 'jvb-media__btns';
      var pick = document.createElement('button');
      pick.type = 'button'; pick.className = 'jvb-mini-btn'; pick.textContent = cur ? 'Replace' : 'Select image';
      pick.addEventListener('click', function () {
        if (typeof openMediaSelector !== 'function') { toast('Media modal not available', true); return; }
        openMediaSelector({ url: (boot.adminBase || '') + '/admin/modal_img/index.php?embedded=1' }).then(function (detail) {
          if (!detail || !detail.url) return;
          cur = detail.url;
          pushUndo(); setSetting(node, field.key, cur);
          if (field.key === 'src' && !node.settings.alt && detail.alt) node.settings.alt = detail.alt;
          markDirty(true); refreshFrame(); draw();
        });
      });
      btns.appendChild(pick);
      if (cur) {
        var clr = document.createElement('button');
        clr.type = 'button'; clr.className = 'jvb-mini-btn danger'; clr.textContent = 'Clear';
        clr.addEventListener('click', function () {
          cur = ''; pushUndo(); setSetting(node, field.key, ''); markDirty(true); refreshFrame(); draw();
        });
        btns.appendChild(clr);
      }
      wrap.appendChild(btns);
    }
    draw();
    return wrap;
  };

  FR.gallery = function (field, node) {
    var wrap = document.createElement('div');
    var images = (node.settings || {})[field.key];
    if (!Array.isArray(images)) images = [];
    function draw() {
      wrap.innerHTML = '';
      var grid = document.createElement('div');
      grid.className = 'jvb-gallery-grid';
      images.forEach(function (img, i) {
        var cell = document.createElement('div');
        cell.className = 'jvb-gallery-grid__item';
        cell.innerHTML = '<img src="' + esc(img.url) + '" alt="">';
        var rm = document.createElement('button');
        rm.type = 'button'; rm.innerHTML = S.uiIcons['x'] || '×'; rm.title = 'Remove'; rm.className = 'jvb-ic-btn';
        rm.addEventListener('click', function () {
          pushUndo(); images.splice(i, 1); setSetting(node, field.key, images); markDirty(true); refreshFrame(); draw();
        });
        cell.appendChild(rm);
        grid.appendChild(cell);
      });
      wrap.appendChild(grid);
      var add = document.createElement('button');
      add.type = 'button'; add.className = 'jvb-mini-btn'; add.textContent = '+ Add image';
      add.addEventListener('click', function () {
        if (typeof openMediaSelector !== 'function') { toast('Media modal not available', true); return; }
        openMediaSelector({ url: (boot.adminBase || '') + '/admin/modal_img/index.php?embedded=1' }).then(function (detail) {
          if (!detail || !detail.url) return;
          pushUndo();
          images.push({ url: detail.url, alt: detail.alt || '' });
          setSetting(node, field.key, images); markDirty(true); refreshFrame(); draw();
        });
      });
      wrap.appendChild(add);
    }
    draw();
    return wrap;
  };

  FR.iconpicker = function (field, node) {
    var wrap = document.createElement('div');
    var cur = String((node.settings || {})[field.key] || 'star');
    var preview = document.createElement('div');
    preview.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:6px';
    function drawPreview() {
      preview.innerHTML = (S.iconSvgs[cur] || '') + '<span style="font-size:12px;color:#64748b">' + esc(cur) + '</span>';
    }
    drawPreview();
    var search = document.createElement('input');
    search.type = 'search'; search.placeholder = 'Search icons…';
    var grid = document.createElement('div');
    grid.className = 'jvb-icon-grid';
    function drawGrid(filter) {
      grid.innerHTML = '';
      S.icons.filter(function (n) { return !filter || n.indexOf(filter) !== -1; }).slice(0, 120).forEach(function (name) {
        var b = document.createElement('button');
        b.type = 'button'; b.title = name;
        b.className = name === cur ? 'is-active' : '';
        b.innerHTML = S.iconSvgs[name] || '';
        b.addEventListener('click', function () {
          cur = name; drawPreview();
          $$('button', grid).forEach(function (x) { x.classList.remove('is-active'); });
          b.classList.add('is-active');
          pushUndo(); setSetting(node, field.key, name); markDirty(true); refreshFrame();
        });
        grid.appendChild(b);
      });
    }
    search.addEventListener('input', function () { drawGrid(search.value.trim().toLowerCase()); });
    drawGrid('');
    wrap.appendChild(preview); wrap.appendChild(search); wrap.appendChild(grid);
    return wrap;
  };

  FR.richtext = function (field, node) {
    var wrap = document.createElement('div');
    var preview = document.createElement('div');
    preview.className = 'jvb-media';
    preview.style.cssText = 'max-height:90px;overflow:auto;text-align:left;font-size:12px;color:#475569;margin-bottom:8px';
    preview.innerHTML = (node.settings || {})[field.key] || '<em>Empty</em>';
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'jvb-editor-btn'; btn.textContent = '✎ Edit with Quill';
    btn.addEventListener('click', function () {
      openQuillOverlay((node.settings || {})[field.key] || '', function (html) {
        pushUndo(); setSetting(node, field.key, html); markDirty(true); refreshFrame();
        preview.innerHTML = html || '<em>Empty</em>';
      });
    });
    wrap.appendChild(preview); wrap.appendChild(btn);
    return wrap;
  };

  FR.code = function (field, node) {
    var wrap = document.createElement('div');
    var preview = document.createElement('textarea');
    preview.value = (node.settings || {})[field.key] || '';
    preview.style.minHeight = '90px';
    var ck = 'code:' + field.key + node.id;
    preview.addEventListener('input', function () {
      pushUndo(ck); setSetting(node, field.key, preview.value); markDirty(true); refreshFrame();
    });
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'jvb-editor-btn'; btn.style.marginTop = '6px';
    btn.textContent = '✎ Edit with CodeMirror';
    btn.addEventListener('click', function () {
      openCodeOverlay((node.settings || {})[field.key] || '', field.mode || 'htmlmixed', function (code) {
        pushUndo(); setSetting(node, field.key, code); markDirty(true); refreshFrame();
        preview.value = code;
      });
    });
    wrap.appendChild(preview); wrap.appendChild(btn);
    return wrap;
  };

  FR.repeater = function (field, node) {
    var wrap = document.createElement('div');
    var items = (node.settings || {})[field.key];
    if (!Array.isArray(items)) items = [];
    var labelKey = field.item_label || 'title';
    function persist() { setSetting(node, field.key, items); markDirty(true); refreshFrame(); }
    function draw() {
      wrap.innerHTML = '';
      items.forEach(function (item, i) {
        var card = document.createElement('div');
        card.className = 'jvb-repeater__item';
        var head = document.createElement('div');
        head.className = 'jvb-repeater__head';
        var title = document.createElement('span');
        title.textContent = (item[labelKey] || 'Item ' + (i + 1));
        var rm = document.createElement('button');
        rm.type = 'button'; rm.innerHTML = S.uiIcons['trash-2'] || '×'; rm.title = 'Remove'; rm.className = 'jvb-ic-btn';
        rm.addEventListener('click', function (e) {
          e.stopPropagation();
          pushUndo(); items.splice(i, 1); persist(); draw();
        });
        head.appendChild(title); head.appendChild(rm);
        var body = document.createElement('div');
        body.className = 'jvb-repeater__body'; body.hidden = true;
        (field.fields || []).forEach(function (sub) {
          var proxy = { settings: item, id: node.id + ':' + i };
          var renderer = FR[sub.type] || FR.text;
          var input = renderer(sub, proxy);
          body.appendChild(fieldWrap(sub.label, input));
        });
        head.addEventListener('click', function () { body.hidden = !body.hidden; });
        card.appendChild(head); card.appendChild(body);
        wrap.appendChild(card);
      });
      var add = document.createElement('button');
      add.type = 'button'; add.className = 'jvb-mini-btn'; add.textContent = '+ Add item';
      add.addEventListener('click', function () {
        pushUndo();
        var blank = {};
        (field.fields || []).forEach(function (sub) {
          blank[sub.key] = sub.type === 'richtext' ? '<p>…</p>' : '';
        });
        items.push(blank); persist(); draw();
      });
      wrap.appendChild(add);
    }
    // proxy persistence: sub-renderers call setSetting on proxy — hook it
    var origPersist = persist;
    draw();
    // Patch: when sub-fields mutate proxy.settings, also persist the items array.
    // setSetting on proxy already mutates item in-place; we just need dirty+refresh —
    // FR renderers already call markDirty+refreshFrame for direct settings objects.
    return wrap;
  };

  FR.formpicker = function (field, node) {
    if (!S.forms.length) {
      var hint = document.createElement('div');
      hint.className = 'jvb-device-note';
      hint.textContent = 'No active forms found. Create one in the Form Builder plugin.';
      return hint;
    }
    var sel = document.createElement('select');
    var cur = (node.settings || {})[field.key];
    var blank = document.createElement('option'); blank.value = '0'; blank.textContent = '— Select a form —';
    sel.appendChild(blank);
    S.forms.forEach(function (f) {
      var o = document.createElement('option');
      o.value = f.id; o.textContent = f.title + ' (' + f.slug + ')';
      if (+cur === +f.id) o.selected = true;
      sel.appendChild(o);
    });
    sel.addEventListener('change', function () {
      pushUndo(); setSetting(node, field.key, +sel.value); markDirty(true); refreshFrame();
    });
    return sel;
  };

  function renderField(field, node) {
    var input;
    if (field.devices) {
      input = deviceField(field, node, function (dev) {
        var renderer = FR[field.type] || FR.text;
        return renderer(field, node, dev);
      });
    } else {
      var renderer = FR[field.type] || FR.text;
      input = renderer(field, node);
    }
    var label = field.type === 'toggle' ? null : field.label;
    var el = fieldWrap(label, input);
    if (field.show_if) {
      var s = node.settings || {};
      var ok = Object.keys(field.show_if).every(function (k) { return s[k] == field.show_if[k]; });
      if (!ok) el.style.display = 'none';
    }
    return el;
  }

  function renderElementPanel(node) {
    var def = S.registry[node.type] || { fields: [] };
    panelTitle.textContent = (def.label || node.type) + ' Settings';
    panelBody.innerHTML = '';

    if (S.panelTab === 'content') {
      (def.fields || []).forEach(function (field) {
        panelBody.appendChild(renderField(field, node));
      });
      if (!(def.fields || []).length) {
        panelBody.innerHTML = '<p class="jvb-panel__hint">No settings for this element.</p>';
      }
    } else if (S.panelTab === 'style') {
      panelBody.appendChild(renderField({ key: 'bg_color', label: 'Background', type: 'color' }, node));
      panelBody.appendChild(renderField({ key: 'padding', label: 'Padding', type: 'spacing4', devices: true }, node));
      panelBody.appendChild(renderField({ key: 'animation', label: 'Entrance Animation', type: 'select', options: { '': 'None', 'fade': 'Fade', 'fade-up': 'Fade Up', 'fade-down': 'Fade Down', 'fade-left': 'Fade Left', 'fade-right': 'Fade Right', 'zoom-in': 'Zoom In', 'flip-up': 'Flip Up' } }, node));
      panelBody.appendChild(renderField({ key: 'anim_delay', label: 'Animation Delay (ms)', type: 'number' }, node));
    } else {
      panelBody.appendChild(renderField({ key: 'class', label: 'CSS Class', type: 'text' }, node));
      panelBody.appendChild(renderField({ key: 'css_id', label: 'CSS ID (anchor)', type: 'text' }, node));
      panelBody.appendChild(renderHideOn(node));
    }

    // quick actions
    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:6px;margin-top:20px;padding-top:14px;border-top:1px solid #e2e8f0';
    var dup = miniAction('Duplicate', function () { duplicateNode(node.id); }, false, 'copy');
    var del = miniAction('Delete', function () { deleteNode(node.id); }, true, 'trash-2');
    actions.appendChild(dup); actions.appendChild(del);
    panelBody.appendChild(actions);
  }

  function miniAction(label, fn, danger, icon) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'jvb-mini-btn' + (danger ? ' danger' : '');
    var svg = icon && S.uiIcons[icon] ? S.uiIcons[icon] : '';
    b.innerHTML = svg + '<span>' + esc(label) + '</span>';
    b.style.flex = '1';
    b.addEventListener('click', fn);
    return b;
  }

  function renderHideOn(node) {
    var wrap = document.createElement('div');
    var l = document.createElement('label');
    l.className = 'jvb-f__label'; l.textContent = 'Hide on device';
    wrap.appendChild(l);
    var row = document.createElement('div');
    row.className = 'jvb-align-btns';
    var hide = (node.settings || {}).hide_on;
    if (!Array.isArray(hide)) hide = [];
    var devIcons = { desktop: 'monitor', tablet: 'tablet', mobile: 'smartphone' };
    [['desktop', 'D'], ['tablet'], ['mobile']].forEach(function (d) {
      var b = document.createElement('button');
      b.type = 'button'; b.title = 'Hide on ' + d[0];
      var ico = S.uiIcons[devIcons[d[0]]];
      b.innerHTML = ico || d[1] || d[0][0].toUpperCase();
      b.style.flex = 'none'; b.style.width = '34px';
      b.className = hide.indexOf(d[0]) !== -1 ? 'is-active' : '';
      b.addEventListener('click', function () {
        pushUndo();
        var cur = (node.settings || {}).hide_on;
        if (!Array.isArray(cur)) cur = [];
        var i = cur.indexOf(d[0]);
        if (i === -1) cur.push(d[0]); else cur.splice(i, 1);
        setSetting(node, 'hide_on', cur);
        b.classList.toggle('is-active');
        markDirty(true); refreshFrame();
      });
      row.appendChild(b);
    });
    wrap.appendChild(row);
    return wrap;
  }

  // ── Column panel ──
  function renderColumnPanel(node) {
    panelTitle.textContent = 'Column Settings';
    panelBody.innerHTML = '';
    if (S.panelTab === 'content') {
      // width presets
      var wLabel = document.createElement('label');
      wLabel.textContent = 'Width';
      panelBody.appendChild(wLabel);
      var presets = document.createElement('div');
      presets.className = 'jvb-width-presets';
      var curW = (node.settings || {}).width;
      var curVal = curW && curW.d ? curW.d : 100;
      [['25', '25%'], ['33', '33%'], ['50', '50%'], ['66', '66%'], ['75', '75%'], ['100', '100%']].forEach(function (p) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = p[1];
        b.className = +p[0] === curVal ? 'is-active' : '';
        b.dataset.w = p[0];
        b.addEventListener('click', function () {
          pushUndo();
          var v = { d: +p[0] };
          node.settings.width = v;
          $$('button', presets).forEach(function (x) { x.classList.remove('is-active'); });
          b.classList.add('is-active');
          markDirty(true); refreshFrame();
        });
        presets.appendChild(b);
      });
      panelBody.appendChild(presets);
      panelBody.appendChild(renderField({ key: 'width', label: 'Custom width (%)', type: 'slider', min: 5, max: 100, unit: '%', devices: true }, node));
    } else if (S.panelTab === 'style') {
      panelBody.appendChild(renderField({ key: 'bg_color', label: 'Background', type: 'color' }, node));
      panelBody.appendChild(renderField({ key: 'padding', label: 'Padding', type: 'spacing4', devices: true }, node));
      panelBody.appendChild(renderField({ key: 'align', label: 'Text Align', type: 'align', devices: true }, node));
      panelBody.appendChild(renderField({ key: 'valign', label: 'Vertical Align', type: 'select', options: { '': 'Top', 'center': 'Middle', 'end': 'Bottom', 'space-between': 'Space Between' } }, node));
    } else {
      panelBody.appendChild(renderField({ key: 'class', label: 'CSS Class', type: 'text' }, node));
      panelBody.appendChild(renderField({ key: 'css_id', label: 'CSS ID (anchor)', type: 'text' }, node));
      panelBody.appendChild(renderHideOn(node));
    }
    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:6px;margin-top:20px;padding-top:14px;border-top:1px solid #e2e8f0';
    actions.appendChild(miniAction('Duplicate', function () { duplicateNode(node.id); }, false, 'copy'));
    actions.appendChild(miniAction('Delete', function () { deleteNode(node.id); }, true));
    panelBody.appendChild(actions);
  }

  // ── Row panel ──
  function renderRowPanel(node) {
    panelTitle.textContent = 'Row Settings';
    panelBody.innerHTML = '';
    if (S.panelTab === 'content') {
      panelBody.appendChild(renderField({ key: 'gap', label: 'Gap between columns (px)', type: 'number' }, node));
      panelBody.appendChild(renderField({ key: 'align', label: 'Vertical Align', type: 'select', options: { '': 'Stretch', 'center': 'Center', 'start': 'Top', 'end': 'Bottom', 'space-between': 'Space Between' } }, node));
      panelBody.appendChild(renderField({ key: 'wrap', label: 'Wrap on mobile', type: 'select', options: { '': 'No wrap', 'wrap': 'Wrap columns' } }, node));
    } else if (S.panelTab === 'style') {
      panelBody.appendChild(renderField({ key: 'bg_color', label: 'Background', type: 'color' }, node));
      panelBody.appendChild(renderField({ key: 'padding', label: 'Padding', type: 'spacing4', devices: true }, node));
      panelBody.appendChild(renderField({ key: 'margin', label: 'Margin', type: 'spacing4', devices: true }, node));
    } else {
      panelBody.appendChild(renderField({ key: 'class', label: 'CSS Class', type: 'text' }, node));
      panelBody.appendChild(renderField({ key: 'css_id', label: 'CSS ID (anchor)', type: 'text' }, node));
    }
    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:6px;margin-top:20px;padding-top:14px;border-top:1px solid #e2e8f0;flex-wrap:wrap';
    actions.appendChild(miniAction('Add Column', function () { addColumnToRow(node); }, false, 'plus'));
    actions.appendChild(miniAction('Duplicate', function () { duplicateNode(node.id); }, false, 'copy'));
    actions.appendChild(miniAction('Delete', function () { deleteNode(node.id); }, true, 'trash-2'));
    panelBody.appendChild(actions);
  }

  // v2 → v3 migration: section.columns → section.rows[0].cols
  function migrateV2toV3(layout) {
    (layout.sections || []).forEach(function (sec) {
      if (!sec.rows && sec.columns) {
        sec.rows = [{ id: uid('r'), settings: { gap: 20 }, cols: sec.columns }];
        delete sec.columns;
      }
    });
    return layout;
  }

  // ── Section panel ──
  function renderSectionPanel(node) {
    panelTitle.textContent = 'Section Settings';
    panelBody.innerHTML = '';
    var s = node.settings || {};

    if (S.panelTab === 'content') {
      panelBody.appendChild(renderField({ key: 'layout', label: 'Width Behavior', type: 'select', options: { boxed: 'Boxed (theme container)', full: 'Full (bleed background)', stretch: 'Stretch (edge to edge)' } }, node));
      panelBody.appendChild(renderField({ key: 'min_height', label: 'Min Height (e.g. 80vh, 600px)', type: 'text' }, node));
      // rows summary
      var rowCount = (node.rows || []).length;
      var info = document.createElement('div');
      info.className = 'jvb-f';
      info.innerHTML = '<label>Rows</label><div style="color:#64748b;font-size:13px;padding:6px 0">' + rowCount + ' row' + (rowCount !== 1 ? 's' : '') + ' in this section</div>';
      panelBody.appendChild(info);
    } else if (S.panelTab === 'style') {
      var t = document.createElement('div');
      t.className = 'jvb-panel__section-title'; t.textContent = 'Background';
      panelBody.appendChild(t);
      panelBody.appendChild(renderField({ key: 'bg_type', label: 'Type', type: 'select', options: { none: 'None', color: 'Color', gradient: 'Gradient', image: 'Image' } }, node));
      var bgType = s.bg_type || 'none';
      if (bgType === 'color') {
        panelBody.appendChild(renderField({ key: 'bg_color', label: 'Color', type: 'color' }, node));
      } else if (bgType === 'gradient') {
        panelBody.appendChild(renderField({ key: 'bg_from', label: 'From', type: 'color' }, node));
        panelBody.appendChild(renderField({ key: 'bg_to', label: 'To', type: 'color' }, node));
        panelBody.appendChild(renderField({ key: 'bg_angle', label: 'Angle (deg)', type: 'slider', min: 0, max: 360, unit: '°' }, node));
      } else if (bgType === 'image') {
        panelBody.appendChild(renderField({ key: 'bg_image', label: 'Image', type: 'media' }, node));
        panelBody.appendChild(renderField({ key: 'bg_size', label: 'Size', type: 'select', options: { cover: 'Cover', contain: 'Contain', auto: 'Auto' } }, node));
        panelBody.appendChild(renderField({ key: 'bg_position', label: 'Position', type: 'select', options: { center: 'Center', 'top center': 'Top', 'bottom center': 'Bottom', 'center left': 'Left', 'center right': 'Right' } }, node));
        panelBody.appendChild(renderField({ key: 'bg_attachment', label: 'Attachment', type: 'select', options: { '': 'Scroll', fixed: 'Fixed (parallax)' } }, node));
      }
      var t2 = document.createElement('div');
      t2.className = 'jvb-panel__section-title'; t2.textContent = 'Overlay';
      panelBody.appendChild(t2);
      panelBody.appendChild(renderField({ key: 'overlay_color', label: 'Color', type: 'color' }, node));
      panelBody.appendChild(renderField({ key: 'overlay_opacity', label: 'Opacity', type: 'slider', min: 0, max: 1, unit: '' }, node));

      var t3 = document.createElement('div');
      t3.className = 'jvb-panel__section-title'; t3.textContent = 'Spacing & Motion';
      panelBody.appendChild(t3);
      panelBody.appendChild(renderField({ key: 'padding', label: 'Padding', type: 'spacing4', devices: true }, node));
      panelBody.appendChild(renderField({ key: 'animation', label: 'Entrance Animation', type: 'select', options: { '': 'None', 'fade': 'Fade', 'fade-up': 'Fade Up', 'fade-down': 'Fade Down', 'fade-left': 'Fade Left', 'fade-right': 'Fade Right', 'zoom-in': 'Zoom In', 'flip-up': 'Flip Up' } }, node));

      var t4 = document.createElement('div');
      t4.className = 'jvb-panel__section-title'; t4.textContent = 'Shape Dividers';
      panelBody.appendChild(t4);
      var shapes = { none: 'None', wave: 'Wave', tilt: 'Tilt', curve: 'Curve', triangle: 'Triangle', zigzag: 'Zigzag' };
      panelBody.appendChild(renderShapeField(node, 'shape_top', 'Top Shape', shapes));
      panelBody.appendChild(renderShapeField(node, 'shape_bottom', 'Bottom Shape', shapes));
    } else {
      panelBody.appendChild(renderField({ key: 'class', label: 'CSS Class', type: 'text' }, node));
      panelBody.appendChild(renderField({ key: 'css_id', label: 'CSS ID (anchor)', type: 'text' }, node));
      panelBody.appendChild(renderHideOn(node));
    }

    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:6px;margin-top:20px;padding-top:14px;border-top:1px solid #e2e8f0;flex-wrap:wrap';
    actions.appendChild(miniAction('Duplicate', function () { duplicateNode(node.id); }, false, 'copy'));
    actions.appendChild(miniAction('Save as template', function () { saveSectionAsTemplate(node); }, false, 'bookmark'));
    actions.appendChild(miniAction('Delete', function () { deleteNode(node.id); }, true, 'trash-2'));
    panelBody.appendChild(actions);
  }

  function renderShapeField(node, key, label, shapes) {
    var s = (node.settings || {})[key] || {};
    var wrap = document.createElement('div');
    wrap.className = 'jvb-f';
    var l = document.createElement('label'); l.textContent = label;
    wrap.appendChild(l);
    var sel = document.createElement('select');
    Object.keys(shapes).forEach(function (v) {
      var o = document.createElement('option'); o.value = v; o.textContent = shapes[v];
      if ((s.type || 'none') === v) o.selected = true;
      sel.appendChild(o);
    });
    function commitShape(patch) {
      pushUndo();
      var cur = Object.assign({}, (node.settings || {})[key], patch);
      setSetting(node, key, cur);
      markDirty(true); refreshFrame();
    }
    sel.addEventListener('change', function () { commitShape({ type: sel.value }); renderPanel(); });
    wrap.appendChild(sel);
    if ((s.type || 'none') !== 'none') {
      var row = document.createElement('div');
      row.className = 'jvb-f__row'; row.style.marginTop = '6px';
      var color = document.createElement('input'); color.type = 'text'; color.placeholder = 'Color (token/hex)'; color.value = s.color || '';
      color.addEventListener('input', function () { commitShape({ color: color.value }); });
      var height = document.createElement('input'); height.type = 'number'; height.placeholder = 'Height px'; height.value = s.height || '';
      height.addEventListener('input', function () { commitShape({ height: height.value === '' ? '' : +height.value }); });
      row.appendChild(color); row.appendChild(height);
      wrap.appendChild(row);
    }
    return wrap;
  }

  // ───────────────────────── Overlay editors ─────────────────────────
  function closeOverlay() {
    var o = $('#jvbOverlay'); if (o) o.remove();
  }

  function openQuillOverlay(initial, onSave) {
    if (typeof Quill === 'undefined') { toast('Quill not available', true); return; }
    closeOverlay();
    var o = document.createElement('div');
    o.className = 'jvb-overlay'; o.id = 'jvbOverlay';
    o.innerHTML = '<div class="jvb-overlay__inner"><div class="jvb-overlay__head"><span>Edit Content</span>' +
      '<div class="jvb-overlay__actions"><button class="jvb-btn-dark" data-act="cancel">Cancel</button>' +
      '<button class="jvb-btn-dark primary" data-act="save">Save</button></div></div>' +
      '<div class="jvb-overlay__body"><div id="jvbQuillHost"></div></div></div>';
    document.body.appendChild(o);
    var quill = new Quill('#jvbQuillHost', {
      theme: 'snow',
      modules: { toolbar: [
        [{ header: [1, 2, 3, 4, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }, { background: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ align: [] }],
        ['blockquote', 'code-block'],
        ['link', 'image'],
        ['clean'],
      ] },
    });
    quill.root.innerHTML = initial || '';
    o.querySelector('[data-act="cancel"]').addEventListener('click', closeOverlay);
    o.querySelector('[data-act="save"]').addEventListener('click', function () {
      onSave(quill.root.innerHTML);
      closeOverlay();
    });
  }

  function openCodeOverlay(initial, mode, onSave) {
    closeOverlay();
    var o = document.createElement('div');
    o.className = 'jvb-overlay'; o.id = 'jvbOverlay';
    o.innerHTML = '<div class="jvb-overlay__inner"><div class="jvb-overlay__head"><span>Edit Code — ' + esc(mode.toUpperCase()) + '</span>' +
      '<div class="jvb-overlay__actions"><button class="jvb-btn-dark" data-act="cancel">Cancel</button>' +
      '<button class="jvb-btn-dark primary" data-act="save">Save</button></div></div>' +
      '<div class="jvb-overlay__body"><textarea id="jvbCodeHost"></textarea></div></div>';
    document.body.appendChild(o);
    var cm = null;
    if (typeof CodeMirror !== 'undefined') {
      cm = CodeMirror.fromTextArea($('#jvbCodeHost'), {
        mode: mode, lineNumbers: true, theme: 'dracula', lineWrapping: true,
        matchBrackets: true, autoCloseBrackets: true, viewportMargin: Infinity,
      });
      cm.setSize('100%', '100%');
      cm.setValue(initial || '');
      setTimeout(function () { cm.refresh(); }, 60);
    } else {
      $('#jvbCodeHost').value = initial || '';
    }
    o.querySelector('[data-act="cancel"]').addEventListener('click', closeOverlay);
    o.querySelector('[data-act="save"]').addEventListener('click', function () {
      onSave(cm ? cm.getValue() : $('#jvbCodeHost').value);
      closeOverlay();
    });
  }

  // ───────────────────────── Page settings modal ─────────────────────────
  function openPageSettings() {
    var o = document.createElement('div');
    o.className = 'jvb-modal'; o.id = 'jvbModal';
    o.innerHTML = '<div class="jvb-modal__inner"><h3>Page Settings</h3>' +
      '<label>Custom CSS (scoped to this page)</label>' +
      '<textarea id="jvbPageCss" placeholder=".jvb-page .my-class { … }"></textarea>' +
      '<div class="jvb-modal__actions"><button class="jvb-btn-dark" data-act="cancel">Cancel</button>' +
      '<button class="jvb-btn-dark primary" data-act="save">Save</button></div></div>';
    document.body.appendChild(o);
    $('#jvbPageCss').value = (S.layout.settings || {}).custom_css || '';
    o.addEventListener('click', function (e) { if (e.target === o) o.remove(); });
    o.querySelector('[data-act="cancel"]').addEventListener('click', function () { o.remove(); });
    o.querySelector('[data-act="save"]').addEventListener('click', function () {
      pushUndo();
      S.layout.settings = S.layout.settings || {};
      S.layout.settings.custom_css = $('#jvbPageCss').value;
      markDirty(true); refreshFrame(); o.remove();
      toast('Page CSS saved', 'success');
    });
  }

  // ───────────────────────── Post Settings Modal ─────────────────────────
  var _postSettingsCallback = null;

  function openPostSettings(onSave) {
    _postSettingsCallback = onSave || null;
    var modal = $('#jvbPostModal');
    if (modal) modal.hidden = false;
  }

  function closePostSettings() {
    var modal = $('#jvbPostModal');
    if (modal) modal.hidden = true;
    _postSettingsCallback = null;
  }

  function savePostSettings() {
    var title = ($('#jvbPostTitle') || {}).value || '';
    var type = ($('#jvbPostType') || {}).value || 'theme';
    var status = ($('#jvbPostStatus') || {}).value || 'draft';
    var authorEl = $('#jvbPostAuthor');
    var payload = { title: title, type: type, status: status };
    if (authorEl) payload.created_by = parseInt(authorEl.value, 10) || 0;

    if (S.postId > 0) {
      // Existing post: update settings via AJAX
      payload.post_id = S.postId;
      api('post_settings', payload).then(function (res) {
        if (res.success) {
          S.post.title = title;
          S.post.type = type;
          S.post.status = status;
          updateToolbarTitle();
          closePostSettings();
          toast('Post settings saved', 'success');
          if (_postSettingsCallback) _postSettingsCallback(payload);
        } else {
          toast(res.message || 'Save failed', true);
        }
      }).catch(function () { toast('Save failed', true); });
    } else {
      // Standalone: stash settings for first save/publish
      S._pendingPostSettings = payload;
      closePostSettings();
      if (_postSettingsCallback) _postSettingsCallback(payload);
    }
  }

  function updateToolbarTitle() {
    var tEl = $('.jvb-bar__title strong');
    var sEl = $('.jvb-bar__slug');
    if (tEl && S.post.title) tEl.textContent = S.post.title;
    if (sEl) sEl.textContent = '/' + (S.post.slug || '') + '/ · ' + (S.post.type || '');
  }

  // ───────────────────────── Templates ─────────────────────────
  function saveSectionAsTemplate(secNode) {
    var o = document.createElement('div');
    o.className = 'jvb-modal'; o.id = 'jvbModal';
    o.innerHTML = '<div class="jvb-modal__inner"><h3>Save Section as Template</h3>' +
      '<label>Template name</label><input type="text" id="jvbTplName" placeholder="My awesome section">' +
      '<div class="jvb-modal__actions"><button class="jvb-btn-dark" data-act="cancel">Cancel</button>' +
      '<button class="jvb-btn-dark primary" data-act="save">Save</button></div></div>';
    document.body.appendChild(o);
    o.addEventListener('click', function (e) { if (e.target === o) o.remove(); });
    o.querySelector('[data-act="cancel"]').addEventListener('click', function () { o.remove(); });
    o.querySelector('[data-act="save"]').addEventListener('click', function () {
      var name = $('#jvbTplName').value.trim();
      if (!name) { toast('Name required', true); return; }
      api('template_save', { title: name, type: 'section', layout: secNode }).then(function (res) {
        o.remove();
        if (res.success) { toast('Template saved', 'success'); loadTemplates(); }
        else toast(res.message || 'Failed', true);
      });
    });
  }

  function loadTemplates() {
    var host = $('#jvbTplList');
    host.innerHTML = '<p class="jvb-left__hint">Loading…</p>';
    api('templates_list').then(function (res) {
      if (!res.success) { host.innerHTML = ''; return; }
      host.innerHTML = '';
      (res.templates || []).forEach(function (tpl) {
        var card = document.createElement('div');
        card.className = 'jvb-tpl-card';
        card.innerHTML = '<strong>' + esc(tpl.title) + '</strong><span>' + esc(tpl.type) +
          (tpl.is_starter == 1 ? ' · <span class="jvb-tpl-badge">Starter</span>' : '') + '</span>';
        card.addEventListener('click', function () { insertTemplate(tpl); });
        host.appendChild(card);
      });
      if (!(res.templates || []).length) host.innerHTML = '<p class="jvb-left__hint">No templates yet.</p>';
    });
  }

  function insertTemplate(tpl) {
    api('template_get', { template_id: tpl.id }).then(function (res) {
      if (!res.success) { toast('Template not found', true); return; }
      var data = res.template.layout;
      function applyTpl() {
        pushUndo();
        if (tpl.type === 'page') {
          var layout = data;
          if (!layout.sections && layout.v) layout = { v: 2, settings: {}, sections: [] };
          (layout.sections || []).forEach(rekeySection);
          S.layout.sections = layout.sections || [];
          S.layout.settings = Object.assign({ custom_css: '' }, layout.settings || {});
        } else {
          var sec = data;
          rekeySection(sec);
          S.layout.sections.push(sec);
        }
        markDirty(true); refreshFrame();
        toast('Template inserted', 'success');
      }
      if (tpl.type === 'page' && S.layout.sections.length) {
        confirmAsync({ title: 'Replace page?', message: 'Replace the whole page with this template?', confirmText: 'Replace' }).then(function (ok) {
          if (ok) applyTpl();
        });
      } else {
        applyTpl();
      }
    });
  }

  function rekeySection(sec) {
    sec.id = uid('s');
    (sec.rows || []).forEach(function (r) {
      r.id = uid('r');
      (r.cols || []).forEach(function (c) {
        c.id = uid('c');
        (c.elements || []).forEach(function (e) { e.id = uid('e'); });
      });
    });
  }

  // ───────────────────────── Revisions ─────────────────────────
  function openRevisions() {
    var drawer = $('#jvbRevDrawer');
    drawer.hidden = false;
    var list = $('#jvbRevList');
    list.innerHTML = '<p class="jvb-left__hint">Loading…</p>';
    api('revisions', { post_id: S.postId }).then(function (res) {
      if (!res.success) { list.innerHTML = ''; return; }
      list.innerHTML = '';
      (res.revisions || []).forEach(function (rev) {
        var item = document.createElement('div');
        item.className = 'jvb-rev-item';
        item.innerHTML = '<div class="jvb-rev-item__meta">' + esc(rev.created_at) + ' · ' + esc(rev.author || 'system') +
          (rev.note ? ' · ' + esc(rev.note) : '') + '</div>' +
          '<div class="jvb-rev-item__stats">' + rev.sections + ' sections · ' + rev.elements + ' elements</div>';
        var btn = document.createElement('button');
        btn.className = 'jvb-mini-btn'; btn.textContent = '↺ Restore to draft';
        btn.addEventListener('click', function () {
          confirmAsync({ title: 'Restore revision?', message: 'Current draft will be replaced (undo still works).', confirmText: 'Restore' }).then(function (ok) {
            if (!ok) return;
            api('restore_revision', { revision_id: rev.id }).then(function (r) {
              if (r.success) {
                pushUndo();
                S.layout = r.layout;
                S.selected = null;
                markDirty(true);
                refreshFrame(); renderPanel();
                drawer.hidden = true;
                toast('Revision restored to draft', 'success');
              } else toast(r.message || 'Failed', true);
            });
          });
        });
        item.appendChild(btn);
        list.appendChild(item);
      });
      if (!(res.revisions || []).length) list.innerHTML = '<p class="jvb-left__hint">No revisions yet. They are created on each publish.</p>';
    });
  }

  // ───────────────────────── Palette + sections tab ─────────────────────────
  function buildPalette() {
    var host = $('#jvbPalette');
    host.innerHTML = '';

    // Sections quick-access at top — teaches hierarchy (section first)
    var secGroup = document.createElement('div');
    secGroup.className = 'jvb-pal-group';
    secGroup.innerHTML = '<div class="jvb-pal-group__title">Layout</div>';
    var secBtn = document.createElement('button');
    secBtn.type = 'button';
    secBtn.className = 'jvb-pal-item jvb-pal-item--section';
    secBtn.innerHTML = (S.iconSvgs['layout-template'] || S.iconSvgs['grid-3x3'] || '') + '<span>Section</span>';
    secBtn.title = 'Add a section (rows & columns)';
    secBtn.addEventListener('click', function () {
      $$('.jvb-left__tabs button').forEach(function (x) { x.classList.toggle('is-active', x.dataset.tab === 'sections'); });
      $$('[data-tabpanel]').forEach(function (p) { p.hidden = p.dataset.tabpanel !== 'sections'; });
    });
    secGroup.appendChild(secBtn);
    host.appendChild(secGroup);

    var groups = {};
    Object.keys(S.registry).forEach(function (type) {
      var def = S.registry[type];
      var g = def.group || 'other';
      if (!groups[g]) groups[g] = [];
      groups[g].push(type);
    });
    var groupLabels = { basic: 'Basic', content: 'Content', advanced: 'Advanced', dynamic: 'Dynamic', other: 'Other' };
    Object.keys(groups).forEach(function (g) {
      var wrap = document.createElement('div');
      wrap.className = 'jvb-pal-group';
      wrap.innerHTML = '<div class="jvb-pal-group__title">' + esc(groupLabels[g] || g) + '</div>';
      var grid = document.createElement('div');
      grid.className = 'jvb-pal-grid';
      groups[g].forEach(function (type) {
        var def = S.registry[type];
        var item = document.createElement('button');
        item.type = 'button';
        item.className = 'jvb-pal-item';
        item.dataset.type = type;
        item.innerHTML = (S.iconSvgs[def.icon] || '') + '<span>' + esc(def.label || type) + '</span>';
        item.title = 'Drag into the canvas';
        item.addEventListener('pointerdown', function (e) { startPaletteDrag(type, e); });
        grid.appendChild(item);
      });
      wrap.appendChild(grid);
      host.appendChild(wrap);
    });
  }

  // ─── Palette drag & drop ───
  // Pointer-based DnD across the iframe boundary: during a drag the iframe gets
  // pointer-events:none so all pointer events reach the parent document; drop
  // targets are resolved with elementFromPoint inside the same-origin frame doc.
  var dragState = null;

  function startPaletteDrag(type, e) {
    if (e.button !== 0 || dragState) return;
    e.preventDefault();
    var frame = $('#jvbFrame');
    var fdoc = frame.contentDocument;
    if (!fdoc) return;
    var ghost = document.createElement('div');
    ghost.className = 'jvb-drag-ghost';
    var def = S.registry[type] || {};
    ghost.innerHTML = (S.iconSvgs[def.icon] || '') + '<span>' + esc(def.label || type) + '</span>';
    document.body.appendChild(ghost);
    dragState = { type: type, ghost: ghost, fdoc: fdoc, frame: frame, line: null, colEl: null, moved: false };
    frame.style.pointerEvents = 'none';
    moveGhost(e);
    document.addEventListener('pointermove', onDragMove);
    document.addEventListener('pointerup', onDragUp);
  }

  function moveGhost(e) {
    if (!dragState) return;
    dragState.ghost.style.transform = 'translate(' + (e.clientX + 12) + 'px,' + (e.clientY + 12) + 'px)';
  }

  function onDragMove(e) {
    if (!dragState) return;
    dragState.moved = true;
    moveGhost(e);
    var ds = dragState;
    var fr = ds.frame.getBoundingClientRect();
    var inside = e.clientX >= fr.left && e.clientX <= fr.right && e.clientY >= fr.top && e.clientY <= fr.bottom;
    // clear previous indicator
    if (ds.line && ds.line.parentNode) ds.line.parentNode.removeChild(ds.line);
    ds.line = null; ds.colEl = null; ds.index = -1;
    if (!inside) return;
    var el = ds.fdoc.elementFromPoint(e.clientX - fr.left, e.clientY - fr.top);
    if (!el) return;
    var col = el.closest('.jvb-col[data-jvb]');
    var page = el.closest('.jvb-page');
    if (!col && page) {
      // empty canvas or drop between sections → first column, else create on drop
      col = page.querySelector('.jvb-col[data-jvb]');
    }
    if (!col) { ds.emptyCanvas = !!page; return; }
    ds.emptyCanvas = false;
    ds.colEl = col;
    var line = ds.fdoc.createElement('div');
    line.className = 'jvb-drop-line';
    var els = Array.prototype.filter.call(col.querySelectorAll(':scope > .jvb-el[data-jvb]'), function () { return true; });
    var placed = false;
    for (var i = 0; i < els.length; i++) {
      var r = els[i].getBoundingClientRect();
      if ((e.clientY - fr.top) < r.top + r.height / 2) {
        els[i].insertAdjacentElement('beforebegin', line);
        ds.index = i; placed = true; break;
      }
    }
    if (!placed) {
      var emptyHint = col.querySelector(':scope > .jvb-col__empty');
      if (emptyHint) emptyHint.insertAdjacentElement('beforebegin', line);
      else col.appendChild(line);
      ds.index = els.length;
    }
    ds.line = line;
  }

  function onDragUp() {
    document.removeEventListener('pointermove', onDragMove);
    document.removeEventListener('pointerup', onDragUp);
    if (!dragState) return;
    var ds = dragState; dragState = null;
    ds.frame.style.pointerEvents = '';
    if (ds.line && ds.line.parentNode) ds.line.parentNode.removeChild(ds.line);
    ds.ghost.remove();
    if (!ds.moved) return; // click without drag: no-op (DnD-only palette)
    var col = null;
    if (ds.colEl) {
      var f = findNode(ds.colEl.getAttribute('data-jvb'));
      if (f && f.kind === 'col') col = f.node;
    }
    if (!col && ds.emptyCanvas) {
      // Guard: require section first — highlight Section button
      toast('Create a section first', 'warning');
      var secBtn = $('.jvb-pal-item--section');
      if (secBtn) {
        secBtn.classList.add('is-highlight');
        setTimeout(function () { secBtn.classList.remove('is-highlight'); }, 2000);
      }
      return;
    }
    if (col) addElement(ds.type, col, ds.index);
  }

  function buildSectionPresets() {
    // Quick start: empty section (1 row, 1 col)
    var quickGrid = $('#jvbSecQuick');
    quickGrid.innerHTML = '';
    var emptyBtn = document.createElement('button');
    emptyBtn.type = 'button'; emptyBtn.className = 'jvb-sec-preset jvb-sec-preset--empty';
    emptyBtn.innerHTML = '<div class="jvb-sec-preset__icon"><i style="--f:100"></i></div><span>Empty</span>';
    emptyBtn.addEventListener('click', function () {
      var after = S.pendingInsertAfter; S.pendingInsertAfter = null;
      insertSection([100], after);
    });
    quickGrid.appendChild(emptyBtn);

    // Column layouts (1 row, N columns)
    var colPresets = [
      { label: '100', widths: [100] },
      { label: '50/50', widths: [50, 50] },
      { label: '33×3', widths: [33.33, 33.33, 33.34] },
      { label: '25×4', widths: [25, 25, 25, 25] },
      { label: '66/33', widths: [66.66, 33.34] },
      { label: '33/66', widths: [33.33, 66.67] },
      { label: '25/50/25', widths: [25, 50, 25] },
      { label: '75/25', widths: [75, 25] },
      { label: '20×5', widths: [20, 20, 20, 20, 20] },
      { label: '16×6', widths: [16.66, 16.66, 16.66, 16.66, 16.66, 16.7] },
    ];
    var colGrid = $('#jvbSecCols');
    colGrid.innerHTML = '';
    colPresets.forEach(function (p) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'jvb-sec-preset';
      var icon = '<div class="jvb-sec-preset__icon">';
      p.widths.forEach(function (w) { icon += '<i style="--f:' + w + '"></i>'; });
      icon += '</div><span>' + p.label + '</span>';
      b.innerHTML = icon;
      b.addEventListener('click', function () {
        var after = S.pendingInsertAfter; S.pendingInsertAfter = null;
        insertSection(p.widths, after);
      });
      colGrid.appendChild(b);
    });

    // Row layouts (N rows, 1 column each)
    var rowPresets = [
      { label: '1 Row', rows: 1 },
      { label: '2 Rows', rows: 2 },
      { label: '3 Rows', rows: 3 },
    ];
    var rowGrid = $('#jvbSecRows');
    rowGrid.innerHTML = '';
    rowPresets.forEach(function (p) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'jvb-sec-preset jvb-sec-preset--rows';
      var icon = '<div class="jvb-sec-preset__icon jvb-sec-preset__icon--rows">';
      for (var i = 0; i < p.rows; i++) icon += '<i style="--f:100"></i>';
      icon += '</div><span>' + p.label + '</span>';
      b.innerHTML = icon;
      b.addEventListener('click', function () {
        var after = S.pendingInsertAfter; S.pendingInsertAfter = null;
        insertSectionMultiRow(p.rows, after);
      });
      rowGrid.appendChild(b);
    });
  }

  function filterPalette(q) {
    q = (q || '').toLowerCase();
    $$('.jvb-pal-item').forEach(function (item) {
      var def = S.registry[item.dataset.type] || {};
      var hay = (item.dataset.type + ' ' + (def.label || '')).toLowerCase();
      item.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
    });
  }

  // ───────────────────────── Toolbar wiring ─────────────────────────
  function wireToolbar() {
    $$('#jvbDevices button').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('#jvbDevices button').forEach(function (x) { x.classList.remove('is-active'); });
        b.classList.add('is-active');
        S.device = b.dataset.device;
        $('#jvbFrameWrap').dataset.device = S.device;
        // re-render panel so device fields follow the preview device
        if (S.selected) renderPanel();
      });
    });
    $('#jvbUndo').addEventListener('click', undo);
    $('#jvbRedo').addEventListener('click', redo);
    $('#jvbPublish').addEventListener('click', publish);
    $('#jvbRevisions').addEventListener('click', openRevisions);
    $('#jvbRevClose').addEventListener('click', function () { $('#jvbRevDrawer').hidden = true; });
    $('#jvbPageSettings').addEventListener('click', openPageSettings);
    $('#jvbPostSettings').addEventListener('click', openPostSettings);
    $('#jvbPostModalClose').addEventListener('click', closePostSettings);
    $('#jvbPostModalCancel').addEventListener('click', closePostSettings);
    $('#jvbPostModalSave').addEventListener('click', savePostSettings);
    $('#jvbPostModal').addEventListener('click', function (e) {
      if (e.target === this) closePostSettings();
    });
    $('#jvbPanelClose').addEventListener('click', function () {
      // minimize the panel itself; reopen via ◨ toolbar button or by selecting a node
      if (S.setRightHidden) S.setRightHidden(true);
    });
    $$('#jvbPanelTabs button').forEach(function (b) {
      b.addEventListener('click', function () {
        S.panelTab = b.dataset.ptab;
        renderPanel();
      });
    });
    $$('.jvb-left__tabs button').forEach(function (b) {
      b.addEventListener('click', function () {
        $$('.jvb-left__tabs button').forEach(function (x) { x.classList.remove('is-active'); });
        b.classList.add('is-active');
        var tab = b.dataset.tab;
        $$('[data-tabpanel]').forEach(function (p) { p.hidden = p.dataset.tabpanel !== tab; });
        if (tab === 'templates') loadTemplates();
      });
    });
    $('#jvbPaletteSearch').addEventListener('input', function (e) { filterPalette(e.target.value); });

    // Side panel show/hide via semicircle edge tabs (persisted)
    var edgeLeft = $('#jvbEdgeLeft');
    function setLeftHidden(hidden) {
      $('#jvbApp').classList.toggle('left-hidden', hidden);
      edgeLeft.textContent = hidden ? '❯' : '❮';
      try { localStorage.setItem('jvb_left_hidden', hidden ? '1' : '0'); } catch (e) {}
    }
    edgeLeft.addEventListener('click', function () {
      setLeftHidden(!$('#jvbApp').classList.contains('left-hidden'));
    });
    try { if (localStorage.getItem('jvb_left_hidden') === '1') setLeftHidden(true); } catch (e) {}

    // Right settings panel; auto-shows on node select
    var edgeRight = $('#jvbEdgeRight');
    S.setRightHidden = function (hidden) {
      $('#jvbApp').classList.toggle('right-hidden', hidden);
      edgeRight.textContent = hidden ? '❮' : '❯';
      try { localStorage.setItem('jvb_right_hidden', hidden ? '1' : '0'); } catch (e) {}
    };
    edgeRight.addEventListener('click', function () {
      S.setRightHidden(!$('#jvbApp').classList.contains('right-hidden'));
    });
    try { if (localStorage.getItem('jvb_right_hidden') === '1') S.setRightHidden(true); } catch (e) {}

    // Mobile: auto-hide both panels, close on backdrop tap
    var app = $('#jvbApp');
    function isMobile() { return window.innerWidth <= 768; }
    app.classList.add('mobile-init');
    if (isMobile()) {
      setLeftHidden(true);
      S.setRightHidden(true);
    }
    // Close panel when tapping backdrop (the ::before pseudo catches via document)
    document.addEventListener('click', function (e) {
      if (!isMobile()) return;
      var t = e.target;
      // If click is on canvas area (not on panels or edge tabs or toolbar), close panels
      if (!t.closest('.jvb-left') && !t.closest('.jvb-panel') && !t.closest('.jvb-edge-tab') && !t.closest('.jvb-bar')) {
        if (!app.classList.contains('left-hidden')) setLeftHidden(true);
        if (!app.classList.contains('right-hidden')) S.setRightHidden(true);
      }
    });
    // On resize: reset panels for desktop
    window.addEventListener('resize', function () {
      if (!isMobile()) {
        setLeftHidden(false);
      }
    });
  }

  function publish() {
    // Standalone mode: require post settings before first publish
    if (S.postId <= 0) {
      openPostSettings(function (settings) {
        S._pendingPostSettings = settings;
        doPublish(settings);
      });
      return;
    }
    doPublish();
  }

  function doPublish(settings) {
    var btn = $('#jvbPublish');
    if (S.dirty) {
      // flush pending autosave first
      if (autosaveTimer) { clearTimeout(autosaveTimer); autosaveTimer = null; }
      S.dirty = true;
    }
    btn.classList.add('is-pending');
    btn.textContent = 'Publishing…';
    var payload = { post_id: S.postId, layout: S.layout };
    if (settings) {
      payload.title = settings.title;
      payload.post_type = settings.type;
    }
    api('publish', payload).then(function (res) {
      btn.classList.remove('is-pending');
      btn.textContent = 'Publish';
      if (res.success) {
        S.status = 'published';
        S.dirty = false;
        if (res.post_id && res.post_id !== S.postId) {
          S.postId = res.post_id;
          var newUrl = window.location.pathname + '?page=admin/tools/jyavani-builder&view=builder&post_id=' + res.post_id;
          try { history.replaceState(null, '', newUrl); } catch (e) {}
          // Apply status/author settings after creation
          if (settings) {
            var up = { post_id: S.postId };
            if (settings.status) up.status = settings.status;
            if (settings.created_by) up.created_by = settings.created_by;
            api('post_settings', up);
          }
          S._pendingPostSettings = null;
        }
        updateStatusBadge();
        $('#jvbSaveState').textContent = 'Published ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        $('#jvbSaveState').className = 'jvb-savestate is-saved';
        toast('Published! The live page is updated.', 'success');
      } else {
        toast(res.message || 'Publish failed', true);
      }
    }).catch(function () {
      btn.classList.remove('is-pending');
      btn.textContent = 'Publish';
      toast('Publish failed', true);
    });
  }

  function updateStatusBadge() {
    var el = $('#jvbStatus');
    el.dataset.status = S.status;
    el.textContent = S.status === 'published' ? 'Published' : (S.status === 'draft' ? 'Draft' : 'Not built');
  }

  // ───────────────────────── Frame messages ─────────────────────────
  function onMessage(e) {
    var msg = e.data;
    if (!msg || msg.source !== 'jvb-frame') return;
    switch (msg.t) {
      case 'ready':
        onFrameReady();
        break;
      case 'select': {
        var f = findNode(msg.id);
        if (f) {
          S.selected = { kind: f.kind, id: msg.id };
          S.panelTab = 'content';
          // reveal the settings panel if the user hid it
          if ($('#jvbApp').classList.contains('right-hidden') && S.setRightHidden) S.setRightHidden(false);
          renderPanel();
        }
        break;
      }
      case 'inline':
        handleInline(msg);
        break;
      case 'inline-start':
        pushUndo('inline:' + msg.id);
        break;
      case 'move':
        moveElement(msg.id, msg.colId, msg.targetId, msg.position);
        break;
      case 'sec-action':
        handleSecAction(msg);
        break;
      case 'row-action':
        handleRowAction(msg);
        break;
      case 'col-action':
        handleColAction(msg);
        break;
      case 'insert-section':
        S.pendingInsertAfter = msg.afterId;
        // open sections tab
        $$('.jvb-left__tabs button').forEach(function (x) { x.classList.toggle('is-active', x.dataset.tab === 'sections'); });
        $$('[data-tabpanel]').forEach(function (p) { p.hidden = p.dataset.tabpanel !== 'sections'; });
        toast('Pick a section layout', 'info');
        break;
    }
  }

  function handleInline(msg) {
    var f = findNode(msg.id);
    if (!f || f.kind !== 'element') return;
    var key = msg.key;
    var def = S.registry[f.node.type] || {};
    // map inline key → settings key (heading: text, richtext: content, button: text)
    var storeKey = def.inline || key;
    if (storeKey === 'text') {
      // plain text element: strip tags
      var tmp = document.createElement('div');
      tmp.innerHTML = msg.html;
      f.node.settings[storeKey] = tmp.textContent || '';
    } else {
      f.node.settings[storeKey] = msg.html;
    }
    markDirty(true);
    // no frame refresh while editing; refresh happens on next structural change
  }

  function handleSecAction(msg) {
    switch (msg.act) {
      case 'settings':
        S.selected = { kind: 'section', id: msg.id };
        S.panelTab = 'content';
        renderPanel();
        break;
      case 'add-row-above': {
        var secTop = findNode(msg.id);
        if (secTop) addRowAt(secTop.node, 0);
        break;
      }
      case 'add-row-below':
      case 'add-row': {
        var secBot = findNode(msg.id);
        if (secBot) addRowAt(secBot.node, -1);
        break;
      }
      case 'up': moveSection(msg.id, -1); break;
      case 'down': moveSection(msg.id, 1); break;
      case 'dup': duplicateNode(msg.id); break;
      case 'del':
        deleteWithUndo(msg.id, 'Section deleted');
        break;
      case 'tpl': {
        var f = findNode(msg.id);
        if (f) saveSectionAsTemplate(f.node);
        break;
      }
    }
  }

  function handleRowAction(msg) {
    switch (msg.act) {
      case 'add-col':
      case 'add-col-at': {
        var row = findNode(msg.id);
        if (row && row.kind === 'row') addColumnAt(row.node, msg.index === 'end' ? -1 : parseInt(msg.index, 10));
        break;
      }
      case 'add-col-left': {
        var rowL = findNode(msg.id);
        if (rowL && rowL.kind === 'row') addColumnAt(rowL.node, 0);
        break;
      }
      case 'add-col-right': {
        var rowR = findNode(msg.id);
        if (rowR && rowR.kind === 'row') addColumnAt(rowR.node, -1);
        break;
      }
      case 'add-row-above':
      case 'add-row-pos': {
        var secTop = findNode(msg.id);
        if (secTop && secTop.kind === 'section') addRowAt(secTop.node, msg.position === 'top' ? 0 : -1);
        break;
      }
      case 'add-row-below':
      case 'add-row-after': {
        var secBot = findNode(msg.id);
        if (secBot && secBot.kind === 'section') addRowAt(secBot.node, -1);
        break;
      }
      case 'up': moveNode(msg.id, 'row', -1); break;
      case 'down': moveNode(msg.id, 'row', 1); break;
      case 'dup': duplicateNode(msg.id); break;
      case 'del': deleteWithUndo(msg.id, 'Row deleted'); break;
    }
  }

  function handleColAction(msg) {
    switch (msg.act) {
      case 'settings':
        S.selected = { kind: 'col', id: msg.id };
        S.panelTab = 'content';
        renderPanel();
        break;
      case 'left': moveNode(msg.id, 'col', -1); break;
      case 'right': moveNode(msg.id, 'col', 1); break;
      case 'dup': duplicateNode(msg.id); break;
      case 'del':
        deleteWithUndo(msg.id, 'Column deleted');
        break;
    }
  }

  function addColumnAt(rowNode, index) {
    var cols = rowNode.cols || [];
    if (cols.length >= 12) { toast('Max 12 columns', true); return; }
    pushUndo();
    var w = Math.floor(100 / (cols.length + 1));
    cols.forEach(function (c) { c.settings.width = { d: w }; });
    var newCol = makeColumn(100 - w * cols.length);
    if (index === -1 || index >= cols.length) cols.push(newCol);
    else cols.splice(index, 0, newCol);
    markDirty(true); refreshFrame();
    S.selected = { kind: 'col', id: newCol.id };
    renderPanel();
  }

  function addRowAt(secNode, index) {
    pushUndo();
    var row = makeRow([100]);
    var rows = secNode.rows || [];
    if (index === -1 || index >= rows.length) rows.push(row);
    else rows.splice(index, 0, row);
    markDirty(true); refreshFrame();
    S.selected = { kind: 'row', id: row.id };
    renderPanel();
  }

  function moveNode(id, kind, dir) {
    var f = findNode(id);
    if (!f) return;
    var ni = f.index + dir;
    if (ni < 0 || ni >= f.arr.length) return;
    pushUndo();
    f.arr.splice(f.index, 1);
    f.arr.splice(ni, 0, f.node);
    markDirty(true);
    refreshFrame();
  }

  // ───────────────────────── Keyboard ─────────────────────────
  function onKeydown(e) {
    var tag = (e.target.tagName || '').toLowerCase();
    var typing = tag === 'input' || tag === 'textarea' || e.target.isContentEditable || $('.jvb-overlay');
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
      e.preventDefault(); undo(); return;
    }
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey))) {
      e.preventDefault(); redo(); return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (autosaveTimer) { clearTimeout(autosaveTimer); autosaveTimer = null; }
      S.dirty = true;
      saveDraft();
      return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd' && S.selected) {
      e.preventDefault(); duplicateNode(S.selected.id); return;
    }
    if (typing) return;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
      e.preventDefault();
      $('#jvbEdgeLeft').click();
      return;
    }
    if (e.key === 'Delete' && S.selected) {
      e.preventDefault();
      var f = currentNode();
      if (!f) return;
      var labels = { section: 'Section deleted', row: 'Row deleted', col: 'Column deleted', element: 'Element deleted' };
      deleteWithUndo(S.selected.id, labels[f.kind] || 'Deleted');
      return;
    }
    if (e.key === 'Escape' && S.selected) {
      S.selected = null;
      framePost({ t: 'deselect' });
      renderPanel();
    }
  }

  // ───────────────────────── Boot ─────────────────────────
  function init() {
    // Escape transformed/animated ancestors: position:fixed becomes relative to
    // them (containing block), which trapped the app beside the admin sidebar on
    // themes with entrance animations (e.g. APU .adam-main translateY).
    var appEl = $('#jvbApp');
    if (appEl && appEl.parentElement !== document.body) document.body.appendChild(appEl);

    wireToolbar();
    window.addEventListener('message', onMessage);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', function (e) {
      if (S.dirty) { e.preventDefault(); e.returnValue = ''; }
    });
    $('#jvbFrame').addEventListener('load', function () {
      // frame.js posts 'ready' shortly after; nothing else needed here
    });

    var loadPromise = S.postId > 0
      ? api('load', { post_id: S.postId })
      : Promise.resolve({ success: true, layout: { v: 2, settings: { custom_css: '' }, sections: [] }, status: 'none', has_draft: false });

    Promise.all([
      loadPromise,
      api('elements'),
    ]).then(function (results) {
      var load = results[0], els = results[1];
      if (!load.success) { toast(load.message || 'Failed to load post', true); return; }
      S.layout = load.layout || S.layout;
      S.layout = migrateV2toV3(S.layout);
      S.status = load.status || 'none';
      if (load.imported) {
        S.dirty = true;
        updateStatusBadge();
        toast('Content imported from editor. Review and save when ready.', 'info');
      }
      if (els.success) {
        (els.elements || []).forEach(function (def) { if (def.type) S.registry[def.type] = def; });
        S.icons = els.icons || [];
        S.iconSvgs = els.icon_svgs || {};
        S.uiIcons = els.ui_icons || {};
        S.tokens = els.tokens || {};
        S.forms = els.forms || [];
        S.categories = els.categories || [];
        S.role = els.role || 'editor';
      }
      buildPalette();
      buildSectionPresets();
      updateStatusBadge();
      updateUndoButtons();
      refreshFrame();
      renderPanel();
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
