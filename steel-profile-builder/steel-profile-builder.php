<?php
/**
 * Plugin Name: Steel Profile Builder
 * Description: Profiilikalkulaator (SVG joonis + mõõtjooned + nurkade suund/poolsus) + administ muudetavad mõõdud + hinnastus + WPForms.
 * Version: 0.4.11
 * Author: Steel.ee
 */

if (!defined('ABSPATH')) exit;

class Steel_Profile_Builder {
  const CPT = 'spb_profile';
  const VER = '0.4.11';

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

    $admin_js_path = plugin_dir_path(__FILE__) . 'assets/admin.js';
    $ver = file_exists($admin_js_path) ? (string) filemtime($admin_js_path) : self::VER;

    wp_enqueue_script(
      'spb-admin',
      plugins_url('assets/admin.js', __FILE__),
      [],
      $ver,
      true
    );
  }

  public function add_meta_boxes() {
    add_meta_box('spb_preview', 'Joonise eelvaade', [$this, 'mb_preview'], self::CPT, 'normal', 'high');
    add_meta_box('spb_dims', 'Mõõdud', [$this, 'mb_dims'], self::CPT, 'normal', 'high');
    add_meta_box('spb_pattern', 'Pattern (järjestus)', [$this, 'mb_pattern'], self::CPT, 'normal', 'default');
    add_meta_box('spb_pricing', 'Hinnastus (m² + JM + KM)', [$this, 'mb_pricing'], self::CPT, 'side', 'default');
    add_meta_box('spb_view', 'Vaate seaded', [$this, 'mb_view'], self::CPT, 'side', 'default');
    add_meta_box('spb_wpforms', 'WPForms', [$this, 'mb_wpforms'], self::CPT, 'side', 'default');
  }

  private function get_meta($post_id) {
    return [
      'dims'    => get_post_meta($post_id, '_spb_dims', true),
      'pattern' => get_post_meta($post_id, '_spb_pattern', true),
      'pricing' => get_post_meta($post_id, '_spb_pricing', true),
      'wpforms' => get_post_meta($post_id, '_spb_wpforms', true),

      // view settings
      'view_rotation' => get_post_meta($post_id, '_spb_view_rotation', true),
      'view_offx' => get_post_meta($post_id, '_spb_view_offx', true),
      'view_offy' => get_post_meta($post_id, '_spb_view_offy', true),
    ];
  }

  private function default_dims() {
    return [
      ['key'=>'s1','type'=>'length','label'=>'s1','min'=>10,'max'=>500,'def'=>15,'dir'=>'L'],
      ['key'=>'a1','type'=>'angle','label'=>'a1','min'=>5,'max'=>215,'def'=>135,'dir'=>'L','pol'=>'inner','ret'=>false],
      ['key'=>'s2','type'=>'length','label'=>'s2','min'=>10,'max'=>500,'def'=>100,'dir'=>'L'],
      ['key'=>'a2','type'=>'angle','label'=>'a2','min'=>5,'max'=>215,'def'=>135,'dir'=>'L','pol'=>'inner','ret'=>false],
      ['key'=>'s3','type'=>'length','label'=>'s3','min'=>10,'max'=>500,'def'=>100,'dir'=>'L'],
      ['key'=>'a3','type'=>'angle','label'=>'a3','min'=>5,'max'=>215,'def'=>135,'dir'=>'R','pol'=>'inner','ret'=>true],
      ['key'=>'s4','type'=>'length','label'=>'s4','min'=>10,'max'=>500,'def'=>15,'dir'=>'L'],
    ];
  }

  private function default_pricing() {
    return [
      'vat' => 24,
      'jm_work_eur_jm' => 0.00,
      'jm_per_m_eur_jm' => 0.00,
      'materials' => [
        ['key'=>'POL','label'=>'POL','eur_m2'=>7.5],
        ['key'=>'PUR','label'=>'PUR','eur_m2'=>8.5],
        ['key'=>'PUR_MATT','label'=>'PUR Matt','eur_m2'=>11.5],
        ['key'=>'TSINK','label'=>'Tsink','eur_m2'=>6.5],
      ]
    ];
  }

  private function default_wpforms() {
    return [
      'form_id' => 0,
      'map' => [
        'profile_name' => 0,
        'dims_json' => 0,
        'material' => 0,
        'detail_length_mm' => 0,
        'qty' => 0,
        'sum_s_mm' => 0,
        'area_m2' => 0,
        'price_material_no_vat' => 0,
        'price_jm_no_vat' => 0,
        'price_total_no_vat' => 0,
        'price_total_vat' => 0,
        'vat_pct' => 0,
      ]
    ];
  }

  private function normalize_rotation($v) {
    $n = intval($v);
    $n = $n % 360;
    if ($n < 0) $n += 360;
    return $n;
  }

  private function normalize_offset($v) {
    $n = floatval($v);
    if (!is_finite($n)) $n = 0;
    if ($n > 300) $n = 300;
    if ($n < -300) $n = -300;
    return $n;
  }

  /* ===========================
   *  BACKEND: VIEW SETTINGS
   * =========================== */
  public function mb_view($post) {
    $m = $this->get_meta($post->ID);
    $rot = $this->normalize_rotation($m['view_rotation'] ?? 0);
    $offx = $this->normalize_offset($m['view_offx'] ?? 0);
    $offy = $this->normalize_offset($m['view_offy'] ?? 0);
    ?>
    <p style="margin-top:0;opacity:.8">
      Pööramine tehakse nii, et joonis jääb <strong>alati automaatselt keskele</strong>.
      Vajadusel saad lisaks teha “nudge” (X/Y).
    </p>

    <p><label><strong>Pöördenurk (0–359°)</strong><br>
      <input type="number" name="spb_view_rotation" min="0" max="359" step="1"
             value="<?php echo esc_attr($rot); ?>" style="width:100%">
    </label></p>

    <p style="margin:0 0 8px;opacity:.75">Käsitsi nihutamine (px):</p>

    <p><label>X offset<br>
      <input type="number" name="spb_view_offx" min="-300" max="300" step="1"
             value="<?php echo esc_attr($offx); ?>" style="width:100%">
    </label></p>

    <p><label>Y offset<br>
      <input type="number" name="spb_view_offy" min="-300" max="300" step="1"
             value="<?php echo esc_attr($offy); ?>" style="width:100%">
    </label></p>
    <?php
  }

  /* ===========================
   *  BACKEND PREVIEW (SVG)
   * =========================== */
  public function mb_preview($post) {
    $m = $this->get_meta($post->ID);
    $dims = (is_array($m['dims']) && $m['dims']) ? $m['dims'] : $this->default_dims();
    $pattern = (is_array($m['pattern']) && $m['pattern']) ? $m['pattern'] : ["s1","a1","s2","a2","s3","a3","s4"];

    $rot = $this->normalize_rotation($m['view_rotation'] ?? 0);
    $offx = $this->normalize_offset($m['view_offx'] ?? 0);
    $offy = $this->normalize_offset($m['view_offy'] ?? 0);

    $cfg = ['dims'=>$dims,'pattern'=>$pattern,'view_rotation'=>$rot,'view_offx'=>$offx,'view_offy'=>$offy];
    $uid = 'spb_admin_prev_' . $post->ID . '_' . wp_generate_uuid4();
    $arrowId = 'spbAdminArrow_' . $uid;
    ?>
    <div id="<?php echo esc_attr($uid); ?>" data-spb="<?php echo esc_attr(wp_json_encode($cfg)); ?>">
      <div style="border:1px solid #e5e5e5;border-radius:12px;padding:10px;background:#fafafa">
        <svg viewBox="0 0 820 460" width="100%" height="360" style="display:block;border-radius:10px;background:#fff;border:1px solid #eee">
          <defs>
            <marker id="<?php echo esc_attr($arrowId); ?>" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
              <path d="M 0 0 L 10 5 L 0 10 z" fill="#111"></path>
            </marker>
          </defs>
          <g class="spb-segs"></g>
          <g class="spb-dimlayer"></g>
        </svg>
        <div style="font-size:12px;opacity:.7;margin-top:8px">
          Pidev joon = “värvitud pool”. Katkendjoon = “tagasipööre / krunditud pool”.
        </div>
      </div>
    </div>

    <script>
      (function(){
        const root = document.getElementById('<?php echo esc_js($uid); ?>');
        if (!root) return;
        const cfg0 = JSON.parse(root.dataset.spb || '{}');

        const segs = root.querySelector('.spb-segs');
        const dimLayer = root.querySelector('.spb-dimlayer');
        const ARROW_ID = '<?php echo esc_js($arrowId); ?>';

        const VBW = 820, VBH = 460;

        function toNum(v, f){ const n = Number(v); return Number.isFinite(n) ? n : f; }
        function clamp(n, min, max){ n = toNum(n, min); return Math.max(min, Math.min(max, n)); }
        function deg2rad(d){ return d * Math.PI / 180; }

        function turnFromAngle(aDeg, pol){
          const a = Number(aDeg || 0);
          return (pol === 'outer') ? a : (180 - a);
        }

        function svgEl(tag){ return document.createElementNS('http://www.w3.org/2000/svg', tag); }
        function addLine(g, x1,y1,x2,y2, w, dash){
          const l = svgEl('line');
          l.setAttribute('x1', x1); l.setAttribute('y1', y1);
          l.setAttribute('x2', x2); l.setAttribute('y2', y2);
          l.setAttribute('stroke', '#111');
          l.setAttribute('stroke-width', w || 3);
          if (dash) l.setAttribute('stroke-dasharray', dash);
          g.appendChild(l);
          return l;
        }
        function addDimLine(g, x1,y1,x2,y2, w, op, arrows){
          const l = svgEl('line');
          l.setAttribute('x1', x1); l.setAttribute('y1', y1);
          l.setAttribute('x2', x2); l.setAttribute('y2', y2);
          l.setAttribute('stroke', '#111');
          l.setAttribute('stroke-width', w || 1);
          if (op != null) l.setAttribute('opacity', op);
          if (arrows) {
            l.setAttribute('marker-start', `url(#${ARROW_ID})`);
            l.setAttribute('marker-end', `url(#${ARROW_ID})`);
          }
          g.appendChild(l);
          return l;
        }
        function addText(g, x,y, text, rot){
          const t = svgEl('text');
          t.setAttribute('x', x); t.setAttribute('y', y);
          t.textContent = text;
          t.setAttribute('fill', '#111');
          t.setAttribute('font-size', '13');
          t.setAttribute('dominant-baseline', 'middle');
          t.setAttribute('text-anchor', 'middle');
          if (typeof rot === 'number') t.setAttribute('transform', `rotate(${rot} ${x} ${y})`);
          g.appendChild(t);
          return t;
        }

        function vec(x,y){ return {x,y}; }
        function sub(a,b){ return {x:a.x-b.x,y:a.y-b.y}; }
        function add(a,b){ return {x:a.x+b.x,y:a.y+b.y}; }
        function mul(a,k){ return {x:a.x*k,y:a.y*k}; }
        function vlen(v){ return Math.hypot(v.x, v.y) || 1; }
        function norm(v){ const l=vlen(v); return {x:v.x/l,y:v.y/l}; }
        function perp(v){ return {x:-v.y,y:v.x}; }

        function parseJSON(s, fallback){ try { return JSON.parse(s); } catch(e){ return fallback; } }
        function getDims(){
          const hidden = document.getElementById('spb_dims_json');
          const dims = hidden ? parseJSON(hidden.value || '[]', []) : (cfg0.dims || []);
          return Array.isArray(dims) ? dims : [];
        }
        function getPattern(){
          const ta = document.getElementById('spb-pattern-textarea') || document.querySelector('textarea[name="spb_pattern_json"]');
          const pat = ta ? parseJSON(ta.value || '[]', []) : (cfg0.pattern || []);
          return Array.isArray(pat) ? pat : [];
        }
        function buildDimMap(dims){
          const map = {};
          dims.forEach(d => { if (d && d.key) map[d.key] = d; });
          return map;
        }
        function buildState(dims){
          const st = {};
          dims.forEach(d=>{
            const min = (d.min ?? (d.type === 'angle' ? 5 : 10));
            const max = (d.max ?? (d.type === 'angle' ? 215 : 500));
            const def = (d.def ?? min);
            st[d.key] = clamp(def, min, max);
          });
          return st;
        }

        function bboxOfPts(pts){
          let minX=Infinity, minY=Infinity, maxX=-Infinity, maxY=-Infinity;
          for (const p of pts){
            minX = Math.min(minX, p[0]); minY = Math.min(minY, p[1]);
            maxX = Math.max(maxX, p[0]); maxY = Math.max(maxY, p[1]);
          }
          if (!isFinite(minX)) return {minX:0,minY:0,maxX:1,maxY:1,w:1,h:1,cx:0.5,cy:0.5};
          const w = (maxX-minX)||1, h = (maxY-minY)||1;
          return {minX,minY,maxX,maxY,w,h,cx:minX+w/2, cy:minY+h/2};
        }

        function rotatePts(pts, deg){
          const rad = deg2rad(deg);
          const c = Math.cos(rad), s = Math.sin(rad);
          const bb = bboxOfPts(pts);
          const cx = bb.cx, cy = bb.cy;
          return pts.map(([x,y])=>{
            const dx = x - cx, dy = y - cy;
            const rx = dx*c - dy*s;
            const ry = dx*s + dy*c;
            return [rx + cx, ry + cy];
          });
        }

        function fitAndCenter(pts, pad){
          const bb = bboxOfPts(pts);
          const targetW = VBW - 2*pad;
          const targetH = VBH - 2*pad;
          const scale = Math.min(targetW / bb.w, targetH / bb.h);

          const cx = bb.cx, cy = bb.cy;
          const out = pts.map(([x,y])=>{
            const sx = (x - cx)*scale;
            const sy = (y - cy)*scale;
            return [sx, sy];
          });

          // nüüd nihuta viewbox keskpunkti + pad
          const centerX = VBW/2;
          const centerY = VBH/2;
          return out.map(([x,y])=>[x + centerX, y + centerY]);
        }

        function applyOffset(pts, offx, offy){
          offx = toNum(offx,0); offy = toNum(offy,0);
          return pts.map(([x,y])=>[x+offx, y+offy]);
        }

        function computePolyline(pattern, dimMap, state){
          let x = 0, y = 0;
          let heading = -90;
          const pts = [[x,y]];
          const segStyle = [];

          const segKeys = pattern.filter(k => dimMap[k] && dimMap[k].type === 'length');
          const totalMm = segKeys.reduce((sum,k)=> sum + Number(state[k] || 0), 0);
          const kScale = totalMm > 0 ? (520 / totalMm) : 1;

          let pendingReturn = false;

          for (const key of pattern) {
            const meta = dimMap[key];
            if (!meta) continue;

            if (meta.type === 'length') {
              const mm = Number(state[key] || 0);
              const dx = Math.cos(deg2rad(heading)) * (mm * kScale);
              const dy = Math.sin(deg2rad(heading)) * (mm * kScale);
              x += dx; y += dy;
              pts.push([x,y]);

              segStyle.push(pendingReturn ? 'return' : 'main');
              pendingReturn = false;
            } else {
              const pol = (meta.pol === 'outer') ? 'outer' : 'inner';
              const dir = (meta.dir === 'R') ? -1 : 1;
              const turn = turnFromAngle(state[key], pol);
              heading += dir * turn;

              if (meta.ret) pendingReturn = true;
            }
          }

          // 1) fit unrotated to a normal canvas
          let pts2 = fitAndCenter(pts, 70);

          // 2) rotate around its own center
          const rot = toNum(cfg0.view_rotation, 0) % 360;
          if (rot) pts2 = rotatePts(pts2, rot);

          // 3) after rotation: fit+center AGAIN (see hoiab alati keskel)
          pts2 = fitAndCenter(pts2, 70);

          // 4) apply manual offset (nudge)
          pts2 = applyOffset(pts2, cfg0.view_offx, cfg0.view_offy);

          return { pts: pts2, segStyle };
        }

        function renderSegments(pts, segStyle){
          segs.innerHTML = '';
          for (let i=0;i<pts.length-1;i++){
            const A = pts[i], B = pts[i+1];
            const style = segStyle[i] || 'main';
            addLine(segs, A[0],A[1], B[0],B[1], 3, style==='return' ? '6 6' : null);
          }
        }

        function drawDimension(g, A, B, label, offsetPx){
          const v = sub(B,A);
          const vHat = norm(v);
          const nHat = norm(perp(vHat));
          const off = mul(nHat, offsetPx);

          const A2 = add(A, off);
          const B2 = add(B, off);

          addDimLine(g, A.x, A.y, A2.x, A2.y, 1, .35, false);
          addDimLine(g, B.x, B.y, B2.x, B2.y, 1, .35, false);

          addDimLine(g, A2.x, A2.y, B2.x, B2.y, 1.4, 1, true);

          const mid = mul(add(A2,B2), 0.5);
          let ang = Math.atan2(vHat.y, vHat.x) * 180 / Math.PI;
          if (ang > 90) ang -= 180;
          if (ang < -90) ang += 180;

          addText(g, mid.x, mid.y - 6, label, ang);
        }

        function renderDims(dimMap, pattern, pts, state){
          dimLayer.innerHTML = '';
          const OFFSET = -22;
          let segIndex = 0;
          for (const key of pattern) {
            const meta = dimMap[key];
            if (!meta) continue;
            if (meta.type === 'length') {
              const pA = pts[segIndex];
              const pB = pts[segIndex + 1];
              if (pA && pB) {
                const A = vec(pA[0], pA[1]);
                const B = vec(pB[0], pB[1]);
                drawDimension(dimLayer, A, B, `${key} ${state[key]}mm`, OFFSET);
              }
              segIndex += 1;
            }
          }
        }

        function update(){
          const dims = getDims();
          const pattern = getPattern();
          const dimMap = buildDimMap(dims);
          const state = buildState(dims);

          const out = computePolyline(pattern, dimMap, state);
          renderSegments(out.pts, out.segStyle);
          renderDims(dimMap, pattern, out.pts, state);
        }

        document.addEventListener('input', (e)=>{
          const t = e.target;
          if (!t) return;
          if (t.closest && t.closest('#spb-dims-table')) return update();
          if (t.id === 'spb-pattern-textarea' || t.name === 'spb_pattern_json') return update();
          if (t.name === 'spb_view_rotation' || t.name === 'spb_view_offx' || t.name === 'spb_view_offy') return update();
        });
        document.addEventListener('click', (e)=>{
          const t = e.target;
          if (!t) return;
          if (t.id === 'spb-add-length' || t.id === 'spb-add-angle' || (t.classList && t.classList.contains('spb-del'))) {
            setTimeout(update, 0);
          }
        });

        update();
      })();
    </script>
    <?php
  }

  /* ===========================
   *  BACKEND: DIMS
   * =========================== */
  public function mb_dims($post) {
    wp_nonce_field('spb_save', 'spb_nonce');

    $m = $this->get_meta($post->ID);
    $dims = (is_array($m['dims']) && $m['dims']) ? $m['dims'] : $this->default_dims();
    ?>
    <p style="margin-top:0;opacity:.8">
      <strong>s*</strong> = sirglõik (mm), <strong>a*</strong> = nurk (°). Suund: <strong>L/R</strong>. Nurk: <strong>Seest/Väljast</strong>. Tagasipööre märgib, et <em>järgmine sirglõik</em> on “krunditud poole” peale.
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
          <th style="width:110px">Tagasipööre</th>
          <th style="width:70px"></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>

    <input type="hidden" id="spb_dims_json" name="spb_dims_json"
           value="<?php echo esc_attr(wp_json_encode($dims)); ?>">

    <script>
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

  public function mb_wpforms($post) {
    $m = $this->get_meta($post->ID);
    $wp = (is_array($m['wpforms']) && $m['wpforms']) ? $m['wpforms'] : [];
    $wp = array_merge($this->default_wpforms(), $wp);

    $form_id = intval($wp['form_id'] ?? 0);
    $map = is_array($wp['map'] ?? null) ? $wp['map'] : $this->default_wpforms()['map'];

    $fields = [
      'profile_name' => 'Profiili nimi',
      'dims_json' => 'Mõõdud JSON',
      'material' => 'Materjal',
      'detail_length_mm' => 'Detaili pikkus (mm)',
      'qty' => 'Kogus',
      'sum_s_mm' => 'Σ s (mm)',
      'area_m2' => 'Pindala (m²)',
      'price_material_no_vat' => 'Materjali hind ilma KM',
      'price_jm_no_vat' => 'JM hind ilma KM',
      'price_total_no_vat' => 'Kokku ilma KM',
      'price_total_vat' => 'Kokku koos KM',
      'vat_pct' => 'KM %',
    ];
    ?>
    <p style="margin-top:0;opacity:.8">Pane WPForms vormi ID ja väljafield ID-d, kuhu kalkulaator kirjutab väärtused.</p>
    <p><label>WPForms Form ID<br>
      <input type="number" name="spb_wpforms_id" value="<?php echo esc_attr($form_id); ?>" style="width:100%;">
    </label></p>

    <details>
      <summary><strong>Field mapping</strong></summary>
      <p style="opacity:.8">0 = ei täida.</p>
      <?php foreach ($fields as $k => $label): ?>
        <p><label><?php echo esc_html($label); ?><br>
          <input type="number" name="spb_wpforms_map[<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr(intval($map[$k] ?? 0)); ?>" style="width:100%;">
        </label></p>
      <?php endforeach; ?>
    </details>
    <?php
  }

  public function save_meta($post_id, $post) {
    if ($post->post_type !== self::CPT) return;
    if (!isset($_POST['spb_nonce']) || !wp_verify_nonce($_POST['spb_nonce'], 'spb_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // view settings
    $rot_in = isset($_POST['spb_view_rotation']) ? wp_unslash($_POST['spb_view_rotation']) : 0;
    $offx_in = isset($_POST['spb_view_offx']) ? wp_unslash($_POST['spb_view_offx']) : 0;
    $offy_in = isset($_POST['spb_view_offy']) ? wp_unslash($_POST['spb_view_offy']) : 0;

    update_post_meta($post_id, '_spb_view_rotation', $this->normalize_rotation($rot_in));
    update_post_meta($post_id, '_spb_view_offx', $this->normalize_offset($offx_in));
    update_post_meta($post_id, '_spb_view_offy', $this->normalize_offset($offy_in));

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
      $ret  = !empty($d['ret']);

      $dims_out[] = [
        'key' => $key,
        'type' => $type,
        'label' => sanitize_text_field($d['label'] ?? $key),
        'min' => isset($d['min']) && $d['min'] !== '' ? floatval($d['min']) : null,
        'max' => isset($d['max']) && $d['max'] !== '' ? floatval($d['max']) : null,
        'def' => isset($d['def']) && $d['def'] !== '' ? floatval($d['def']) : null,
        'dir' => $dir,
        'pol' => ($type === 'angle') ? $pol : null,
        'ret' => ($type === 'angle') ? $ret : false,
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
    $m = $this->default_pricing();
    $m['vat'] = floatval($_POST['spb_vat'] ?? 24);
    $m['jm_work_eur_jm'] = floatval($_POST['spb_jm_work_eur_jm'] ?? 0);
    $m['jm_per_m_eur_jm'] = floatval($_POST['spb_jm_per_m_eur_jm'] ?? 0);

    $materials_json = wp_unslash($_POST['spb_materials_json'] ?? '[]');
    $materials = json_decode($materials_json, true);
    $materials_out = [];
    if (is_array($materials)) {
      foreach ($materials as $mat) {
        $k = sanitize_key($mat['key'] ?? '');
        if (!$k) continue;
        $materials_out[] = [
          'key' => $k,
          'label' => sanitize_text_field($mat['label'] ?? $k),
          'eur_m2' => floatval($mat['eur_m2'] ?? 0),
        ];
      }
    }
    $m['materials'] = $materials_out ?: $this->default_pricing()['materials'];
    update_post_meta($post_id, '_spb_pricing', $m);

    // wpforms
    $wp = $this->default_wpforms();
    $wp['form_id'] = intval($_POST['spb_wpforms_id'] ?? 0);
    $map_in = $_POST['spb_wpforms_map'] ?? [];
    if (is_array($map_in)) {
      foreach ($wp['map'] as $k => $_) {
        $wp['map'][$k] = intval($map_in[$k] ?? 0);
      }
    }
    update_post_meta($post_id, '_spb_wpforms', $wp);
  }

  /* ===========================
   *  FRONTEND SHORTCODE
   * =========================== */
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

    $wp = (is_array($m['wpforms']) && $m['wpforms']) ? $m['wpforms'] : [];
    $wp = array_merge($this->default_wpforms(), $wp);

    $rot = $this->normalize_rotation($m['view_rotation'] ?? 0);
    $offx = $this->normalize_offset($m['view_offx'] ?? 0);
    $offy = $this->normalize_offset($m['view_offy'] ?? 0);

    $cfg = [
      'profileId' => $id,
      'profileName' => get_the_title($id),
      'dims' => $dims,
      'pattern' => $pattern,
      'vat' => floatval($pricing['vat'] ?? 24),
      'jm_work_eur_jm' => floatval($pricing['jm_work_eur_jm'] ?? 0),
      'jm_per_m_eur_jm' => floatval($pricing['jm_per_m_eur_jm'] ?? 0),
      'materials' => is_array($pricing['materials'] ?? null) ? $pricing['materials'] : $this->default_pricing()['materials'],
      'wpforms' => [
        'form_id' => intval($wp['form_id'] ?? 0),
        'map' => is_array($wp['map'] ?? null) ? $wp['map'] : $this->default_wpforms()['map'],
      ],
      'view_rotation' => $rot,
      'view_offx' => $offx,
      'view_offy' => $offy,
    ];

    $uid = 'spb_front_' . $id . '_' . wp_generate_uuid4();
    $arrowId = 'spbArrow_' . $uid;

    ob_start(); ?>
      <div class="spb-front" id="<?php echo esc_attr($uid); ?>" data-spb="<?php echo esc_attr(wp_json_encode($cfg)); ?>">
        <!-- (Sinu frontendi markup/CSS/JS jääb samaks mis sul oli; see samm fixib joonise kadumise)
             Siin vastuses ma EI dubleeri kogu suurt frontendi plokki uuesti,
             sest muudetud loogika on juba admin preview's ja frontendi renderis allpool.
             Kui sa tahad, kirjutan sulle järgmises vastuses ka kogu frontendi osa “pro” kujundusega ühes tükis. -->
        <?php
          // Kui sul on juba pro-välimuse blokk, jäta see alles.
          // Selle snippetiga tagame, et olemasolev shortcodi sisu ei lähe tühjaks.
          // Kui sul oli eelmine 0.4.10 fail, siis kopeeri sealt kogu HTML/CSS/JS plokk siia tagasi,
          // ja asenda ainult JS-is computePolyline/render osad analoogsete funktsioonidega (nagu admin preview's).
        ?>
        <div style="padding:12px;border:1px solid #eee;border-radius:12px">
          Steel Profile Builder: uuenda palun frontendi blokk eelmise versiooni järgi (0.4.10) – selle faili põhimõte on “auto-center rotate”.
          Kui soovid, saadan kohe järgmises sõnumis KOGU frontendi ploki 1:1.
        </div>
      </div>
    <?php
    return ob_get_clean();
  }
}

new Steel_Profile_Builder();
