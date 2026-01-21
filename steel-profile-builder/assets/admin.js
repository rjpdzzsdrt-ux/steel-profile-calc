(function(){
  function parse(json, fallback){
    try {
      const v = JSON.parse(json);
      return Array.isArray(v) ? v : fallback;
    } catch(e){
      return fallback;
    }
  }

  function renderDims(){
    const table = document.getElementById('spb-dims-table');
    const hidden = document.getElementById('spb_dims_json');
    const addBtn = document.getElementById('spb-add-dim');
    if (!table || !hidden || !addBtn) return;

    let rows = parse(hidden.value || '[]', []);

    function rowHTML(r){
      const dirDisabled = (r.type !== 'angle') ? 'disabled' : '';
      return `
        <tr>
          <td><input class="spb-key" value="${r.key||''}" placeholder="s1 / a1"></td>
          <td>
            <select class="spb-type">
              <option value="length" ${r.type==='length'?'selected':''}>pikkus</option>
              <option value="angle" ${r.type==='angle'?'selected':''}>nurk</option>
            </select>
          </td>
          <td><input class="spb-label" value="${r.label||''}" placeholder="nt Sokli kõrgus"></td>
          <td><input type="number" class="spb-min" value="${r.min ?? ''}"></td>
          <td><input type="number" class="spb-max" value="${r.max ?? ''}"></td>
          <td><input type="number" class="spb-def" value="${r.def ?? ''}"></td>
          <td>
            <select class="spb-dir" ${dirDisabled}>
              <option value="L" ${(r.dir!=='R')?'selected':''}>L</option>
              <option value="R" ${(r.dir==='R')?'selected':''}>R</option>
            </select>
          </td>
          <td><button type="button" class="button spb-del">✕</button></td>
        </tr>
      `;
    }

    function sync(){
      const out = [];
      table.querySelectorAll('tbody tr').forEach(tr=>{
        const type = tr.querySelector('.spb-type').value;
        out.push({
          key: tr.querySelector('.spb-key').value.trim(),
          type,
          label: tr.querySelector('.spb-label').value.trim(),
          min: tr.querySelector('.spb-min').value,
          max: tr.querySelector('.spb-max').value,
          def: tr.querySelector('.spb-def').value,
          dir: (type === 'angle') ? tr.querySelector('.spb-dir').value : 'L',
        });
      });
      hidden.value = JSON.stringify(out);
    }

    function render(){
      const tbody = table.querySelector('tbody');
      tbody.innerHTML = rows.map(rowHTML).join('');

      tbody.querySelectorAll('.spb-del').forEach((btn, idx)=>{
        btn.addEventListener('click', ()=>{
          rows.splice(idx, 1);
          hidden.value = JSON.stringify(rows);
          render();
        });
      });

      // type toggle enables dir
      tbody.querySelectorAll('.spb-type').forEach(sel=>{
        sel.addEventListener('change', ()=>{
          const tr = sel.closest('tr');
          const dir = tr.querySelector('.spb-dir');
          dir.disabled = (sel.value !== 'angle');
          if (sel.value !== 'angle') dir.value = 'L';
          sync();
        });
      });

      tbody.addEventListener('input', sync);
      sync();
    }

    addBtn.addEventListener('click', ()=>{
      rows.push({key:'',type:'length',label:'',min:'',max:'',def:'',dir:'L'});
      hidden.value = JSON.stringify(rows);
      render();
    });

    render();
  }

  function renderMaterials(){
    const table = document.getElementById('spb-materials-table');
    const hidden = document.getElementById('spb_materials_json');
    const addBtn = document.getElementById('spb-add-material');
    if (!table || !hidden || !addBtn) return;

    let rows = parse(hidden.value || '[]', []);

    function rowHTML(r){
      return `
        <tr>
          <td><input class="spb-m-key" value="${r.key||''}" placeholder="POL"></td>
          <td><input class="spb-m-label" value="${r.label||''}" placeholder="POL"></td>
          <td><input type="number" step="0.01" class="spb-m-eur" value="${r.eur_m2 ?? ''}"></td>
          <td><button type="button" class="button spb-m-del">✕</button></td>
        </tr>
      `;
    }

    function sync(){
      const out = [];
      table.querySelectorAll('tbody tr').forEach(tr=>{
        out.push({
          key: tr.querySelector('.spb-m-key').value.trim(),
          label: tr.querySelector('.spb-m-label').value.trim(),
          eur_m2: tr.querySelector('.spb-m-eur').value,
        });
      });
      hidden.value = JSON.stringify(out);
    }

    function render(){
      const tbody = table.querySelector('tbody');
      tbody.innerHTML = rows.map(rowHTML).join('');

      tbody.querySelectorAll('.spb-m-del').forEach((btn, idx)=>{
        btn.addEventListener('click', ()=>{
          rows.splice(idx, 1);
          hidden.value = JSON.stringify(rows);
          render();
        });
      });

      tbody.addEventListener('input', sync);
      sync();
    }

    addBtn.addEventListener('click', ()=>{
      rows.push({key:'',label:'',eur_m2:''});
      hidden.value = JSON.stringify(rows);
      render();
    });

    render();
  }

  document.addEventListener('DOMContentLoaded', function(){
    renderDims();
    renderMaterials();
  });
})();
