/* Jyavani Page Builder — frame.js (runs INSIDE the canvas iframe)
 * Selection, hover chrome, inline editing, drag-sort. Talks to parent via postMessage. */
(function () {
  'use strict';

  var parent = window.parent;
  var selected = null;   // element node
  var editing = null;    // contenteditable node

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
      if (kind === 'section') {
        var tools = document.createElement('div');
        tools.className = 'jvb-sec-tools';
        tools.innerHTML =
          '<button data-act="settings" title="Settings">⚙</button>' +
          '<button data-act="up" title="Move up">↑</button>' +
          '<button data-act="down" title="Move down">↓</button>' +
          '<button data-act="dup" title="Duplicate">⧉</button>' +
          '<button data-act="tpl" title="Save as template">💾</button>' +
          '<button data-act="del" title="Delete" class="danger">✕</button>';
        node.insertBefore(tools, node.firstChild);
      }
      if (kind === 'col') {
        var add = document.createElement('button');
        add.className = 'jvb-col__add';
        add.type = 'button';
        add.textContent = '+ Add element here';
        node.appendChild(add);
      }
    });

    // insert-section bars
    document.querySelectorAll('.jvb-insert-sec').forEach(function (b) { b.remove(); });
    var page = document.querySelector('.jvb-page');
    if (!page) return;
    var sections = page.querySelectorAll(':scope > .jvb-section');
    sections.forEach(function (sec, i) {
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
    // column add
    var addBtn = e.target.closest('.jvb-col__add');
    if (addBtn) {
      e.preventDefault(); e.stopPropagation();
      var col = addBtn.closest('[data-jvb]');
      post({ t: 'want-add', colId: col.getAttribute('data-jvb') });
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
