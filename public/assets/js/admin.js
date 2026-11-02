(function () {
  'use strict';
  var shell = document.getElementById('appShell');
  function store(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function read(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }

  // Sidebar: collapses on desktop, slides in on mobile
  if (shell && read('pp-sidebar') === 'mini' && window.innerWidth >= 992) shell.classList.add('collapsed');
  var toggle = document.getElementById('sidebarToggle');
  if (toggle) toggle.addEventListener('click', function () {
    if (window.innerWidth >= 992) {
      shell.classList.toggle('collapsed');
      store('pp-sidebar', shell.classList.contains('collapsed') ? 'mini' : 'full');
    } else {
      shell.classList.toggle('mobile-open');
    }
  });
  var backdrop = document.getElementById('sidebarBackdrop');
  if (backdrop) backdrop.addEventListener('click', function () { shell.classList.remove('mobile-open'); });

  // Light / dark mode
  var themeBtn = document.getElementById('themeToggle');
  function paintTheme() {
    var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    if (themeBtn) themeBtn.innerHTML = '<i class="bi ' + (dark ? 'bi-sun' : 'bi-moon-stars') + '"></i>';
  }
  paintTheme();
  if (themeBtn) themeBtn.addEventListener('click', function () {
    var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', next);
    store('pp-theme', next);
    paintTheme();
    document.dispatchEvent(new CustomEvent('pp:theme'));
  });

  // Toasts from the server
  function toast(type, msg) {
    var box = document.getElementById('toasts');
    if (!box) return;
    var map = { success: 'text-bg-success', error: 'text-bg-danger', info: 'text-bg-primary', warning: 'text-bg-warning' };
    var el = document.createElement('div');
    el.className = 'toast align-items-center border-0 ' + (map[type] || 'text-bg-secondary');
    el.setAttribute('role', 'alert');
    var row = document.createElement('div'); row.className = 'd-flex';
    var body = document.createElement('div'); body.className = 'toast-body'; body.textContent = msg;
    var close = document.createElement('button'); close.type = 'button'; close.className = 'btn-close btn-close-white me-2 m-auto'; close.setAttribute('data-bs-dismiss', 'toast'); close.setAttribute('aria-label', 'Close');
    row.appendChild(body); row.appendChild(close); el.appendChild(row); box.appendChild(el);
    new bootstrap.Toast(el, { delay: type === 'error' ? 9000 : 4500 }).show();
  }
  var flash = document.getElementById('flashData');
  if (flash) { try { JSON.parse(flash.textContent).forEach(function (f) { toast(f.type, f.message); }); } catch (e) {} }

  // Prevents a double click from submitting twice. The server also refuses repeats.
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (ev.defaultPrevented || form.method.toLowerCase() !== 'post') return;
    setTimeout(function () {
      form.querySelectorAll('button:not([type=button])').forEach(function (b) { b.disabled = true; });
    }, 0);
  });

  // Confirm dialogs
  var green = '#1f7a52';
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (form.dataset.confirmed) return;
    if (form.dataset.confirm) {
      ev.preventDefault();
      Swal.fire({ title: form.dataset.confirm, icon: 'warning', showCancelButton: true, confirmButtonText: form.dataset.confirmLabel || 'Delete', cancelButtonText: 'Keep', confirmButtonColor: form.dataset.confirmLabel ? green : '#b02a37' })
        .then(function (r) { if (r.isConfirmed) { form.dataset.confirmed = '1'; form.requestSubmit(); } });
    } else if (form.dataset.cancel) {
      ev.preventDefault();
      Swal.fire({ title: form.dataset.cancel, text: 'Its effects on stock and accounts will be reversed.', icon: 'warning', input: 'text', inputPlaceholder: 'Reason (optional)', showCancelButton: true, confirmButtonText: form.dataset.cancelLabel || 'Cancel it', cancelButtonText: 'Keep', confirmButtonColor: '#b02a37' })
        .then(function (r) {
          if (r.isConfirmed) {
            var reason = form.querySelector('[name=reason]');
            if (reason) reason.value = r.value || '';
            form.dataset.confirmed = '1';
            form.requestSubmit();
          }
        });
    }
  }, true);

  // A select whose options depend on another field
  document.querySelectorAll('[data-parent-field]').forEach(function (sel) {
    var parent = sel.form.querySelector('[name="' + sel.dataset.parentField + '"]');
    if (!parent) return;
    function apply() {
      Array.prototype.forEach.call(sel.options, function (o) {
        if (!o.value) return;
        var show = !parent.value || o.dataset.parent === parent.value;
        o.hidden = !show; o.disabled = !show;
        if (!show && o.selected) sel.value = '';
      });
    }
    parent.addEventListener('change', apply); apply();
  });

  // Image preview before upload
  document.querySelectorAll('[data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var img = document.querySelector(input.dataset.preview);
      if (img && input.files[0]) { img.src = URL.createObjectURL(input.files[0]); img.classList.remove('d-none'); }
    });
  });

  // Payment modal
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-pay]');
    if (!b) return;
    document.getElementById('payDocType').value = b.dataset.docType;
    document.getElementById('payDocId').value = b.dataset.docId;
    var due = parseFloat(b.dataset.due) || 0;
    var amt = document.getElementById('payAmount');
    amt.value = due.toFixed(2); amt.max = due.toFixed(2);
    document.getElementById('payDue').textContent = 'Outstanding: ' + due.toLocaleString(undefined, { minimumFractionDigits: 2 });
    new bootstrap.Modal(document.getElementById('payModal')).show();
  });
})();
