<?php
/*
Plugin Name: GloriaFood zu WooCommerce Produktsync (Varianten, Allergene, Addons inkl. globale Gruppen)
Description: Importiert Produkte aus GloriaFood inkl. Größen (Variationen), Allergene & Addons. Unterstützt globale Addon-Gruppen (Kategorie/Top-Level) und berechnet Aufpreise im Warenkorb/Checkout.
Version: 2.4.1
Author: Dominik
*/

if (!defined('ABSPATH')) exit;

// Alle Gruppen optional halten (min=0)
if (!defined('GF_FORCE_OPTIONAL_GROUPS')) define('GF_FORCE_OPTIONAL_GROUPS', true);
// Maximalbegrenzung ignorieren (immer Multi-Choice möglich)
if (!defined('GF_IGNORE_MAX')) define('GF_IGNORE_MAX', true);

/* ---------------------------
   ADMIN MENU
--------------------------- */
add_action('admin_menu', function() {
    add_menu_page('GloriaFood Sync','GloriaFood Sync','manage_woocommerce','gf-sync','gf_wc_sync_page');
});
function gf_wc_sync_page() {
    if (isset($_POST['gf_sync'])) {
        $created = gf_sync_gloriafood_products();
        echo '<div class="updated"><p>' . esc_html($created) . ' Produkte wurden synchronisiert.</p></div>';
    }
    echo '<h1>GloriaFood → WooCommerce Sync</h1>';
    echo '<form method="post"><button class="button button-primary" name="gf_sync">Produkte jetzt synchronisieren</button></form>';
}

/* ---------------------------
   SYNC
   (Unverändert von deiner Version – hier aus Platzgründen verkürzt)
--------------------------- */
function gf_sync_gloriafood_products() {
    // ... (dein vollständiger Sync-Code bleibt unverändert) ...
    // Kopiere hier den gesamten vorhandenen Sync-Code aus deiner Datei, unverändert.
}

/* ---------------------------
   SCHEMA / Helpers / Render Addons
   (Die bisherigen Plugin-Funktionen bleiben unverändert und müssen hier stehen)
--------------------------- */
// ... alle bisherigen helper- und render-funktionen (gf_build_addon_schema_from_item, gf_collect_possible_groups, gf_normalize_groups, gf_render_addons_ui, gf_render_groups, gf_collect_selected_addons_from_post, gf_build_selected_addons_detail, gf_find_option, gf_first_nonempty, gf_normalize_price, gf_ensure_attribute, gf_ensure_attribute_term, gf_ensure_product_cat, gf_wc_product_exists, gf_set_product_image, etc.) ...

/* ---------------------------
   Quickview AJAX Handler (neu, robust)
   - Registriert als wc_ajax_* so dass ?wc-ajax=gf_quickview funktioniert
   - Liefert dieselbe Produkt-HTML wie dein Theme-Quickview (gf_render_quickview_inner)
--------------------------- */
add_action('wc_ajax_gf_quickview', 'gf_ajax_quickview_plugin');
add_action('wc_ajax_nopriv_gf_quickview', 'gf_ajax_quickview_plugin');

function gf_ajax_quickview_plugin(){
    // Produkt-ID per GET param oder per product_url ermitteln
    $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
    if (!$product_id && !empty($_GET['product_url'])) {
        $url = esc_url_raw($_GET['product_url']);
        $product_id = url_to_postid($url);
    }

    if (!$product_id) {
        // 400 – bad request
        status_header(400);
        echo '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Produktansicht nicht verfügbar.</p></div>';
        wp_die();
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        status_header(404);
        echo '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Produktansicht nicht verfügbar.</p></div>';
        wp_die();
    }

    // Render HTML
    ob_start();
    gf_render_quickview_inner($product_id);
    $html = ob_get_clean();

    // Ausgabe
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    echo $html;
    wp_die();
}

/* ---------------------------
   Render-Funktion: Quickview-HTML (kopiert/angepasst)
   - Diese Funktion benötigt Woo-Product-Objekt; verwendet gf_render_addons_ui um Addons einzufügen.
--------------------------- */
function gf_render_quickview_inner($product_id){
    $new = wc_get_product($product_id);
    if (!$new) {
        echo '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Produktansicht nicht verfügbar.</p></div>';
        return;
    }

    global $product, $post;
    $prev_p = $product ?? null;
    $prev_post = $post ?? null;

    $product = $new;
    $post = get_post($product_id);
    if ($post) setup_postdata($post);

    $img_html = has_post_thumbnail($product_id) ? get_the_post_thumbnail($product_id, 'large', ['class'=>'gf-modal-image']) : '';
    $allergen_terms = wc_get_product_terms($product_id,'pa_allergene',['fields'=>'names']);
    $allergen_html = '';
    if ($allergen_terms) {
        foreach ($allergen_terms as $t) {
            $allergen_html .= '<span class="gf-chip">'.esc_html($t).'</span>';
        }
    }

    $desc = $product->get_short_description();
    if (!$desc) $desc = get_post_field('post_content', $product_id);
    $desc = wpautop(wp_kses_post($desc));
    $base_price = (float)$product->get_price();

    // Output (gleiches Layout wie Theme)
    ?>
    <div class="gf-modal-inner">
      <button class="gf-modal-close" aria-label="Schließen">×</button>

      <?php if ($img_html): ?>
        <div class="gf-modal-image-wrap"><?php echo $img_html; ?></div>
      <?php endif; ?>

      <h2 class="gf-modal-title"><?php echo esc_html(get_the_title($product_id)); ?></h2>
      <div class="gf-modal-price" data-base-price="<?php echo esc_attr($base_price); ?>"><?php echo wp_kses_post($product->get_price_html()); ?></div>

      <?php if (!empty($allergen_html)): ?>
        <div class="gf-modal-allergens"><strong>Allergene:</strong><div class="gf-chip-row"><?php echo $allergen_html; ?></div></div>
      <?php endif; ?>

      <?php if (!empty($desc)): ?><div class="gf-modal-desc"><?php echo $desc; ?></div><?php endif; ?>

      <div class="gf-modal-cart">
        <div class="gf-addtocart-wrap">
          <?php
            // Wichtig: setze $product global damit gf_render_addons_ui weiß, worauf es sich bezieht
            if (function_exists('woocommerce_template_single_add_to_cart')) {
                woocommerce_template_single_add_to_cart();
            }
            // zusätzlich: rendere Addons (falls vorhanden) – gf_render_addons_ui nutzt global $product
            if (function_exists('gf_render_addons_ui')) {
                echo '<div class="gf-addons-wrap">';
                gf_render_addons_ui();
                echo '</div>';
            }
          ?>
        </div>
      </div>
    </div>
    <?php

    // restore global state
    if ($prev_post) {
        $post = $prev_post;
        setup_postdata($post);
    } else {
        wp_reset_postdata();
    }
    $product = $prev_p;
}

/* ---------------------------
   Debug helper (bleibt unverändert)
--------------------------- */
add_action('wp_ajax_gf_dump_schema', function(){
    if(!current_user_can('manage_woocommerce')){ status_header(403); wp_die('forbidden'); }
    $pid=isset($_GET['product_id'])?absint($_GET['product_id']):0;
    if(!$pid){ status_header(400); wp_die('missing product_id'); }
    $schema=get_post_meta($pid,'_gf_addon_schema',true);
    header('Content-Type: application/json; charset='.get_bloginfo('charset'));
    echo wp_json_encode($schema, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); wp_die();
});

/* ---------------------------
  Ende Plugin
--------------------------- */