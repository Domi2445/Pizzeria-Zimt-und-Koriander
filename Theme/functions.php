<?php
/**
 * Theme Functions – Quick-View mit Größe im Checkbox-Look, Live-Preis und Sticky-CTA
 */

if (!function_exists('zimt_enqueue_assets')) {
  function zimt_enqueue_assets() {
    wp_enqueue_style('zimt-style', get_stylesheet_uri());
  }
}
add_action('wp_enqueue_scripts','zimt_enqueue_assets');

if (!function_exists('zimt_custom_theme_setup')) {
  function zimt_custom_theme_setup(){ add_theme_support('custom-logo'); add_theme_support('woocommerce'); }
}
add_action('after_setup_theme','zimt_custom_theme_setup');

/* Optional: eigenes Single-Template */
add_filter('template_include', function($template){
  if (is_singular('product')) { $f=locate_template('single-product-modern.php'); if ($f) return $f; }
  return $template;
});

/* Shop/Kategorie: benötigte Woo Skripte laden */
add_action('wp_enqueue_scripts', function(){
  if (function_exists('is_woocommerce') && (is_woocommerce()||is_shop()||is_product_taxonomy())) {
    wp_enqueue_script('jquery');
    wp_enqueue_script('wc-add-to-cart-variation');
    wp_enqueue_script('wc-cart-fragments');
  }
},20);

/* AJAX Quickview */
add_action('wc_ajax_gf_quickview','gf_ajax_quickview');
add_action('wc_ajax_nopriv_gf_quickview','gf_ajax_quickview');

function gf_ajax_quickview(){
  $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
  if (!$product_id && !empty($_GET['product_url'])) { $url=esc_url_raw($_GET['product_url']); $product_id=url_to_postid($url); }
  if (!$product_id) { status_header(400); echo '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Produkt nicht gefunden.</p></div>'; wp_die(); }
  $product = wc_get_product($product_id);
  if (!$product) { status_header(404); echo '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Produktdaten nicht verfügbar.</p></div>'; wp_die(); }

  ob_start(); gf_render_quickview_inner($product_id); $html=ob_get_clean();
  header('Content-Type: text/html; charset='.get_bloginfo('charset')); echo $html; wp_die();
}

function gf_render_quickview_inner($product_id){
  $new = wc_get_product($product_id); if(!$new){ echo '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Produktansicht nicht verfügbar.</p></div>'; return; }
  global $product,$post; $prev_p=$product??null; $prev_post=$post??null;
  $product=$new; $post=get_post($product_id); if($post) setup_postdata($post);

  $img_html = has_post_thumbnail($product_id)? get_the_post_thumbnail($product_id,'large',['class'=>'gf-modal-image']) : '';
  $allergen_terms = wc_get_product_terms($product_id,'pa_allergene',['fields'=>'names']);
  $allergen_html = '';
  if ($allergen_terms) foreach($allergen_terms as $t){ $allergen_html.='<span class="gf-chip">'.esc_html($t).'</span>'; }

  $desc=$product->get_short_description(); if(!$desc) $desc=get_post_field('post_content',$product_id); $desc=wpautop(wp_kses_post($desc));
  $base_price = (float)$product->get_price();

  ?>
  <div class="gf-modal-inner">
    <button class="gf-modal-close" aria-label="Schließen">×</button>

    <?php if ($img_html): ?><div class="gf-modal-image-wrap"><?php echo $img_html; ?></div><?php endif; ?>

    <h2 class="gf-modal-title"><?php echo esc_html(get_the_title($product_id)); ?></h2>
    <div class="gf-modal-price" data-base-price="<?php echo esc_attr($base_price); ?>"><?php echo wp_kses_post($product->get_price_html()); ?></div>

    <?php if (!empty($allergen_html)): ?>
      <div class="gf-modal-allergens"><strong>Allergene:</strong><div class="gf-chip-row"><?php echo $allergen_html; ?></div></div>
    <?php endif; ?>

    <?php if (!empty($desc)): ?><div class="gf-modal-desc"><?php echo $desc; ?></div><?php endif; ?>

    <div class="gf-modal-cart">
      <div class="gf-addtocart-wrap">
        <?php woocommerce_template_single_add_to_cart(); ?>
      </div>
    </div>
  </div>
  <?php

  if ($prev_post){ $post=$prev_post; setup_postdata($post); } else { wp_reset_postdata(); }
  $product=$prev_p;
}

