// Line items on the sale and purchase forms. Totals here are a preview: the server recalculates them.
(function () {
  'use strict';
  var form = document.getElementById('docForm');
  if (!form) return;
  var data = JSON.parse(document.getElementById('docData').textContent);
  var lk = data.lk;
  var isSale = form.dataset.kind === 'sale';
  var tbody = document.getElementById('lines');
  var farm = document.getElementById('docFarm');
  var seq = 0;

  var types = isSale
    ? { bird: 'Live birds', egg: 'Eggs', feed: 'Feed (kg)', product: 'Other product' }
    : { chick: 'Chicks / birds', feed: 'Feed', medicine: 'Medicine', product: 'Product for resale', equipment: 'Equipment (capital)', other: 'Other (expense)' };

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML.replace(/"/g, '&quot;'); }
  function options(list, selected, placeholder) {
    var h = '<option value="">' + esc(placeholder || 'Choose…') + '</option>';
    list.forEach(function (o) {
      h += '<option value="' + esc(o.value) + '"' + (String(o.value) === String(selected) ? ' selected' : '') + (o.price ? ' data-price="' + esc(o.price) + '"' : '') + '>' + esc(o.label) + '</option>';
    });
    return h;
  }
  function atFarm(list) {
    if (!farm.value) return list;
    return list.filter(function (o) { return o.parent === undefined || o.parent === null || String(o.parent) === farm.value; });
  }

  function itemCell(tr, line) {
    var i = tr.dataset.i, type = tr.querySelector('.l-type').value, cell = tr.querySelector('.l-item');
    var ref = line ? line.ref_id : '';
    var name = 'items[' + i + ']';
    var h = '';
    if (!type) { cell.innerHTML = '<span class="text-body-secondary small">Choose what you are ' + (isSale ? 'selling' : 'buying') + ' first.</span>'; return; }
    if (isSale) {
      if (type === 'bird') {
        h = '<select class="form-select form-select-sm" name="' + name + '[ref_id]" required>' + options(atFarm(lk.flocks), ref, 'Choose flock…') + '</select>';
      } else if (type === 'egg') {
        var stock = farm.value ? (lk.eggStock[farm.value] || 0) : null;
        h = '<select class="form-select form-select-sm" name="' + name + '[ref_id]">' + options(atFarm(lk.batches), ref, 'Any batch (optional)') + '</select>' + (stock !== null ? '<div class="form-text">' + Number(stock).toLocaleString() + ' good eggs in stock</div>' : '');
      } else if (type === 'feed') {
        h = '<select class="form-select form-select-sm l-ref" name="' + name + '[ref_id]" required>' + options(lk.feed, ref) + '</select><div class="form-text l-stock"></div>';
      } else {
        h = '<select class="form-select form-select-sm" name="' + name + '[ref_id]" required>' + options(lk.products, ref) + '</select>';
      }
    } else {
      if (type === 'chick') {
        h = '<select class="form-select form-select-sm" name="' + name + '[ref_id]" required>' + options(atFarm(lk.batches), ref, 'Add to batch…') + '</select>';
      } else if (type === 'feed') {
        h = '<div class="d-flex gap-1"><select class="form-select form-select-sm" name="' + name + '[ref_id]" required>' + options(lk.feed, ref) + '</select><select class="form-select form-select-sm" style="max-width:130px" name="' + name + '[unit_id]" required aria-label="Unit">' + options(lk.units, line ? line.unit_id : '', 'Unit…') + '</select></div>';
      } else if (type === 'medicine') {
        h = '<select class="form-select form-select-sm mb-1" name="' + name + '[ref_id]" required>' + options(lk.medicines, ref) + '</select><div class="d-flex gap-1"><input class="form-control form-control-sm" name="' + name + '[lot_no]" placeholder="Lot no." maxlength="60" value="' + esc(line && line.lot_no) + '"><input class="form-control form-control-sm" type="date" name="' + name + '[expiry_date]" aria-label="Expiry date" title="Expiry date" value="' + esc(line && line.expiry_date) + '"></div>';
      } else if (type === 'product') {
        h = '<select class="form-select form-select-sm" name="' + name + '[ref_id]" required>' + options(lk.stockProducts, ref) + '</select>';
      } else {
        h = '<input class="form-control form-control-sm" name="' + name + '[description]" placeholder="What was bought" maxlength="255" required value="' + esc(line && line.description) + '">';
      }
    }
    cell.innerHTML = h;
    feedStockHint(tr);
  }

  function feedStockHint(tr) {
    var sel = tr.querySelector('.l-ref'), hint = tr.querySelector('.l-stock');
    if (!sel || !hint) return;
    var st = farm.value && lk.feedStock[farm.value] ? lk.feedStock[farm.value][sel.value] : undefined;
    hint.textContent = sel.value && farm.value ? Number(st || 0).toLocaleString() + ' kg in stock at this farm' : '';
  }

  function addLine(line) {
    var i = seq++;
    var tr = document.createElement('tr');
    tr.dataset.i = i;
    var typeOpts = '<option value="">Choose…</option>';
    Object.keys(types).forEach(function (k) { typeOpts += '<option value="' + k + '"' + (line && line.item_type === k ? ' selected' : '') + '>' + esc(types[k]) + '</option>'; });
    tr.innerHTML =
      '<td><select class="form-select form-select-sm l-type" name="items[' + i + '][item_type]" aria-label="Item type">' + typeOpts + '</select></td>' +
      '<td class="l-item"></td>' +
      '<td><input class="form-control form-control-sm l-qty" type="number" step="any" min="0" name="items[' + i + '][quantity]" aria-label="Quantity" value="' + esc(line ? line.quantity : '') + '"></td>' +
      '<td><input class="form-control form-control-sm l-price" type="number" step="any" min="0" name="items[' + i + '][unit_price]" aria-label="Unit price" value="' + esc(line ? line.unit_price : '') + '"></td>' +
      '<td class="text-end l-total">0.00</td>' +
      '<td class="text-end"><button type="button" class="btn btn-sm btn-action text-danger l-del" aria-label="Remove item"><i class="bi bi-x-lg"></i></button></td>';
    tbody.appendChild(tr);
    itemCell(tr, line);
    recalc();
  }

  function fmt(n) { return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function set(id, n) { var el = document.getElementById(id); if (el) el.textContent = fmt(n); }

  function recalc() {
    var sub = 0;
    tbody.querySelectorAll('tr').forEach(function (tr) {
      var line = (parseFloat(tr.querySelector('.l-qty').value) || 0) * (parseFloat(tr.querySelector('.l-price').value) || 0);
      tr.querySelector('.l-total').textContent = fmt(line);
      sub += line;
    });
    var grand = sub;
    if (isSale) {
      var disc = parseFloat(document.getElementById('docDiscount').value) || 0;
      var rate = parseFloat(document.getElementById('docTax').value) || 0;
      var tax = (sub - disc) * rate / 100;
      grand = sub - disc + tax;
      set('tSub', sub); set('tDisc', disc); set('tTax', tax);
    }
    set('tGrand', grand);
    set('tDue', grand - (parseFloat(document.getElementById('docPaid').value) || 0));
  }

  tbody.addEventListener('change', function (ev) {
    var tr = ev.target.closest('tr');
    if (ev.target.classList.contains('l-type')) { itemCell(tr, null); }
    if (ev.target.tagName === 'SELECT' && !ev.target.classList.contains('l-type')) {
      var opt = ev.target.selectedOptions[0];
      var price = tr.querySelector('.l-price');
      if (opt && opt.dataset.price && !price.value) { price.value = parseFloat(opt.dataset.price); recalc(); }
    }
    feedStockHint(tr);
  });
  tbody.addEventListener('input', recalc);
  tbody.addEventListener('click', function (ev) {
    if (ev.target.closest('.l-del')) { ev.target.closest('tr').remove(); recalc(); }
  });
  form.addEventListener('input', function (ev) { if (ev.target.closest('.card') && !ev.target.closest('#lines')) recalc(); });
  document.getElementById('addLine').addEventListener('click', function () { addLine(null); });
  farm.addEventListener('change', function () {
    // Flocks and stock depend on the farm, so re-render each line's item picker.
    tbody.querySelectorAll('tr').forEach(function (tr) {
      var sel = tr.querySelector('.l-item select');
      itemCell(tr, { ref_id: sel ? sel.value : '' });
    });
  });

  (data.lines && data.lines.length ? data.lines : [null]).forEach(addLine);
})();
