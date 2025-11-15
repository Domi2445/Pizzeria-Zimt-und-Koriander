<?php
/**
 * WooCommerce Shopseite im Lieferando-Stil mit Quick-View Modal (keine Navigation auf Einzelprodukt)
 */
get_header();
?>

<style>
.shop-lieferando-container { max-width: 1200px; margin: 3rem auto; padding: 0 1rem; display: flex; gap: 2rem; }
.shop-lieferando-main { flex: 3; }
.shop-lieferando-sidebar { flex: 1; background: #f9f9f9; padding: 1rem; border-radius: 12px; box-shadow: 0 0 8px rgba(0,0,0,0.05); }
.shop-lieferando-title { text-align: center; font-size: 2.5rem; margin-bottom: 2rem; color: #ff8000; font-family: 'Montserrat', sans-serif; font-weight: 700; }
.shop-lieferando-categories { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.75rem; margin-bottom: 2rem; }
.shop-lieferando-categories a { background-color: #ff8000; color: white; padding: 0.5rem 1rem; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 0.95rem; transition: background 0.2s ease; }
.shop-lieferando-categories a:hover, .shop-lieferando-categories a.active { background-color: #e56f00; }
.shop-lieferando-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 2rem; }
.shop-lieferando-cart-empty { text-align: center; color: #666; font-size: 0.95rem; }

/* Modal */
.gf-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.55); display: none; z-index: 9999; }
.gf-modal-backdrop.open { display: block; }
.gf-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; z-index: 10000; }
.gf-modal.open { display: flex; }
.gf-modal-box { background: #fff; width: 95%; max-width: 900px; max-height: 90vh; border-radius: 16px; overflow: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
.gf-modal-inner { padding: 1rem 1rem 1.25rem; position: relative; }
.gf-modal-close { position: absolute; top: 8px; right: 10px; font-size: 28px; width: 36px; height: 36px; border: 0; background: transparent; color: #888; cursor: pointer; }
.gf-modal-image { width: 100%; height: 280px; object-fit: cover; border-radius: 12px; }
.gf-modal-image-wrap { margin-bottom: 0.75rem; }
.gf-modal-title { font-size: 1.6rem; font-weight: 700; color: #ff8000; text-align: left; margin: 0.25rem 0; }
.gf-modal-price { display: inline-block; background: #ff8000; color:#fff; padding: 0.35rem 0.6rem; border-radius: 6px; font-weight: 700; margin: 0.4rem 0 0.8rem; }
.gf-modal-allergens { margin: 0.5rem 0 0.25rem; }
.gf-chip-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.gf-chip { display: inline-block; background: #fff2e6; color: #a23c16; border: 1px solid #ffd4b0; padding: 3px 8px; border-radius: 999px; font-size: 0.85rem; }
.gf-modal-desc { color: #444; line-height: 1.55; margin: 0.6rem 0 1rem; }
.gf-modal-cart .button { background-color: #ff8000; color:#fff; font-weight: 600; border-radius: 8px; padding: 0.7rem 1rem; }
.gf-modal-cart .button:hover { background-color: #e56f00; }

@media (max-width: 800px) {
  .shop-lieferando-container { flex-direction: column; }
  .gf-modal-image { height: 220px; }
}
</style>

<div class="shop-lieferando-container">
  <div class="shop-lieferando-main">
    <h1 class="shop-lieferando-title">
      <?php
      if (is_shop()) {
        echo 'Unsere Speisekarte';
      } elseif (is_product_category()) {
        single_term_title();
      } else {
        echo 'Produkte';
      }
      ?>
    </h1>

    <?php
    // Kategorie-Tabs
    $terms = get_terms('product_cat', ['hide_empty' => true]);
    if ($terms && !is_wp_error($terms)) {
      echo '<div class="shop-lieferando-categories">';
      foreach ($terms as $term) {
        $is_active = (is_tax('product_cat') && get_queried_object()->term_id === $term->term_id) ? 'active' : '';
        echo '<a href="' . get_term_link($term) . '" class="' . $is_active . '">' . esc_html($term->name) . '</a>';
      }
      echo '</div>';
    }

    // Produkt-Loop
    if (woocommerce_product_loop()) {
      echo '<div class="shop-lieferando-grid products">';
      woocommerce_product_loop_start();

      while (have_posts()) {
        the_post();
        wc_get_template_part('content', 'product');
      }

      woocommerce_product_loop_end();
      echo '</div>';
    } else {
      echo '<p>Keine Produkte gefunden.</p>';
    }
    ?>
  </div>

  <div class="shop-lieferando-sidebar">
    <h3>🛒 Warenkorb</h3>
    <div class="shop-lieferando-cart-empty">
      Füge einige Artikel aus der Speisekarte hinzu und beginne dein Essen.
    </div>
  </div>
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
  const modal = document.getElementById('gfModal');
  const backdrop = document.getElementById('gfModalBackdrop');
  const content = document.getElementById('gfModalContent');

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

  // Produkt-ID aus nächstem .product Element ermitteln (id="post-123" oder Klasse "post-123")
  function getProductIdFromLink(linkEl){
    const productEl = linkEl.closest('.product');
    if (!productEl) return 0;
    if (productEl.id && productEl.id.indexOf('post-') === 0) {
      const n = parseInt(productEl.id.replace('post-',''), 10);
      if (!isNaN(n)) return n;
    }
    const cls = productEl.className.split(/\s+/);
    for (const c of cls) {
      if (c.indexOf('post-') === 0) {
        const n = parseInt(c.replace('post-',''), 10);
        if (!isNaN(n)) return n;
      }
    }
    return 0;
  }

  // Delegiere Klicks auf Produktlinks (nicht Add-to-Cart Buttons)
  document.addEventListener('click', function(e){
    const link = e.target.closest('.products a');
    if (!link) return;

    // Nicht abfangen: Add-to-Cart Buttons, Mittelklick, Ctrl/Cmd-Klick (neuer Tab)
    if (link.classList.contains('add_to_cart_button') || e.button === 1 || e.metaKey || e.ctrlKey) return;

    const href = link.getAttribute('href');
    if (!href) return;

    const pid = getProductIdFromLink(link);
    if (!pid) return; // wenn ID nicht ermittelbar, normal navigieren
    e.preventDefault();

    openModal();
    content.innerHTML = '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Bitte warten…</p></div>';

    const url = new URL('<?php echo esc_url( home_url('/?wc-ajax=gf_quickview') ); ?>');
    url.searchParams.set('product_id', String(pid));

    fetch(url.toString(), { credentials: 'same-origin' })
      .then(r => r.text())
      .then(html => {
        content.innerHTML = html;
        // Variations-Form initialisieren
        if (window.jQuery) {
          jQuery(content).find('.variations_form').each(function(){
            jQuery(this).wc_variation_form();
          });
          // Trigger Variation-Events, damit Preise/Verfügbarkeit sofort stimmen
          jQuery(content).find('.variations_form').trigger('check_variations');
        }
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