/* Modal + Styles + JS */
add_action('wp_footer', function(){
  if (!(function_exists('is_woocommerce') && (is_woocommerce()||is_shop()||is_product_taxonomy()))) return;
  ?>
  <style>
    .gf-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;z-index:9999}
    .gf-modal-backdrop.open{display:block}
    .gf-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:10000}
    .gf-modal.open{display:flex}
    .gf-modal-box{background:#fff;width:95%;max-width:900px;max-height:90vh;border-radius:16px;overflow:auto;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    .gf-modal-inner{padding:1rem 1rem 4.5rem;position:relative} /* Platz für Sticky CTA */
    .gf-modal-close{position:absolute;top:8px;right:10px;font-size:28px;width:36px;height:36px;border:0;background:transparent;color:#888;cursor:pointer}
    .gf-modal-image{width:100%;height:280px;object-fit:cover;border-radius:12px}
    .gf-modal-image-wrap{margin-bottom:.75rem}
    .gf-modal-title{font-size:1.6rem;font-weight:700;color:#ff8000;margin:.25rem 0}
    .gf-modal-price .price{background:#ff8000;color:#fff;padding:.35rem .6rem;border-radius:6px;font-weight:700;display:inline-block}
    .gf-chip-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
    .gf-chip{display:inline-block;background:#fff2e6;color:#a23c16;border:1px solid #ffd4b0;padding:3px 8px;border-radius:999px;font-size:.85rem}
    .gf-modal-desc{color:#444;line-height:1.55;margin:.6rem 0 1rem}
    /* Size chooser (Checkbox-Look, technisch Radio) */
    .gf-size-chooser{display:flex;flex-direction:column;gap:8px;margin:.6rem 0 1rem}
    .gf-size-item{display:flex;align-items:center;gap:10px;padding:.55rem .7rem;border:1px solid #e9e9e9;border-radius:8px;cursor:pointer}
    .gf-size-item input{width:18px;height:18px;appearance:none;border:1.5px solid #bbb;border-radius:4px;display:inline-block;position:relative}
    .gf-size-item input:checked{border-color:#ff8000;background:#ff8000}
    .gf-size-item input:checked::after{content:"";position:absolute;top:2px;left:5px;width:5px;height:10px;border:2px solid #fff;border-top:0;border-left:0;transform:rotate(45deg)}
    .gf-size-item .gf-size-label{font-weight:600}
    .gf-size-item .gf-size-diff{margin-left:auto;color:#a23c16}
    /* Sticky CTA */
    .gf-sticky-cta{position:sticky;bottom:0;left:0;right:0;background:#ffffffeb;border-top:1px solid #eee;backdrop-filter:saturate(180%) blur(6px);padding:.75rem 1rem;display:flex;gap:12px;align-items:center;justify-content:space-between;z-index:1}
    .gf-sticky-cta .gf-total{font-weight:700;color:#111;font-size:1.05rem}
    .gf-sticky-cta .gf-btn{background:#ff8000;color:#fff;font-weight:700;border:0;border-radius:10px;padding:.8rem 1.2rem;cursor:pointer}
    .gf-sticky-cta .gf-btn:hover{background:#e56f00}
    /* Addon-Reihen */
    .gf-addon-row{transition:background .15s ease}
    .gf-addon-row:hover{background:#fafafa}
    .gf-addon-row input[type="checkbox"]{width:18px;height:18px}
  </style>

  <div class="gf-modal-backdrop" id="gfModalBackdrop" hidden></div>
  <div class="gf-modal" id="gfModal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="gf-modal-box">
      <div id="gfModalContent"><!-- AJAX-Inhalt --></div>
    </div>
  </div>

  <script>
  (function(){
    const modal=document.getElementById('gfModal');
    const backdrop=document.getElementById('gfModalBackdrop');
    const content=document.getElementById('gfModalContent');

    function openModal(){ backdrop.removeAttribute('hidden'); backdrop.classList.add('open'); modal.classList.add('open'); modal.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
    function closeModal(){ modal.classList.remove('open'); backdrop.classList.remove('open'); modal.setAttribute('aria-hidden','true'); document.body.style.overflow=''; setTimeout(()=>{content.innerHTML=''; backdrop.setAttribute('hidden','');},150); }

    function getProductIdFromLink(a){ const p=a.closest('.product'); if(!p) return 0; if(p.id && p.id.indexOf('post-')===0){const n=parseInt(p.id.replace('post-',''),10); if(!isNaN(n)) return n;} const cls=(p.className||'').split(/\s+/); for(const c of cls){ if(c.indexOf('post-')===0){ const n=parseInt(c.replace('post-',''),10); if(!isNaN(n)) return n; } } const d=p.getAttribute('data-product_id')||p.getAttribute('data-pid'); return d?parseInt(d,10)||0:0; }

    // Preisformatierung
    function formatPrice(n){ n = (Math.round(n*100)/100).toFixed(2); return n.replace('.', ',') + '€'; }

    // Sticky CTA hinzufügen
    function ensureStickyCTA(form){
      if (content.querySelector('.gf-sticky-cta')) return;
      const bar=document.createElement('div');
      bar.className='gf-sticky-cta';
      bar.innerHTML='<div class="gf-total">Gesamt: <span class="gf-total-val">0,00€</span></div><button type="button" class="gf-btn">In den Warenkorb</button>';
      content.querySelector('.gf-modal-inner').appendChild(bar);
      bar.querySelector('.gf-btn').addEventListener('click', ()=>{
        // Safety: Variation gewählt?
        const varId = form.querySelector('input[name="variation_id"]');
        const sizeSel = form.querySelector('select[name="attribute_pa_size"]');
        if (varId && !varId.value && sizeSel && !sizeSel.value) {
          const first = sizeSel.querySelector('option[value]:not([value=""])');
          if (first) {
            sizeSel.value = first.value;
            sizeSel.dispatchEvent(new Event('change',{bubbles:true}));
            setTimeout(()=>{ form.requestSubmit ? form.requestSubmit() : form.submit(); }, 80);
            return;
          }
        }
        form.requestSubmit ? form.requestSubmit() : form.submit();
      });
    }

    // Größe-Chooser (Checkbox-Look) bauen
    function buildSizeChooser(form){
      const sel = form.querySelector('select[name="attribute_pa_size"]');
      if (!sel || sel.dataset.gfBuilt) return;
      sel.dataset.gfBuilt = '1';

      const wrap = document.createElement('div');
      wrap.className = 'gf-size-chooser';

      // Optional: Preis-Deltas aus data-product_variations berechnen
      let deltas = {};
      try {
        const vf = form.closest('.variations_form') || form;
        const json = vf.getAttribute('data-product_variations');
        if (json) {
          const vars = JSON.parse(json);
          // Nimm die preisliche Differenz relativ zur günstigsten Variation
          let minPrice = Infinity;
          vars.forEach(v => { if (v.display_price != null) minPrice = Math.min(minPrice, parseFloat(v.display_price)); });
          vars.forEach(v => {
            const slug = (v.attributes && v.attributes['attribute_pa_size']) ? v.attributes['attribute_pa_size'] : '';
            if (!slug) return;
            const p = (v.display_price != null) ? parseFloat(v.display_price) : minPrice;
            deltas[slug] = (p - minPrice);
          });
        }
      } catch(e){}

      const opts = Array.from(sel.querySelectorAll('option')).filter(o=>o.value);
      opts.forEach(o=>{
        const lbl = document.createElement('label');
        lbl.className='gf-size-item';
        lbl.setAttribute('data-size-slug', o.value);
        const diff = (deltas[o.value] && Math.abs(deltas[o.value])>0.0001) ? ('+'+formatPrice(deltas[o.value])) : '';
        lbl.innerHTML = '<input type="radio" name="gf_size_choice"><span class="gf-size-label"></span><span class="gf-size-diff"></span>';
        lbl.querySelector('.gf-size-label').textContent = o.textContent.trim();
        lbl.querySelector('.gf-size-diff').textContent = diff;
        wrap.appendChild(lbl);
      });

      // Select verstecken und UI davor setzen
      sel.style.display='none';
      sel.insertAdjacentElement('beforebegin', wrap);

      function markFromSelect(){
        const val = sel.value || '';
        wrap.querySelectorAll('.gf-size-item').forEach(el=>{
          const inp = el.querySelector('input[type="radio"]');
          const on = (el.getAttribute('data-size-slug') === val);
          inp.checked = on;
        });
      }
      markFromSelect();

      wrap.addEventListener('click', function(e){
        const item = e.target.closest('.gf-size-item'); if (!item) return;
        const slug = item.getAttribute('data-size-slug');
        if (slug && sel.value !== slug){
          sel.value = slug;
          sel.dispatchEvent(new Event('change',{bubbles:true}));
        }
      });

      sel.addEventListener('change', markFromSelect);
    }

    // Variationsdaten lesen (fallback ohne jQuery)
    function findMatchingVariation(form){
      try{
        const vf = form.closest('.variations_form') || form;
        const json = vf.getAttribute('data-product_variations');
        if (!json) return null;
        const vars = JSON.parse(json);
        const sel = form.querySelector('select[name="attribute_pa_size"]');
        const size = sel ? (sel.value || '') : '';
        for (const v of vars){
          const s = v.attributes && v.attributes['attribute_pa_size'] ? v.attributes['attribute_pa_size'] : '';
          if (s === size) return v;
          // Fuzzy: falls Slug leicht abweicht (Bindestriche etc.)
          if (size && s && (s.replace(/-/g,'') === size.replace(/-/g,''))) return v;
        }
        return null;
      }catch(e){ return null; }
    }

    let currentBase = 0;

    function readBasePriceFallback(){
      const el = content.querySelector('.gf-modal-price');
      if (!el) return 0;
      const raw = parseFloat(el.getAttribute('data-base-price') || '0') || 0;
      const priceEl = content.querySelector('.woocommerce-variation-price .price, .gf-modal-price .price');
      if (priceEl){
        const txt = priceEl.textContent || '';
        const num = parseFloat(txt.replace(/[^\d,.-]/g,'').replace('.','').replace(',','.'));
        if (!isNaN(num) && num>0) return num;
      }
      return raw;
    }

    function sumAddons(){
      let sum=0;
      content.querySelectorAll('.gf-addons .gf-addon-check').forEach(chk=>{
        if (chk.checked){
          const price = parseFloat(chk.getAttribute('data-price') || '0') || 0;
          let qty = 1;
          const row = chk.closest('.gf-addon-row');
          if (row){
            const q = row.querySelector('.gf-addon-qty');
            if (q) qty = Math.max(1, parseInt(q.value || '1', 10));
          }
          sum += price * qty;
        }
      });
      return sum;
    }
    function getProductQty(form){
      const q = form.querySelector('input.qty');
      return Math.max(1, parseInt((q && q.value) ? q.value : '1', 10));
    }
    function refreshTotals(){
      const form = content.querySelector('form.cart'); if (!form) return;
      const extras = sumAddons();
      const qty = getProductQty(form);
      const total = (currentBase + extras) * qty;
      const target = content.querySelector('.gf-sticky-cta .gf-total-val');
      if (target) target.textContent = formatPrice(total);
    }

    function refreshAddonBlocks(){
      const select = content.querySelector('select[name="attribute_pa_size"]');
      const slug = (select && select.value) ? select.value : '';
      const blocks = content.querySelectorAll('.gf-addon-size-block');
      let shown=false;
      blocks.forEach(b=>{
        const base=b.getAttribute('data-size-slug')||'';
        const alts=(b.getAttribute('data-alt-slugs')||'').split('|').filter(Boolean);
        const match=(base===slug)||(alts.indexOf(slug)!==-1)||(!slug&&!shown);
        b.style.display = match ? 'block' : 'none';
        if (match) shown=true;
      });
    }

    // Quickview öffnen + laden
    document.addEventListener('click', function(e){
      const link=e.target.closest('.products a, a.woocommerce-LoopProduct-link, a.gf-product-link');
      if (!link) return;
      if (link.classList.contains('add_to_cart_button') || e.button===1 || e.metaKey || e.ctrlKey || link.target==='_blank') return;

      const pid=getProductIdFromLink(link);
      if (!pid) return;

      e.preventDefault();
      openModal();
      content.innerHTML='<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Bitte warten…</p></div>';

      let qvUrl = '<?php echo esc_js(home_url('/?wc-ajax=gf_quickview')); ?>';
      if (window.wc_add_to_cart_params && wc_add_to_cart_params.wc_ajax_url) qvUrl = wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%','gf_quickview');
      const url=new URL(qvUrl); url.searchParams.set('product_id', String(pid));

      fetch(url.toString(),{credentials:'same-origin'}).then(r=>r.text()).then(html=>{
        content.innerHTML=html;
        const form = content.querySelector('form.cart');
        ensureStickyCTA(form);

        // Variations-Form klassisch initialisieren (falls jQuery/Woo da ist)
        if (window.jQuery){
          const $form = jQuery(content).find('.variations_form');
          $form.each(function(){
            const $vf=jQuery(this);
            $vf.wc_variation_form();
          });
        }

        // Größe-Chooser sicher bauen (unabhängig von jQuery)
        buildSizeChooser(form);

        // Default-Größe setzen, wenn leer
        (function ensureSize(){
          const sel = form.querySelector('select[name="attribute_pa_size"]');
          if (sel && !sel.value) {
            const first = sel.querySelector('option[value]:not([value=""])');
            if (first) { sel.value = first.value; sel.dispatchEvent(new Event('change',{bubbles:true})); }
          }
        })();

        // Basispreis aus Variation-Daten (ohne jQuery)
        (function updateBaseFromVariation(){
          const match = findMatchingVariation(form);
          if (match && (match.display_price!=null || match.display_regular_price!=null)){
            currentBase = parseFloat(match.display_price ?? match.display_regular_price) || 0;
          } else {
            currentBase = readBasePriceFallback();
          }
        })();

        refreshAddonBlocks();
        refreshTotals();

        // Listeners
        content.addEventListener('change', function(ev){
          const t = ev.target; if (!t) return;

          if (t.name==='attribute_pa_size' || t.id==='pa_size') {
            // Wenn Woo/jQuery aktiv ist, feuert found_variation → ansonsten selbst berechnen
            setTimeout(()=>{
              const match = findMatchingVariation(form);
              if (match && (match.display_price!=null || match.display_regular_price!=null)){
                currentBase = parseFloat(match.display_price ?? match.display_regular_price) || 0;
              } else {
                currentBase = readBasePriceFallback();
              }
              refreshAddonBlocks();
              refreshTotals();
            }, 20);
          }
          if (t.classList.contains('gf-addon-check') || t.classList.contains('gf-addon-qty') || t.name==='quantity') {
            refreshTotals();
          }
        });

        // Formular-Submit → AJAX add_to_cart; sorge dafür, dass variation_id gesetzt ist
        form.addEventListener('submit', function(e){
          e.preventDefault();

          const sel = form.querySelector('select[name="attribute_pa_size"]');
          const varId = form.querySelector('input[name="variation_id"]');
          // Sicherstellen, dass eine Größe gewählt ist
          if (sel && !sel.value) {
            const first = sel.querySelector('option[value]:not([value=""])');
            if (first) { sel.value=first.value; sel.dispatchEvent(new Event('change',{bubbles:true})); }
          }
          // Falls Woo nicht automatisch variation_id gesetzt hat → selber setzen
          if (varId && !varId.value) {
            const match = findMatchingVariation(form);
            if (match && match.variation_id) varId.value = match.variation_id;
          }

          const fd = new FormData(form);
          if (!fd.has('product_id') && fd.get('add-to-cart')) fd.append('product_id', fd.get('add-to-cart'));
          if (varId && varId.value) fd.set('variation_id', varId.value);

          let ajaxUrl='<?php echo esc_js(home_url('/?wc-ajax=add_to_cart')); ?>';
          if (window.wc_add_to_cart_params && wc_add_to_cart_params.wc_ajax_url) ajaxUrl = wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%','add_to_cart');

          fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'})
            .then(r=>r.json())
            .then(res=>{
              if (!res){ alert('Unerwartete Antwort.'); return; }
              if (res.error){
                if (res.product_url){ alert('Bitte wähle zuerst eine Größe.'); return; }
                alert('Artikel konnte nicht hinzugefügt werden.'); return;
              }
              if (res.fragments){
                Object.keys(res.fragments).forEach(function(sel){
                  const html=res.fragments[sel];
                  const doc=new DOMParser().parseFromString(html,'text/html');
                  const newEl=doc.body.firstElementChild;
                  const oldEl=document.querySelector(sel);
                  if (oldEl && newEl) oldEl.replaceWith(newEl);
                });
              }
              closeModal();
            })
            .catch(()=>alert('Fehler beim Hinzufügen in den Warenkorb.'));
        });

      }).catch(()=>{ content.innerHTML='<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Fehler beim Laden.</p></div>'; });
    });

    document.addEventListener('click', function(e){
      if (e.target.classList.contains('gf-modal-close') || e.target===backdrop){ e.preventDefault(); closeModal(); }
    });
    document.addEventListener('keydown', function(e){ if (e.key==='Escape' && modal.classList.contains('open')) closeModal(); });
  })();
  </script>
  <?php
},50);