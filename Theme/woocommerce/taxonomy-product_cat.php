<?php
/**
 * WooCommerce Kategorie-Template im Lieferando-Stil
 * Öffnet Produkte in einem Quick-View-Modal (bleibt auf der Übersicht) und
 * unterstützt Variations-/Addons-Formular inkl. AJAX "In den Warenkorb".
 */
get_header();

// Optional: Globales $post absichern
global $post;
if (!is_object($post)) {
  $post = new stdClass();
}
$post->post_author       = 1;
$post->post_date         = current_time('mysql');
$post->post_date_gmt     = current_time('mysql', 1);
$post->post_modified     = current_time('mysql');
$post->post_modified_gmt = current_time('mysql', 1);
?>

<style>
.shop-lieferando-container {
  max-width: 1200px;
  margin: 3rem auto;
  padding: 0 1rem;
}
.shop-lieferando-title {
  text-align: center;
  font-size: 2.5rem;
  margin-bottom: 2rem;
  color: #ff8000;
  font-family: 'Montserrat', sans-serif;
  font-weight: 700;
}
.shop-lieferando-categories {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.75rem;
  margin-bottom: 2rem;
}
.shop-lieferando-categories a {
  background-color: #ff8000;
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 20px;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.95rem;
  transition: background 0.2s ease;
}
.shop-lieferando-categories a:hover,
.shop-lieferando-categories a.active {
  background-color: #e56f00;
}
.shop-lieferando-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 2rem;
}
.shop-lieferando-item {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  overflow: hidden;
  transition: transform 0.2s ease;
  display: flex;
  flex-direction: column;
}
.shop-lieferando-item:hover {
  transform: scale(1.02);
}
.shop-lieferando-image {
  width: 100%;
  height: 180px;
  object-fit: cover;
  background-color: #f0f0f0;
}
.shop-lieferando-content {
  padding: 1rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.shop-lieferando-title2 {
  font-size: 1.2rem;
  font-weight: 600;
  color: #333;
  margin-bottom: 0.5rem;
}
.shop-lieferando-desc {
  font-size: 0.95rem;
  color: #666;
  margin-bottom: 0.75rem;
}
.shop-lieferando-price {
  background-color: #ff8000;
  color: white;
  font-weight: bold;
  font-size: 1rem;
  padding: 0.4rem 0.6rem;
  border-radius: 6px;
  display: inline-block;
  margin-bottom: 0.8rem;
}
.shop-lieferando-addtocart .button {
  background-color: #ff8000;
  color: white;
  border: none;
  font-weight: 600;
  font-size: 1rem;
  border-radius: 6px;
  padding: 0.6rem 1rem;
  cursor: pointer;
  width: 100%;
  transition: background 0.2s ease;
}
.shop-lieferando-addtocart .button:hover {
  background-color: #e56f00;
}

/* Modal (Lieferando-Stil) */
.gf-modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.55); display: none; z-index: 9999;
}
.gf-modal-backdrop.open { display: block; }
.gf-modal {
  position: fixed; inset: 0; display: none; align-items: center; justify-content: center; z-index: 10000;
}
.gf-modal.open { display: flex; }
.gf-modal-box {
  background: #fff; width: 95%; max-width: 900px; max-height: 90vh; border-radius: 16px;
  overflow: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.25);
}
.gf-modal-inner { padding: 1rem 1rem 1.25rem; position: relative; }
.gf-modal-close {
  position: absolute; top: 8px; right: 10px; font-size: 28px; width: 36px; height: 36px;
  border: 0; background: transparent; color: #888; cursor: pointer;
}
.gf-modal-image { width: 100%; height: 280px; object-fit: cover; border-radius: 12px; }
.gf-modal-image-wrap { margin-bottom: 0.75rem; }
.gf-modal-title { font-size: 1.6rem; font-weight: 700; color: #ff8000; text-align: left; margin: 0.25rem 0; }
.gf-modal-price { display: inline-block; background: #ff8000; color:#fff; padding: 0.35rem 0.6rem; border-radius: 6px; font-weight: 700; margin: 0.4rem 0 0.8rem; }
.gf-modal-allergens { margin: 0.5rem 0 0.25rem; }
.gf-chip-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.gf-chip {
  display: inline-block; background: #fff2e6; color: #a23c16; border: 1px solid #ffd4b0;
  padding: 3px 8px; border-radius: 999px; font-size: 0.85rem;
}
.gf-modal-desc { color: #444; line-height: 1.55; margin: 0.6rem 0 1rem; }
.gf-modal-cart .button { background-color: #ff8000; color:#fff; font-weight: 600; border-radius: 8px; padding: 0.7rem 1rem; }
.gf-modal-cart .button:hover { background-color: #e56f00; }

@media (max-width: 600px) {
  .shop-lieferando-title { font-size: 1.6rem; }
  .gf-modal-image { height: 220px; }
}
</style>

<div class="shop-lieferando-container">
  <h1 class="shop-lieferando-title"><?php single_term_title(); ?></h1>

  <?php
  // Kategorie-Auswahl
  $terms = get_terms('product_cat', ['hide_empty' => true]);
  if ($terms && !is_wp_error($terms)) {
    echo '<div class="shop-lieferando-categories">';
    foreach ($terms as $term) {
      $active = (is_tax('product_cat') && get_queried_object()->term_id === $term->term_id) ? 'active' : '';
      echo '<a href="' . esc_url(get_term_link($term)) . '" class="' . esc_attr($active) . '">' . esc_html($term->name) . '</a>';
    }
    echo '</div>';
  }

  // Produktabfrage (support für Pagination & sauberes Product-Objekt)
  $qo = get_queried_object();
  $paged = max( 1, ( get_query_var('paged') ) ? get_query_var('paged') : ( get_query_var('page') ? get_query_var('page') : 1 ) );

  $base_args = [
    'post_type'      => 'product',
    'posts_per_page' => 30,
    'post_status'    => 'publish',
    'paged'          => $paged,
  ];

  // Wenn wir eine Kategorie haben, filtern wir danach, sonst alle Produkte anzeigen
  if ( $qo && isset( $qo->term_id ) && $qo->term_id ) {
    $base_args['tax_query'] = [[
      'taxonomy' => 'product_cat',
      'field'    => 'term_id',
      'terms'    => $qo->term_id,
    ]];
  }

  $loop = new WP_Query( $base_args );
  if ($loop->have_posts()) :
    echo '<div class="shop-lieferando-grid" id="gfGrid">';
    while ($loop->have_posts()) : $loop->the_post();
      // Stelle sicher, dass $product für Template-Funktionen verfügbar ist
      global $product;
      $product = wc_get_product( get_the_ID() );
      ?>
      <div class="shop-lieferando-item product" id="post-<?php the_ID(); ?>" data-pid="<?php echo esc_attr(get_the_ID()); ?>">
        <a href="<?php the_permalink(); ?>" class="gf-product-link">
          <?php if (has_post_thumbnail()) {
            the_post_thumbnail('medium', ['class' => 'shop-lieferando-image']);
          } else {
            echo '<img src="' . esc_url(wc_placeholder_img_src()) . '" class="shop-lieferando-image" alt="Kein Bild">';
          } ?>
        </a>
        <div class="shop-lieferando-content">
          <div>
            <div class="shop-lieferando-title2"><?php the_title(); ?></div>
            <div class="shop-lieferando-desc">
              <?php
              $desc = $product ? ($product->get_short_description() ?: get_the_excerpt()) : get_the_excerpt();
              echo esc_html(wp_trim_words($desc, 14, '...'));
              ?>
            </div>
            <div class="shop-lieferando-price"><?php echo wp_kses_post($product ? $product->get_price_html() : ''); ?></div>
          </div>
          <div class="shop-lieferando-addtocart">
            <?php woocommerce_template_loop_add_to_cart(); ?>
          </div>
        </div>
      </div>
      <?php
    endwhile;
    echo '</div>';
  else :
    echo '<p>Keine Produkte gefunden.</p>';
  endif;
  wp_reset_postdata();
  ?>
</div>

<!-- Modal Struktur -->
<div class="gf-modal-backdrop" id="gfModalBackdrop" hidden></div>
<div class="gf-modal" id="gfModal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="gf-modal-box">
    <div id="gfModalContent"><!-- AJAX-Inhalt hier --></div>
  </div>
</div>

<script>
(function(){
  const grid      = document.getElementById('gfGrid');
  const modal     = document.getElementById('gfModal');
  const backdrop  = document.getElementById('gfModalBackdrop');
  const content   = document.getElementById('gfModalContent');

  function openModal() {
    backdrop.removeAttribute('hidden');
    backdrop.classList.add('open');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    modal.classList.remove('open');
    backdrop.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    setTimeout(()=>{ content.innerHTML=''; backdrop.setAttribute('hidden',''); }, 150);
  }

  function getPidFrom(el) {
    const item = el.closest('.shop-lieferando-item');
    if (!item) return 0;
    const dataPid = item.getAttribute('data-pid');
    if (dataPid) return parseInt(dataPid, 10) || 0;
    if (item.id && item.id.indexOf('post-') === 0) {
      const n = parseInt(item.id.replace('post-',''), 10);
      if (!isNaN(n)) return n;
    }
    const cls = item.className.split(/\s+/);
    for (const c of cls) {
      if (c.indexOf('post-') === 0) {
        const n = parseInt(c.replace('post-',''), 10);
        if (!isNaN(n)) return n;
      }
    }
    return 0;
  }

  // Bind AJAX Add-to-Cart für das Formular im Modal
  function bindModalCartAjax() {
    const form = content.querySelector('form.cart');
    if (!form) return;

    form.addEventListener('submit', function(e){
      e.preventDefault();

      const fd = new FormData(form);

      // Woo erwartet product_id bei wc-ajax=add_to_cart
      if (!fd.has('product_id') && fd.get('add-to-cart')) {
        fd.append('product_id', fd.get('add-to-cart'));
      }

      // Variation-ID muss bei variablen Produkten gesetzt sein
      const variationIdInput = form.querySelector('input[name="variation_id"]');
      if (variationIdInput && variationIdInput.value) {
        fd.set('variation_id', variationIdInput.value);
      }

      // AJAX-Endpoint ermitteln
      let ajaxUrl = '<?php echo esc_js( home_url('/?wc-ajax=add_to_cart') ); ?>';
      if (window.wc_add_to_cart_params && wc_add_to_cart_params.wc_ajax_url) {
        ajaxUrl = wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%','add_to_cart');
      }

      fetch(ajaxUrl, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(res => {
        if (!res) { alert('Unerwartete Antwort.'); return; }

        if (res.error) {
          if (res.product_url) {
            // Woo leitet normalerweise auf Produktseite – hier nicht gewünscht.
            // Zeige Hinweis statt Redirect.
            alert('Bitte wähle zuerst alle benötigten Optionen.');
            return;
          }
          alert('Artikel konnte nicht hinzugefügt werden.');
          return;
        }

        // WooCommerce Fragmente aktualisieren (Mini-Cart etc.)
        if (res.fragments) {
          for (const sel in res.fragments) {
            if (!Object.prototype.hasOwnProperty.call(res.fragments, sel)) continue;
            const html = res.fragments[sel];
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newEl = doc.body.firstElementChild;
            const oldEl = document.querySelector(sel);
            if (oldEl && newEl) oldEl.replaceWith(newEl);
          }
        }

        // Feedback und Modal schließen
        closeModal();
      })
      .catch(() => {
        alert('Fehler beim Hinzufügen in den Warenkorb.');
      });
    });
  }

  // Produktklicks abfangen (nicht Add-to-Cart / nicht neuer Tab)
  document.addEventListener('click', function(e){
    const link = e.target.closest('.gf-product-link');
    if (!link || !grid || !grid.contains(link)) return;
    if (e.button === 1 || e.metaKey || e.ctrlKey || link.target === '_blank') return;

    const pid = getPidFrom(link);
    if (!pid) return; // normal navigieren, wenn keine ID

    e.preventDefault();
    openModal();
    content.innerHTML = '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Bitte warten…</p></div>';

    const url = new URL('<?php echo esc_url( home_url('/?wc-ajax=gf_quickview') ); ?>');
    url.searchParams.set('product_id', String(pid));

    fetch(url.toString(), { credentials: 'same-origin' })
      .then(r => r.text())
      .then(html => {
        content.innerHTML = html;

        // Variations-Form initialisieren, damit Preise/Verfügbarkeit funktionieren
        if (window.jQuery) {
          jQuery(content).find('.variations_form').each(function(){
            jQuery(this).wc_variation_form();
          }).trigger('check_variations');
        }

        // Add-to-Cart im Modal auf AJAX binden
        bindModalCartAjax();
      })
      .catch(() => {
        content.innerHTML = '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Fehler beim Laden.</p></div>';
      });
  });

  // Close (X oder Overlay)
  document.addEventListener('click', function(e){
    if (e.target.classList.contains('gf-modal-close') || e.target === backdrop) {
      e.preventDefault();
      closeModal();
    }
  });

  // ESC schließt
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
  });
})();
</script>

<?php get_footer(); ?>