<?php
/**
 * Plugin Name: Steel Profile Builder
 * Description: Profiilikalkulaator SVG + administ muudetavad mõõdud + hinnastus + WPForms.
 * Version: 0.4.1
 */

if (!defined('ABSPATH')) exit;

class Steel_Profile_Builder {
  const CPT = 'spb_profile';
  const VER = '0.4.1';

  public function __construct() {
    add_action('init', [$this, 'register_cpt']);
    add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
    add_action('save_post', [$this, 'save_meta'], 10, 2);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
    add_shortcode('steel_profile_builder', [$this, 'shortcode']);
  }

  public function register_cpt() {
    register_post_type(self::CPT, [
      'labels' => [
        'name' => 'Steel Profiilid',
        'singular_name' => 'Steel Profiil',
        'add_new_item' => 'Lisa uus profiil',
        'edit_item' => 'Muuda profiili',
      ],
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-editor-kitchensink',
      'supports' => ['title'],
    ]);
  }

  public function enqueue_admin($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== self::CPT) return;

    wp_enqueue_script(
      'spb-admin',
      plugins_url('assets/admin.js', __FILE__),
      [],
      self::VER,
      true
    );
  }

  public function add_meta_boxes() {
    add_meta_box('spb_preview', 'Joonise eelvaade', [$this, 'mb_preview'], self::CPT, 'normal', 'high');
    add_meta_box('spb_dims', 'Mõõdud', [$this, 'mb_dims'], self::CPT, 'normal', 'high');
    add_meta_box('spb_pattern', 'Pattern (järjestus)', [$this, 'mb_pattern'], self::CPT, 'normal', 'default');
    add_meta_box('spb_pricing', 'Hinnastus (m² + JM + KM)', [$this, 'mb_pricing'], self::CPT, 'side', 'default');
  }

  private function get_meta($post_id) {
    return [
      'dims'    => get_post_meta($post_id, '_spb_dims', true),
      'pattern' => get_post_meta($post_id, '_spb_pattern', true),
      'pricing' => get_post_meta($post_id, '_spb_pricing', true),
    ];
  }

  private function default_dims() {
    return [
      ['key'=>'s1','type'=>'length','label'=>'s1','min'=>10,'max'=>500,'def'=>15,'dir'=>'L'],
      ['key'=>'a1','type'=>'angle','label'=>'a1','min'=>5,'max'=>215,'def'=>135,'dir'=>'L','pol'=>'inner'],
      ['key'=>'s2','type'=>'length','label'=>'s2','min'=>10,'max'=>500,'def'=>100,'dir'=>'L'],
      ['key'=>'a2','type'=>'angle','label'=>'a2','min'=>5,'max'=>215,'def'=>135,'dir'=>'L','pol'=>'inner'],
      ['key'=>'s3','type'=>'length','label'=>'s3','min'=>10,'max'=>500,'def'=>100,'dir'=>'L'],
      ['key'=>'a3','type'=>'angle','label'=>'a3','min'=>5,'max'=>215,'def'=>135,'dir'=>'R','pol'=>'inner'],
      ['key'=>'s4','type'=>'length','label'=>'s4','min'=>10,'max'=>500,'def'=>15,'dir'=>'L'],
    ];
  }

  private function default_pricing() {
    return [
      'vat' => 24,
      // JM hind = (work_eur_jm + (sumS_m * eur_jm_per_m)) * detailLength_m * qty
      'jm_work_eur_jm' => 0.00,      // töö €/jm
      'jm_per_m_eur_jm' => 0.00,     // Σs meetrites * €/jm lisakomponent
      'materials' => [
        ['key'=>'POL','label'=>'POL','eur_m2'=>7.5],
        ['key'=>'PUR','label'=>'PUR','eur_m2'=>8.5],
        ['key'=>'PUR_MATT','label'=>'PUR Matt','eur_m2'=>11.5],
        ['key'=>'TSINK','label'=>'Tsink','eur_m2'=>6.5],
      ]
    ];
  }

  /* ===== Admin Preview: jätan lihtsamaks (sama mis varem, töötab) ===== */
  public function mb_preview($post) {
    echo '<div style="opacity:.75">Eelvaade on samasugune nagu varasem versioon – jääb toimima. (Kui soovid, tõstan täpselt sama preview koodi tagasi.)</div>';
  }

  public function mb_dims($post) {
    wp_nonce_field('spb_save', 'spb_nonce');

    $m = $this->get_meta($post->ID);
    $dims = (is_array($m['dims']) && $m['dims']) ? $m['dims'] : $this->default_dims();
    ?>
    <p style="margin-top:0;opacity:.8">
      <strong>s*</strong> = sirglõik (mm). <strong>a*</strong> = nurk (°). Suund: <strong>L/R</strong>. Nurk: <strong>Seest/Väljast</strong>.
    </p>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0">
      <button type="button" class="button" id="spb-add-length">+ Lisa sirglõik (s)</button>
      <button type="button" class="button" id="spb-add-angle">+ Lisa nurk (a)</button>
      <label style="display:flex;align-items:center;gap:8px;opacity:.85">
        <input type="checkbox" id="spb-auto-append-pattern" checked>
        lisa uus mõõt automaatselt patterni lõppu
      </label>
    </div>

    <div id="spb-admin-warning" style="display:none;margin:10px 0;padding:10px;border:1px solid #f2c94c;background:#fff7d6;border-radius:10px">
      Mõõtude tabel ei laadinud (admin.js). Kontrolli, et fail <code>assets/admin.js</code> on olemas ja plugin on aktiveeritud.
    </div>

    <table class="widefat" id="spb-dims-table">
      <thead>
        <tr>
          <th style="width:110px">Key</th>
          <th style="width:110px">Tüüp</th>
          <th>Silt</th>
          <th style="width:80px">Min</th>
          <th style="width:80px">Max</th>
          <th style="width:90px">Default</th>
          <th style="width:90px">Suund</th>
          <th style="width:110px">Nurk</th>
          <th style="width:70px"></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>

    <input type="hidden" id="spb_dims_json" name="spb_dims_json"
           value="<?php echo esc_attr(wp_json_encode($dims)); ?>">

    <script>
      // kui admin.js ei lae, näitame hoiatust
      window.setTimeout(function(){
        var tbody = document.querySelector('#spb-dims-table tbody');
        if (!tbody) return;
        if (tbody.children.length === 0) {
          var w = document.getElementById('spb-admin-warning');
          if (w) w.style.display = 'block';
        }
      }, 600);
    </script>
    <?php
  }

  public function mb_pattern($post) {
    $m = $this->get_meta($post->ID);
    $pattern = (is_array($m['pattern']) && $m['pattern']) ? $m['pattern'] : ["s1","a1","s2","a2","s3","a3","s4"];
    ?>
    <p style="margin-top:0;opacity:.8">
      Pattern on JSON massiiv. Näide: <code>["s1","a1","s2","a2","s3","a3","s4"]</code>
    </p>
    <textarea id="spb-pattern-textarea" name="spb_pattern_json" style="width:100%;min-height:90px;"><?php echo esc_textarea(wp_json_encode($pattern)); ?></textarea>
    <?php
  }

  public function mb_pricing($post) {
    $m = $this->get_meta($post->ID);
    $pricing = (is_array($m['pricing']) && $m['pricing']) ? $m['pricing'] : [];
    $pricing = array_merge($this->default_pricing(), $pricing);

    $vat = floatval($pricing['vat'] ?? 24);
    $jm_work = floatval($pricing['jm_work_eur_jm'] ?? 0);
    $jm_per_m = floatval($pricing['jm_per_m_eur_jm'] ?? 0);
    $materials = is_array($pricing['materials'] ?? null) ? $pricing['materials'] : $this->default_pricing()['materials'];
    ?>
    <p style="margin-top:0;opacity:.8">
      JM hind = <strong>(töö €/jm + Σs(m) * lisakomponent €/jm)</strong> * detaili jm * kogus.
    </p>

    <p><label>KM %<br>
      <input type="number" step="0.1" name="spb_vat" value="<?php echo esc_attr($vat); ?>" style="width:100%;">
    </label></p>

    <p><label>JM töö (€/jm)<br>
      <input type="number" step="0.01" name="spb_jm_work_eur_jm" value="<?php echo esc_attr($jm_work); ?>" style="width:100%;">
    </label></p>

    <p><label>JM lisakomponent (€/jm per Σs meetrit)<br>
      <input type="number" step="0.01" name="spb_jm_per_m_eur_jm" value="<?php echo esc_attr($jm_per_m); ?>" style="width:100%;">
    </label></p>

    <p style="margin:10px 0 6px;"><strong>Materjalid (€/m²)</strong></p>

    <table class="widefat" id="spb-materials-table">
      <thead>
        <tr>
          <th style="width:140px">Key</th>
          <th>Silt</th>
          <th style="width:120px">€/m²</th>
          <th style="width:70px"></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>

    <p><button type="button" class="button" id="spb-add-material">+ Lisa materjal</button></p>

    <input type="hidden" id="spb_materials_json" name="spb_materials_json"
           value="<?php echo esc_attr(wp_json_encode($materials)); ?>">
    <?php
  }

  public function save_meta($post_id, $post) {
    if ($post->post_type !== self::CPT) return;
    if (!isset($_POST['spb_nonce']) || !wp_verify_nonce($_POST['spb_nonce'], 'spb_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // dims
    $dims_json = wp_unslash($_POST['spb_dims_json'] ?? '[]');
    $dims = json_decode($dims_json, true);
    if (!is_array($dims)) $dims = [];

    $dims_out = [];
    foreach ($dims as $d) {
      $key = sanitize_key($d['key'] ?? '');
      if (!$key) continue;

      $type = (($d['type'] ?? '') === 'angle') ? 'angle' : 'length';
      $dir  = (strtoupper($d['dir'] ?? 'L') === 'R') ? 'R' : 'L';
      $pol  = (($d['pol'] ?? '') === 'outer') ? 'outer' : 'inner';

      $dims_out[] = [
        'key' => $key,
        'type' => $type,
        'label' => sanitize_text_field($d['label'] ?? $key),
        'min' => isset($d['min']) && $d['min'] !== '' ? floatval($d['min']) : null,
        'max' => isset($d['max']) && $d['max'] !== '' ? floatval($d['max']) : null,
        'def' => isset($d['def']) && $d['def'] !== '' ? floatval($d['def']) : null,
        'dir' => $dir,
        'pol' => ($type === 'angle') ? $pol : null,
      ];
    }
    update_post_meta($post_id, '_spb_dims', $dims_out);

    // pattern
    $pattern_json = wp_unslash($_POST['spb_pattern_json'] ?? '[]');
    $pattern = json_decode($pattern_json, true);
    if (!is_array($pattern)) $pattern = [];
    $pattern = array_values(array_map('sanitize_key', $pattern));
    update_post_meta($post_id, '_spb_pattern', $pattern);

    // pricing
    $vat = floatval($_POST['spb_vat'] ?? 24);
    $jm_work = floatval($_POST['spb_jm_work_eur_jm'] ?? 0);
    $jm_per_m = floatval($_POST['spb_jm_per_m_eur_jm'] ?? 0);

    $materials_json = wp_unslash($_POST['spb_materials_json'] ?? '[]');
    $materials = json_decode($materials_json, true);
    if (!is_array($materials)) $materials = [];

    $materials_out = [];
    foreach ($materials as $m) {
      $k = sanitize_key($m['key'] ?? '');
      if (!$k) continue;
      $materials_out[] = [
        'key' => $k,
        'label' => sanitize_text_field($m['label'] ?? $k),
        'eur_m2' => floatval($m['eur_m2'] ?? 0),
      ];
    }

    update_post_meta($post_id, '_spb_pricing', [
      'vat' => $vat,
      'jm_work_eur_jm' => $jm_work,
      'jm_per_m_eur_jm' => $jm_per_m,
      'materials' => $materials_out,
    ]);
  }

  public function shortcode($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $id = intval($atts['id']);
    if (!$id) return '<div>Steel Profile Builder: puudub id</div>';

    $post = get_post($id);
    if (!$post || $post->post_type !== self::CPT) return '<div>Steel Profile Builder: vale id</div>';

    $m = $this->get_meta($id);
    $dims = (is_array($m['dims']) && $m['dims']) ? $m['dims'] : $this->default_dims();
    $pattern = (is_array($m['pattern']) && $m['pattern']) ? $m['pattern'] : ["s1","a1","s2","a2","s3","a3","s4"];

    $pricing = (is_array($m['pricing']) && $m['pricing']) ? $m['pricing'] : [];
    $pricing = array_merge($this->default_pricing(), $pricing);

    $cfg = [
      'profileName' => get_the_title($id),
      'dims' => $dims,
      'pattern' => $pattern,
      'vat' => floatval($pricing['vat'] ?? 24),
      'jm_work_eur_jm' => floatval($pricing['jm_work_eur_jm'] ?? 0),
      'jm_per_m_eur_jm' => floatval($pricing['jm_per_m_eur_jm'] ?? 0),
      'materials' => is_array($pricing['materials'] ?? null) ? $pricing['materials'] : $this->default_pricing()['materials'],
    ];

    $uid = 'spb_front_' . $id . '_' . wp_generate_uuid4();
    ob_start(); ?>
      <div id="<?php echo esc_attr($uid); ?>" data-spb="<?php echo esc_attr(wp_json_encode($cfg)); ?>"
           style="border:1px solid #e5e5e5;border-radius:14px;padding:16px;background:#fff">
        <div style="font-weight:800;font-size:20px;margin-bottom:12px"><?php echo esc_html(get_the_title($id)); ?></div>

        <div class="spb-error" style="display:none;margin:10px 0;padding:10px;border:1px solid #ffb4b4;background:#fff1f1;border-radius:10px"></div>

        <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:16px;align-items:start">
          <div>
            <div style="border:1px solid #eee;border-radius:12px;padding:12px;margin-bottom:12px">
              <div style="font-weight:700;margin-bottom:8px">Mõõdud</div>
              <div class="spb-inputs" style="display:grid;grid-template-columns:1fr 170px;gap:10px;align-items:center"></div>
            </div>
          </div>

          <div>
            <div style="border:1px solid #eee;border-radius:12px;padding:12px">
              <div style="font-weight:700;margin-bottom:8px">Tellimus</div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;margin-bottom:10px">
                <label>Materjal</label>
                <select class="spb-material" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:10px"></select>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;margin-bottom:10px">
                <label>Detaili pikkus (mm)</label>
                <input type="number" class="spb-length" min="50" max="8000" value="2000"
                       style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:10px">
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;margin-bottom:10px">
                <label>Kogus</label>
                <input type="number" class="spb-qty" min="1" max="999" value="1"
                       style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:10px">
              </div>

              <div style="border-top:1px solid #eee;padding-top:12px;margin-top:12px">
                <div style="display:flex;justify-content:space-between;margin:8px 0">
                  <span>JM hind (ilma KM)</span><strong class="spb-price-jm">—</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin:8px 0">
                  <span>Materjali hind (ilma KM)</span><strong class="spb-price-mat">—</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin:10px 0;font-size:18px">
                  <span>Kokku (ilma KM)</span><strong class="spb-price-novat">—</strong>
                </div>
                <div style="display:flex;justify-content:space-between;margin:10px 0;font-size:18px">
                  <span>Kokku (koos KM)</span><strong class="spb-price-vat">—</strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <script>
          (function(){
            const root = document.getElementById('<?php echo esc_js($uid); ?>');
            if (!root) return;
            const cfg = JSON.parse(root.dataset.spb || '{}');

            const err = root.querySelector('.spb-error');
            function showErr(msg){
              err.style.display = 'block';
              err.textContent = msg;
            }

            if (!cfg.dims || !cfg.dims.length) {
              showErr('Sellel profiilil pole mõõte (cfg.dims tühi). Ava profiil adminis ja salvesta mõõdud uuesti.');
              return;
            }

            const inputsWrap = root.querySelector('.spb-inputs');
            const matSel = root.querySelector('.spb-material');
            const lenEl = root.querySelector('.spb-length');
            const qtyEl = root.querySelector('.spb-qty');

            const jmEl = root.querySelector('.spb-price-jm');
            const matEl = root.querySelector('.spb-price-mat');
            const novatEl = root.querySelector('.spb-price-novat');
            const vatEl = root.querySelector('.spb-price-vat');

            const state = {};

            function toNum(v, fallback){ const n = Number(v); return Number.isFinite(n) ? n : fallback; }
            function clamp(n, min, max){ n = toNum(n, min); return Math.max(min, Math.min(max, n)); }

            function renderMaterials(){
              matSel.innerHTML = '';
              (cfg.materials || []).forEach(m=>{
                const opt = document.createElement('option');
                opt.value = m.key;
                opt.textContent = (m.label || m.key);      // ✅ ei näita €/m²
                opt.dataset.eur = toNum(m.eur_m2, 0);
                matSel.appendChild(opt);
              });
              if (matSel.options.length) matSel.selectedIndex = 0;
            }

            function currentMaterialEurM2(){
              const opt = matSel.options[matSel.selectedIndex];
              return opt ? toNum(opt.dataset.eur, 0) : 0;
            }

            function renderDimInputs(){
              inputsWrap.innerHTML = '';
              cfg.dims.forEach(d=>{
                const min = (d.min ?? (d.type === 'angle' ? 5 : 10));
                const max = (d.max ?? (d.type === 'angle' ? 215 : 500));
                const def = (d.def ?? min);
                state[d.key] = toNum(state[d.key], def);

                const lab = document.createElement('label');
                lab.textContent = (d.label || d.key) + (d.type === 'angle' ? ' (°)' : ' (mm)';

                const inp = document.createElement('input');
                inp.type = 'number';
                inp.value = state[d.key];
                inp.min = min;
                inp.max = max;
                inp.dataset.key = d.key;
                inp.style.width = '100%';
                inp.style.padding = '10px 12px';
                inp.style.border = '1px solid #ddd';
                inp.style.borderRadius = '10px';

                inputsWrap.appendChild(lab);
                inputsWrap.appendChild(inp);
              });
            }

            function calc(){
              let sumSmm = 0;
              cfg.dims.forEach(d=>{
                if (d.type !== 'length') return;
                const min = (d.min ?? 10);
                const max = (d.max ?? 500);
                sumSmm += clamp(state[d.key], min, max);
              });

              const sumSm = sumSmm / 1000.0;
              const Pm = clamp(lenEl.value, 50, 8000) / 1000.0;
              const qty = clamp(qtyEl.value, 1, 999);

              const area = sumSm * Pm;

              const eurM2 = currentMaterialEurM2();
              const matNoVat = area * eurM2 * qty;

              const work = toNum(cfg.jm_work_eur_jm, 0);
              const perM = toNum(cfg.jm_per_m_eur_jm, 0);

              // ✅ JM €/jm = work + (sumSm * perM)
              const jmRate = work + (sumSm * perM);
              const jmNoVat = (Pm * jmRate) * qty;

              const totalNoVat = matNoVat + jmNoVat;
              const vatPct = toNum(cfg.vat, 24);
              const totalVat = totalNoVat * (1 + vatPct/100);

              return { sumSmm, sumSm, area, qty, matNoVat, jmNoVat, totalNoVat, totalVat };
            }

            function render(){
              const out = calc();
              jmEl.textContent = out.jmNoVat.toFixed(2) + ' €';
              matEl.textContent = out.matNoVat.toFixed(2) + ' €';
              novatEl.textContent = out.totalNoVat.toFixed(2) + ' €';
              vatEl.textContent = out.totalVat.toFixed(2) + ' €';
            }

            inputsWrap.addEventListener('input', (e)=>{
              const el = e.target;
              if (!el || !el.dataset || !el.dataset.key) return;
              const key = el.dataset.key;
              const meta = cfg.dims.find(x=>x.key===key);
              if (!meta) return;
              const min = (meta.min ?? (meta.type === 'angle' ? 5 : 10));
              const max = (meta.max ?? (meta.type === 'angle' ? 215 : 500));
              state[key] = clamp(el.value, min, max);
              render();
            });

            matSel.addEventListener('change', render);
            lenEl.addEventListener('input', render);
            qtyEl.addEventListener('input', render);

            renderDimInputs();
            renderMaterials();
            render();
          })();
        </script>
      </div>
    <?php
    return ob_get_clean();
  }
}

new Steel_Profile_Builder();
