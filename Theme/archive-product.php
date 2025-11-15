<?php
/**
 * Shop / Speisekarte: alle Kategorien + Produkte auf einer Seite,
 * Suche blendet leere Kategorien aus (Lieferando‑like).
 */
get_header();
?>

<style>
/* Layout + Card Styles (siehe vorherige Version, ergänzt) */
.lief-wrap { max-width:1200px; margin: 2rem auto; padding: 0 1rem; }
.lief-hero { text-align:center; margin-bottom: 1rem; }
.lief-hero h1 { font-size:2.4rem; color:#ff8000; margin:0 0 0.5rem; }
.lief-search { display:flex; justify-content:center; margin-bottom:12px; }
.lief-search input { width:70%; max-width:800px; padding:0.65rem 0.9rem; border-radius:999px; border:1px solid #eee; }

/* Kategorie / Produkte */
.kategorie-section { margin: 2rem 0; transition: opacity .18s ease; }
.kategorie-section.hidden { opacity: 0; height: 0; margin: 0; padding: 0; overflow: hidden; pointer-events: none; }
.kategorie-section .kategorie-title { font-size:1.4rem; font-weight:700; margin-bottom:1rem; color:#333; padding-left:6px; }
.kategorie-products { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1.25rem; }
.shop-card { background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.06); overflow:hidden; display:flex; flex-direction:column; }
.shop-card img { width:100%; height:160px; object-fit:cover; display:block; }
.shop-card-body { padding:0.9rem; flex:1; display:flex; flex-direction:column; justify-content:space-between; }
.shop-card h3 { margin:0 0 0.4rem; font-size:1rem; color:#222; }
.shop-card p { margin:0 0 0.6rem; color:#666; font-size:0.95rem; }
.shop-card .price { display:inline-block; background:#ff8000; color:#fff; padding:6px 10px; border-radius:8px; font-weight:700; }
.shop-card .meta { display:flex; gap:8px; align-items:center; margin-top:8px; }

/* Keine Treffer */
.lief-no-results { text-align:center; padding:2rem 0; color:#666; font-weight:600; display:none; }

/* small changes */
@media (max-width:800px) {
  .lief-hero h1 { font-size:1.6rem; }
  .lief-search input { width:92%; }
}
</style>

<div class="lief-wrap">
  <div class="lief-hero">
    <h1>Unsere Speisekarte</h1>
    <div class="lief-search">
      <input id="liefSearch" type="search" placeholder="Suche Gerichte..." aria-label="Suche Speisekarte">
    </div>
  </div>

  <!-- category nav placeholder (JS baut das Band) -->
  <div id="theme-category-nav-placeholder" aria-hidden="true"></div>

  <?php
  // Alle Kategorien laden (sichtbare, sortiert)
  $terms = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => true,
    'orderby' => 'menu_order',
    'order' => 'ASC'
  ]);

  if ($terms && !is_wp_error($terms)) {
    foreach ($terms as $term) {
      // Produkte in Kategorie
      $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'tax_query' => [[
          'taxonomy' => 'product_cat',
          'field'    => 'term_id',
          'terms'    => $term->term_id,
        ]]
      ];
      $q = new WP_Query($args);
      if (!$q->have_posts()) { continue; }
      $section_id = 'kategorie-' . sanitize_title($term->slug);
      ?>
      <section id="<?php echo esc_attr($section_id); ?>" class="kategorie-section" data-cat-id="<?php echo esc_attr($term->term_id); ?>" data-cat-slug="<?php echo esc_attr($term->slug); ?>">
        <div class="kategorie-title"><?php echo esc_html($term->name); ?></div>

        <div class="kategorie-products">
          <?php while ($q->have_posts()) : $q->the_post();
            global $product;
            $pid = get_the_ID();
            $img = has_post_thumbnail($pid) ? get_the_post_thumbnail($pid, 'medium', ['loading'=>'lazy']) : '<img src="' . esc_url(wc_placeholder_img_src()) . '" alt="">';
            $price_html = $product ? $product->get_price_html() : '';
            ?>
            <article class="shop-card product" id="post-<?php the_ID(); ?>" data-pid="<?php echo esc_attr($pid); ?>">
              <a href="<?php the_permalink(); ?>" class="gf-product-link" aria-label="<?php echo esc_attr(get_the_title()); ?>">
                <?php echo $img; ?>
              </a>
              <div class="shop-card-body">
                <div>
                  <h3><?php the_title(); ?></h3>
                  <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 16, '...')); ?></p>
                </div>
                <div class="meta">
                  <div class="price"><?php echo wp_kses_post($price_html); ?></div>
                  <div class="add">
                    <?php woocommerce_template_loop_add_to_cart(); ?>
                  </div>
                </div>
              </div>
            </article>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
      </section>
      <?php
    }
  } else {
    echo '<p>Keine Kategorien / Produkte gefunden.</p>';
  }
  ?>

  <div class="lief-no-results" id="liefNoResults">Keine Treffer für Ihre Suche.</div>
</div>

<!-- Modal structure (quickview) -->
<div class="gf-modal-backdrop" id="gfModalBackdrop" hidden></div>
<div class="gf-modal" id="gfModal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="gf-modal-box">
    <div id="gfModalContent"><!-- AJAX content loaded here --></div>
  </div>
</div>

<script>
(function(){
  var input = document.getElementById('liefSearch');
  var noRes = document.getElementById('liefNoResults');

  function updateCategoryVisibilityAndNav(){
    var totalVisible = 0;
    document.querySelectorAll('.kategorie-section').forEach(function(section){
      // count visible product cards inside
      var cards = section.querySelectorAll('.kategorie-products .shop-card');
      var anyVisible = false;
      cards.forEach(function(card){
        // card may be hidden via display:none by filter
        if (card.style.display !== 'none') { anyVisible = true; }
      });
      // toggle section
      if (anyVisible) {
        section.classList.remove('hidden');
        totalVisible++;
      } else {
        section.classList.add('hidden');
      }
      // update nav item (if present)
      var navItem = document.querySelector('.theme-category-nav-item[data-target="' + section.id + '"]');
      if (navItem) {
        navItem.style.display = anyVisible ? '' : 'none';
      }
    });

    // show/hide no-results message
    if (totalVisible === 0) { noRes.style.display = ''; }
    else { noRes.style.display = 'none'; }

    // if category-nav.js exposes an updater, call it
    if (window.ThemeCategoryNav && typeof window.ThemeCategoryNav.onFilterUpdate === 'function') {
      window.ThemeCategoryNav.onFilterUpdate();
    }
  }

  // Simple client-side filter: hide cards whose title/desc don't match query
  if (input) {
    input.addEventListener('input', function(){
      var q = input.value.trim().toLowerCase();
      document.querySelectorAll('.kategorie-products .shop-card').forEach(function(card){
        var title = (card.querySelector('h3') && card.querySelector('h3').textContent) ? card.querySelector('h3').textContent.toLowerCase() : '';
        var desc = (card.querySelector('p') && card.querySelector('p').textContent) ? card.querySelector('p').textContent.toLowerCase() : '';
        if (!q || title.indexOf(q) !== -1 || desc.indexOf(q) !== -1) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
      updateCategoryVisibilityAndNav();
    });

    // clear with ESC or "x" if any native browser control triggers input event; ensure nav resets
    input.addEventListener('search', function(){
      // 'search' fires for some browsers on clear
      document.querySelectorAll('.kategorie-products .shop-card').forEach(function(card){ card.style.display = ''; });
      updateCategoryVisibilityAndNav();
    });
  }

  // initial visibility sync (on page load)
  document.addEventListener('DOMContentLoaded', function(){
    // trigger once to ensure nav items reflect initial state
    setTimeout(updateCategoryVisibilityAndNav, 30);
  });
})();
</script>