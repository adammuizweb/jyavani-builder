/* Jyavani Page Builder — frame.js (v3.0 intuitive GUI)
 * Selection, hover chrome, inline editing, drag-sort.
 * Hierarchy: Section > Row > Column > Element.
 * Talks to parent via postMessage. */
(function () {
  'use strict';

  var parent = window.parent;
  var selected = null;
  var editing = null;

  function post(msg) {
    msg.t = msg.t || '';
    parent.postMessage(Object.assign({ source: 'jvb-frame' }, msg), '*');
  }

  function kindOf(node) {
    return node.getAttribute('data-jvb-kind') || 'element';
  }

  function labelOf(node) {
    var kind = kindOf(node);
    if (kind === 'section') return 'Section';
    if (kind === 'row') return 'Row';
    if (kind === 'col') return 'Column';
    return node.getAttribute('data-jvb-type') || 'element';
  }

  // ---------- Chrome injection ----------
  function injectChrome() {
    document.querySelectorAll('[data-jvb]').forEach(function (node) {
      if (node.querySelector(':scope > .jvb-chip')) return;
      var chip = document.createElement('span');
      chip.className = 'jvb-chip';
      chip.textContent = labelOf(node);
      node.insertBefore(chip, node.firstChild);

      var kind = kindOf(node);
      var ICONS = (window.JVB_FRAME && window.JVB_FRAME.icons) || {};

      if (kind === 'section') {
        var tools = document.createElement('div');
        tools.className = 'jvb-sec-tools';
        tools.innerHTML =
          '<button data-act="settings" title="Settings">' + (ICONS['settings'] || 'S') + '</button>' +
          '<button data-act="add-row-above" title="Add row above">' + (ICONS['plus'] || '+') + '</button>' +
          '<button data-act="add-row-below" title="Add row below">' + (ICONS['plus'] || '+') + '</button>' +
          '<button data-act="up" title="Move up">' + (ICONS['arrow-up'] || '↑') + '</button>' +
          '<button data-act="down" title="Move down">' + (ICONS['arrow-down'] || '↓') + '</button>' +
          '<button data-act="dup" title="Duplicate">' + (ICONS['copy'] || 'D') + '</button>' +
          '<button data-act="tpl" title="Save as template">' + (ICONS['bookmark'] || 'T') + '</button>' +
          '<button data-act="del" title="Delete" class="danger">' + (ICONS['x'] || '×') + '</button>';
        node.insertBefore(tools, node.firstChild);
      }

      if (kind === 'row') {
        var tools = document.createElement('div');
        tools.className = 'jvb-row-tools';
        tools.innerHTML =
          '<button data-act="add-col-left" title="Add column left">' + (ICONS['chevron-left'] || '<') + '</button>' +
          '<button data-act="add-col-right" title="Add column right">' + (ICONS['chevron-right'] || '>') + '</button>' +
          '<button data-act="up" title="Move up">' + (ICONS['arrow-up'] || '↑') + '</button>' +
          '<button data-act="down" title="Move down">' + (ICONS['arrow-down'] || '↓') + '</button>' +
          '<button data-act="dup" title="Duplicate row">' + (ICONS['copy'] || 'D') + '</button>' +
          '<button data-act="del" title="Delete row" class="danger">' + (ICONS['x'] || '×') + '</button>';
        node.insertBefore(tools, node.firstChild);
      }

      if (kind === 'col') {
        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'jvb-col__add';
        addBtn.title = 'Drop elements here';
        addBtn.innerHTML = (ICONS['rows-3'] || '') + '<span>Drop elements here</span>';
        node.appendChild(addBtn);
      }
    });

    // ---------- Row spots (+ between rows) ----------
    document.querySelectorAll('.jvb-row-spot').forEach(function (b) { b.remove(); });
    var sections = document.querySelectorAll('.jvb-section');
    sections.forEach(function (sec) {
      var inner = sec.querySelector(':scope > .jvb-section__inner');
      if (!inner) return;
      var rows = inner.querySelectorAll(':scope > .jvb-row[data-jvb]');
      // top spot (before first row)
      var spotTop = document.createElement('div');
      spotTop.className = 'jvb-row-spot jvb-row-spot--top';
      spotTop.innerHTML = '+';
      spotTop.title = 'Add row above';
      if (rows.length) rows[0].insertAdjacentElement('beforebegin', spotTop);
      else inner.appendChild(spotTop);
      // between spots
      rows.forEach(function (row) {
        var spot = document.createElement('div');
        spot.className = 'jvb-row-spot';
        spot.innerHTML = '+';
        spot.dataset.after = row.getAttribute('data-jvb');
        spot.title = 'Add row below';
        row.insertAdjacentElement('afterend', spot);
      });
    });

    // ---------- Column spots (+ between columns) ----------
    document.querySelectorAll('.jvb-col-spot').forEach(function (b) { b.remove(); });
    var rows = document.querySelectorAll('.jvb-row[data-jvb]');
    rows.forEach(function (row) {
      var cols = row.querySelectorAll(':scope > .jvb-col[data-jvb]');
      if (!cols.length) return;
      // left edge
      var spotLeft = document.createElement('div');
      spotLeft.className = 'jvb-col-spot jvb-col-spot--left';
      spotLeft.innerHTML = '+';
      spotLeft.title = 'Add column left';
      spotLeft.dataset.idx = '0';
      cols[0].insertAdjacentElement('beforebegin', spotLeft);
      // between
      cols.forEach(function (col, i) {
        var spot = document.createElement('div');
        spot.className = 'jvb-col-spot';
        spot.innerHTML = '+';
        spot.title = 'Add column here';
        spot.dataset.idx = String(i + 1);
        col.insertAdjacentElement('afterend', spot);
      });
      // right edge (last spot has class)
      var allSpots = row.querySelectorAll(':scope > .jvb-col-spot');
      var lastSpot = allSpots[allSpots.length - 1];
      if (lastSpot) lastSpot.classList.add('jvb-col-spot--right');
    });

    // ---------- Section insert bars ----------
    document.querySelectorAll('.jvb-insert-sec').forEach(function (b) { b.remove(); });
    var page = document.querySelector('.jvb-page');
    if (!page) return;
    page.querySelectorAll(':scope > .jvb-section').forEach(function (sec) {
      var bar = document.createElement('div');
      bar.className = 'jvb-insert-sec';
      bar.innerHTML = '<span>+ Add section</span>';
      bar.dataset.after = sec.getAttribute('data-jvb');
      sec.insertAdjacentElement('afterend', bar);
    });
  }

  // ---------- Selection ----------
  function selectNode(node, scroll) {
    if (selected) selected.classList.remove('jvb-node-sel');
    selected = node;
    if (node) {
      node.classList.add('jvb-node-sel');
      if (scroll) {
        node.classList.add('jvb-flash');
        setTimeout(function () { node.classList.remove('jvb-flash'); }, 850);
        try { node.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
      }
    }
  }

  function nodeById(id) {
    return document.querySelector('[data-jvb="' + id + '"]');
  }

  // ---------- Inline editing ----------
  function startInline(inlineNode) {
    stopInline();
    editing = inlineNode;
    inlineNode.setAttribute('contenteditable', 'true');
    inlineNode.classList.add('jvb-editing');
    inlineNode.focus();
    var host = inlineNode.closest('[data-jvb]');
    if (host) post({ t: 'inline-start', id: host.getAttribute('data-jvb') });
    // place caret at end
    try {
      var range = document.createRange();
      range.selectNodeContents(inlineNode);
      range.collapse(false);
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
    } catch (e) {}
  }

  function stopInline() {
    if (!editing) return;
    var node = editing;
    editing = null;
    node.removeAttribute('contenteditable');
    node.classList.remove('jvb-editing');
    var host = node.closest('[data-jvb]');
    if (host) {
      post({ t: 'inline', id: host.getAttribute('data-jvb'), key: node.getAttribute('data-jvb-inline'), html: node.innerHTML });
    }
  }

  // ---------- Drag & drop (elements) ----------
  var dragId = null;
  var dropLine = null;

  function ensureDropLine() {
    if (!dropLine) {
      dropLine = document.createElement('div');
      dropLine.className = 'jvb-drop-line';
    }
    return dropLine;
  }

  function onDragStart(e) {
    var el = e.target.closest('.jvb-el[data-jvb]');
    if (!el || editing) return;
    dragId = el.getAttribute('data-jvb');
    el.classList.add('jvb-dragging');
    try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', dragId); } catch (err) {}
    post({ t: 'dragstart', id: dragId });
  }

  function onDragEnd() {
    if (dragId) {
      var el = nodeById(dragId);
      if (el) el.classList.remove('jvb-dragging');
    }
    dragId = null;
    clearDropLine();
  }

  function clearDropLine() {
    if (dropLine && dropLine.parentNode) dropLine.parentNode.removeChild(dropLine);
    document.querySelectorAll('.jvb-drop-target').forEach(function (c) { c.classList.remove('jvb-drop-target'); });
  }

  function onDragOver(e) {
    if (!dragId) return;
    var col = e.target.closest('.jvb-col[data-jvb]');
    if (!col) return;
    e.preventDefault();
    try { e.dataTransfer.dropEffect = 'move'; } catch (err) {}
    col.classList.add('jvb-drop-target');

    // find insertion point among elements (skip dragged one)
    var els = Array.prototype.filter.call(col.querySelectorAll(':scope > .jvb-el[data-jvb]'), function (n) {
      return n.getAttribute('data-jvb') !== dragId;
    });
    var line = ensureDropLine();
    var placed = false;
    for (var i = 0; i < els.length; i++) {
      var r = els[i].getBoundingClientRect();
      if (e.clientY < r.top + r.height / 2) {
        els[i].insertAdjacentElement('beforebegin', line);
        placed = true;
        break;
      }
    }
    if (!placed) {
      var addBtn = col.querySelector(':scope > .jvb-col__add');
      if (addBtn) addBtn.insertAdjacentElement('beforebegin', line);
      else col.appendChild(line);
    }
  }

  function onDrop(e) {
    if (!dragId) return;
    var col = e.target.closest('.jvb-col[data-jvb]');
    if (!col) return;
    e.preventDefault();
    var targetId = null, position = 'end';
    if (dropLine && dropLine.previousElementSibling && dropLine.previousElementSibling.hasAttribute('data-jvb')) {
      targetId = dropLine.previousElementSibling.getAttribute('data-jvb');
      position = 'after';
    } else if (dropLine && dropLine.nextElementSibling && dropLine.nextElementSibling.hasAttribute('data-jvb')) {
      targetId = dropLine.nextElementSibling.getAttribute('data-jvb');
      position = 'before';
    }
    post({ t: 'move', id: dragId, colId: col.getAttribute('data-jvb'), targetId: targetId, position: position });
    onDragEnd();
  }

  // ---------- Events ----------
  document.addEventListener('mouseover', function (e) {
    var node = e.target.closest('[data-jvb]');
    if (!node) return;
  });

  document.addEventListener('click', function (e) {
    // section tools
    var toolBtn = e.target.closest('.jvb-sec-tools button');
    if (toolBtn) {
      e.preventDefault(); e.stopPropagation();
      var sec = toolBtn.closest('[data-jvb]');
      post({ t: 'sec-action', act: toolBtn.dataset.act, id: sec.getAttribute('data-jvb') });
      return;
    }
    // row tools
    var rowToolBtn = e.target.closest('.jvb-row-tools button');
    if (rowToolBtn) {
      e.preventDefault(); e.stopPropagation();
      var row = rowToolBtn.closest('[data-jvb]');
      post({ t: 'row-action', act: rowToolBtn.dataset.act, id: row.getAttribute('data-jvb') });
      return;
    }
    // column add-element button
    var addBtn = e.target.closest('.jvb-col__add');
    if (addBtn) {
      e.preventDefault(); e.stopPropagation();
      var col = addBtn.closest('[data-jvb]');
      post({ t: 'select', id: col.getAttribute('data-jvb'), kind: 'col' });
      return;
    }
    // column insert spot (add column at index)
    var colSpot = e.target.closest('.jvb-col-spot');
    if (colSpot) {
      e.preventDefault(); e.stopPropagation();
      var rowNode = colSpot.closest('[data-jvb-kind="row"]');
      if (rowNode) {
        var idx = colSpot.dataset.idx || 'end';
        post({ t: 'row-action', act: 'add-col-at', id: rowNode.getAttribute('data-jvb'), index: idx });
      }
      return;
    }
    // row insert spot (add row)
    var rowSpot = e.target.closest('.jvb-row-spot');
    if (rowSpot) {
      e.preventDefault(); e.stopPropagation();
      var secNode = rowSpot.closest('[data-jvb-kind="section"]');
      if (secNode) {
        var afterRowId = rowSpot.dataset.after || null;
        var pos = rowSpot.classList.contains('jvb-row-spot--top') ? 'top' : 'bottom';
        post({ t: 'row-action', act: 'add-row-pos', id: secNode.getAttribute('data-jvb'), position: pos, refId: afterRowId });
      }
      return;
    }
    // insert-section bar
    var insBar = e.target.closest('.jvb-insert-sec');
    if (insBar) {
      e.preventDefault(); e.stopPropagation();
      post({ t: 'insert-section', afterId: insBar.dataset.after || null });
      return;
    }
    // selection
    var node = e.target.closest('[data-jvb]');
    if (!node) return;
    // ignore clicks inside an active inline editor
    if (editing && editing.contains(e.target)) return;
    e.preventDefault(); e.stopPropagation();
    stopInline();
    selectNode(node, false);
    post({ t: 'select', id: node.getAttribute('data-jvb'), kind: kindOf(node) });
  }, true);

  document.addEventListener('dblclick', function (e) {
    var inline = e.target.closest('[data-jvb-inline]');
    if (!inline) return;
    e.preventDefault(); e.stopPropagation();
    var host = inline.closest('[data-jvb]');
    if (host) { selectNode(host, false); post({ t: 'select', id: host.getAttribute('data-jvb'), kind: kindOf(host) }); }
    startInline(inline);
  }, true);

  document.addEventListener('keydown', function (e) {
    if (editing && e.key === 'Escape') { stopInline(); e.preventDefault(); }
  });

  // drag events on the document
  document.addEventListener('dragstart', onDragStart, true);
  document.addEventListener('dragend', onDragEnd, true);
  document.addEventListener('dragover', onDragOver, true);
  document.addEventListener('drop', onDrop, true);

  // make elements draggable (not while editing)
  function armDraggables() {
    document.querySelectorAll('.jvb-el[data-jvb]').forEach(function (el) {
      el.setAttribute('draggable', 'true');
    });
  }

  // ---------- Parent commands ----------
  window.addEventListener('message', function (e) {
    var msg = e.data;
    if (!msg || msg.source !== 'jvb-parent') return;
    if (msg.t === 'highlight') {
      var node = nodeById(msg.id);
      if (node) selectNode(node, true);
    } else if (msg.t === 'deselect') {
      selectNode(null, false);
    }
  });

  // ---------- Boot ----------
  function boot() {
    injectChrome();
    armDraggables();
    post({ t: 'ready', postId: (window.JVB_FRAME || {}).postId || 0 });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
