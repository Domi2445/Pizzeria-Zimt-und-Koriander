<?php
/**
 * WooCommerce Shopseite im Lieferando-Stil
 */
get_header();
?>

<style>
.shop-lieferando-container {
  max-width: 1200px;
  margin: 3rem auto;
  padding: 0 1rem;
  display: flex;
  gap: 2rem;
}
.shop-lieferando-main {
  flex: 3;
}
.shop-lieferando-sidebar {
  flex: 1;
  background: #f9f9f9;
  padding: 1rem;
  border-radius: 12px;
  box-shadow: 0 0 8px rgba(0,0,0,0.05);
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
.shop-lieferando-cart-empty {
  text-align: center;
  color: #666;
  font-size: 0.95rem;
}
@media (max-width: 800px) {
  .shop-lieferando-container {
    flex-direction: column;
  }
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
    // Kategorie-Auswahl als Tabs
    $terms = get_terms('product_cat', ['hide_empty' => true]);
    if ($terms && !is_wp_error($terms)) {
      echo '<div class="shop-lieferando-categories">';
      foreach ($terms as $term) {
        $is_active = (is_tax('product_cat') && get_queried_object()->term_id === $term->term_id) ? 'active' : '';
        echo '<a href="' . get_term_link($term) . '" class="' . $is_active . '">' . esc_html($term->name) . '</a>';
      }
      echo '</div>';
    }

    // WooCommerce Produkt-Loop
    if (woocommerce_product_loop()) {
      echo '<div class="shop-lieferando-grid">';
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

<?php get_footer(); ?>
