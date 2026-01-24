(function () {
  function safeJSON(str, fallback) { try { return JSON.parse(str); } catch (e) { return fallback; } }
  function esc(v) {
    return String(v ?? '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function valOrNull(v) {
    if (v === '' || v == null) return null;
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
  }

  function getPatternTextarea() {
    return document.getElementById('spb-pattern-textarea') || document.querySelector('textarea[name="spb_pattern_json"]');
  }
  function getPatternArray() {
    const ta = getPatternTextarea();
    const arr = safeJSON((ta && ta.value) ? ta.value : '[]', []);
    return Array.isArray(arr) ? arr : [];
  }
  function setPatternArray(arr) {
    const ta = getPatternTextarea();
    if (!ta) return;
    ta.value = JSON.stringify(arr);
    ta.dispatchEvent(new Event('input', { bubbles: true }));
    ta.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function nextKey(dims, prefix) {
    let max = 0;
    dims.forEach(d => {
      const k = String(d.key || '');
      if (!k.startsWith(prefix)) return;
      const num = Number(k.slice(prefix.length));
      if (Number.isFinite(num)) max = Math.max(max, num);
    });
    return prefix + String(max + 1);
  }

  function loadDims() {
    const hidden = document.getElementById('spb_dims_json');
    if (!hidden) return [];
    let dims = safeJSON(hidden.value || '[]', []);
    if (!Array.isArray(dims)) dims = [];
    return dims;
  }

  function saveDims(dims) {
    const hidden = document.getElementById('spb_dims_json');
    if (!hidden) return;
    hidden.value = JSON.stringify(dims);
    hidden.dispatchEvent(new Event('input', { bubbles: true }));
    hidden.dispatchEvent(new Event('change', { bubbles: true }));
  }

  // =========================
  // ✅ AUTO PATTERN SYNC (dims order -> pattern)
  // =========================
  function ensurePatternSyncUI() {
    const ta = getPatternTextarea();
    if (!ta) return null;

    // already created?
    const existing = document.getElementById('spb-auto-pattern-sync-wrap');
    if (existing) return existing;

    const wrap = document.createElement('div');
    wrap.id = 'spb-auto-pattern-sync-wrap';
    wrap.style.cssText = 'margin:8px 0 10px; padding:10px; border:1px solid #e9e9e9; border-radius:10px; background:#fafafa; display:flex; gap:10px; align-items:center; flex-wrap:wrap;';

    const label = document.createElement('label');
    label.style.cssText = 'display:flex; align-items:center; gap:8px; font-weight:600;';
    const cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.id = 'spb-auto-pattern-sync';
    cb.checked = true;

    const txt = document.createElement('span');
    txt.textContent = 'Sünkrooni Pattern automaatselt mõõtude järjekorraga (soovituslik)';

    label.appendChild(cb);
    label.appendChild(txt);

    const hint = document.createElement('div');
    hint.style.cssText = 'opacity:.75; font-size:12px;';
    hint.textContent = 'Kui sees: Pattern lukustub ja uuendub automaatselt, kui liigutad mõõte ↑↓ või lisad uusi ridu.';

    wrap.appendChild(label);
    wrap.appendChild(hint);

    // Insert right above textarea
    ta.parentNode.insertBefore(wrap, ta);

    // Toggle textarea readonly
    function applyLock() {
      const on = cb.checked;
      ta.readOnly = on;
      ta.style.opacity = on ? '0.75' : '1';
      ta.style.background = on ? '#f6f6f6' : '';
    }
    cb.addEventListener('change', applyLock);
    applyLock();

    return wrap;
  }

  function isAutoPatternSyncOn() {
    const cb = document.getElementById('spb-auto-pattern-sync');
    return cb ? !!cb.checked : false;
  }

  function syncPatternFromDims(dims) {
    // pattern = dims keys in their current order
    const keys = (Array.isArray(dims) ? dims : [])
      .map(d => (d && d.key ? String(d.key).trim() : ''))
      .filter(Boolean);

    // Only set if different to reduce noise
    const current = getPatternArray();
    const same =
      Array.isArray(current) &&
      current.length === keys.length &&
      current.every((v, i) => String(v) === String(keys[i]));

    if (!same) setPatternArray(keys);
  }

  // =========================
  // DIMS TABLE
  // =========================
  function renderDimsTable() {
    const table = document.getElementById('spb-dims-table');
    const hidden = document.getElementById('spb_dims_json');
    if (!table || !hidden) return;

    let dims = loadDims();

    // ensure sync UI exists (Pattern box is on the same edit screen)
    ensurePatternSyncUI();
    if (isAutoPatternSyncOn()) syncPatternFromDims(dims);

    const tbody = table.querySelector('tbody');
    tbody.innerHTML = '';

    dims.forEach((d, idx) => {
      const tr = document.createElement('tr');

      const key = d.key || '';
      const type = (d.type === 'angle') ? 'angle' : 'length';
      const label = d.label || key;
      const min = (d.min ?? '');
      const max = (d.max ?? '');
      const def = (d.def ?? '');
      const dir = (String(d.dir || 'L').toUpperCase() === 'R') ? 'R' : 'L';
      const pol = (d.pol === 'outer') ? 'outer' : 'inner';
      const ret = !!d.ret;

      tr.innerHTML = `
        <td><input type="text" data-k="key" value="${esc(key)}" style="width:100%"></td>
        <td>
          <select data-k="type" style="width:100%">
            <option value="length" ${type === 'length' ? 'selected' : ''}>length</option>
            <option value="angle" ${type === 'angle' ? 'selected' : ''}>angle</option>
          </select>
        </td>
        <td><input type="text" data-k="label" value="${esc(label)}" style="width:100%"></td>
        <td><input type="number" data-k="min" value="${esc(min)}" style="width:100%"></td>
        <td><input type="number" data-k="max" value="${esc(max)}" style="width:100%"></td>
        <td><input type="number" data-k="def" value="${esc(def)}" style="width:100%"></td>
        <td>
          <select data-k="dir" style="width:100%">
            <option value="L" ${dir === 'L' ? 'selected' : ''}>L</option>
            <option value="R" ${dir === 'R' ? 'selected' : ''}>R</option>
          </select>
        </td>
        <td>
          <select data-k="pol" style="width:100%" ${type === 'angle' ? '' : 'disabled'}>
            <option value="inner" ${pol === 'inner' ? 'selected' : ''}>Seest</option>
            <option value="outer" ${pol === 'outer' ? 'selected' : ''}>Väljast</option>
          </select>
        </td>
        <td style="text-align:center">
          <input type="checkbox" data-k="ret" ${ret ? 'checked' : ''} ${type === 'angle' ? '' : 'disabled'}>
        </td>
        <td style="white-space:nowrap;text-align:right">
          <button type="button" class="button spb-up" data-i="${idx}" title="Üles">↑</button>
          <button type="button" class="button spb-down" data-i="${idx}" title="Alla">↓</button>
          <button type="button" class="button spb-del" data-i="${idx}" title="Kustuta">X</button>
        </td>
      `;

      tbody.appendChild(tr);
    });

    function syncToHidden() {
      const rows = Array.from(tbody.querySelectorAll('tr'));
      dims = rows.map((tr) => {
        const get = (k) => tr.querySelector(`[data-k="${k}"]`);
        const type = get('type').value === 'angle' ? 'angle' : 'length';

        const polSel = get('pol');
        const retCb = get('ret');
        if (polSel) polSel.disabled = (type !== 'angle');
        if (retCb) retCb.disabled = (type !== 'angle');

        return {
          key: (get('key').value || '').trim(),
          type,
          label: (get('label').value || '').trim(),
          min: valOrNull(get('min').value),
          max: valOrNull(get('max').value),
          def: valOrNull(get('def').value),
          dir: (get('dir').value === 'R') ? 'R' : 'L',
          pol: (type === 'angle') ? (get('pol').value === 'outer' ? 'outer' : 'inner') : null,
          ret: (type === 'angle') ? !!get('ret').checked : false,
        };
      }).filter(x => x.key);

      saveDims(dims);

      // ✅ keep pattern in sync when keys/order change
      if (isAutoPatternSyncOn()) syncPatternFromDims(dims);
    }

    tbody.oninput = tbody.onchange = syncToHidden;

    tbody.onclick = function (e) {
      const up = e.target.closest('.spb-up');
      const down = e.target.closest('.spb-down');
      const del = e.target.closest('.spb-del');

      // ⬆️ üles
      if (up) {
        const i = Number(up.dataset.i);
        let arr = loadDims();
        if (i > 0) {
          const tmp = arr[i - 1];
          arr[i - 1] = arr[i];
          arr[i] = tmp;
          saveDims(arr);
          if (isAutoPatternSyncOn()) syncPatternFromDims(arr);
          renderDimsTable();
        }
        return;
      }

      // ⬇️ alla
      if (down) {
        const i = Number(down.dataset.i);
        let arr = loadDims();
        if (i < arr.length - 1) {
          const tmp = arr[i + 1];
          arr[i + 1] = arr[i];
          arr[i] = tmp;
          saveDims(arr);
          if (isAutoPatternSyncOn()) syncPatternFromDims(arr);
          renderDimsTable();
        }
        return;
      }

      // ❌ kustuta
      if (del) {
        const i = Number(del.dataset.i);
        let arr = loadDims();
        arr.splice(i, 1);
        saveDims(arr);
        if (isAutoPatternSyncOn()) syncPatternFromDims(arr);
        renderDimsTable();
        return;
      }
    };
  }

  // =========================
  // MATERIALS TABLE (unchanged)
  // =========================
  function renderMaterialsTable() {
    const table = document.getElementById('spb-materials-table');
    const hidden = document.getElementById('spb_materials_json');
    if (!table || !hidden) return;

    let mats = safeJSON(hidden.value || '[]', []);
    if (!Array.isArray(mats)) mats = [];

    const tbody = table.querySelector('tbody');
    tbody.innerHTML = '';

    mats.forEach((m, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="text" data-k="key" value="${esc(m.key || '')}" style="width:100%"></td>
        <td><input type="text" data-k="label" value="${esc(m.label || '')}" style="width:100%"></td>
        <td><input type="number" step="0.01" data-k="eur_m2" value="${esc(m.eur_m2 ?? '')}" style="width:100%"></td>
        <td style="white-space:nowrap;text-align:right"><button type="button" class="button spb-mdel" data-i="${idx}">X</button></td>
      `;
      tbody.appendChild(tr);
    });

    function sync() {
      const rows = Array.from(tbody.querySelectorAll('tr'));
      mats = rows.map((tr) => {
        const key = (tr.querySelector('[data-k="key"]').value || '').trim();
        if (!key) return null;
        return {
          key,
          label: (tr.querySelector('[data-k="label"]').value || '').trim(),
          eur_m2: Number(tr.querySelector('[data-k="eur_m2"]').value || 0),
        };
      }).filter(Boolean);

      hidden.value = JSON.stringify(mats);
      hidden.dispatchEvent(new Event('input', { bubbles: true }));
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }

    tbody.oninput = tbody.onchange = sync;

    tbody.onclick = function (e) {
      const btn = e.target.closest('.spb-mdel');
      if (!btn) return;
      const i = Number(btn.dataset.i);
      mats.splice(i, 1);
      hidden.value = JSON.stringify(mats);
      hidden.dispatchEvent(new Event('input', { bubbles: true }));
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
      renderMaterialsTable();
    };
  }

  function addDim(type) {
    const hidden = document.getElementById('spb_dims_json');
    if (!hidden) return;

    let dims = safeJSON(hidden.value || '[]', []);
    if (!Array.isArray(dims)) dims = [];

    const key = nextKey(dims, type === 'angle' ? 'a' : 's');

    if (type === 'angle') {
      dims.push({ key, type: 'angle', label: key, min: 5, max: 215, def: 135, dir: 'L', pol: 'inner', ret: false });
    } else {
      dims.push({ key, type: 'length', label: key, min: 10, max: 500, def: 50, dir: 'L' });
    }

    hidden.value = JSON.stringify(dims);
    hidden.dispatchEvent(new Event('input', { bubbles: true }));
    hidden.dispatchEvent(new Event('change', { bubbles: true }));

    // existing checkbox: append new key to pattern if user wants
    const auto = document.getElementById('spb-auto-append-pattern');
    if (auto && auto.checked) {
      const pat = getPatternArray();
      pat.push(key);
      setPatternArray(pat);
    }

    // ✅ if auto sync on: override pattern to follow dims order (stronger rule)
    if (isAutoPatternSyncOn()) syncPatternFromDims(dims);

    renderDimsTable();
  }

  document.addEventListener('DOMContentLoaded', function () {
    ensurePatternSyncUI();

    renderDimsTable();
    renderMaterialsTable();

    const addLen = document.getElementById('spb-add-length');
    if (addLen) addLen.addEventListener('click', () => addDim('length'));

    const addAng = document.getElementById('spb-add-angle');
    if (addAng) addAng.addEventListener('click', () => addDim('angle'));

    const addMat = document.getElementById('spb-add-material');
    if (addMat) {
      addMat.addEventListener('click', function () {
        const hidden = document.getElementById('spb_materials_json');
        let mats = safeJSON(hidden.value || '[]', []);
        if (!Array.isArray(mats)) mats = [];
        mats.push({ key: 'NEW', label: 'Uus materjal', eur_m2: 0 });
        hidden.value = JSON.stringify(mats);
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
        renderMaterialsTable();
      });
    }

    // Kui user teeb Pattern textarea unlocki ja hakkab käsitsi muutma,
    // siis me ei sunni seda tagasi (ainult siis, kui auto sync on).
  });
})();
