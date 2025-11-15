<?php
/**
 * Einzelproduktseite – zeigt direkt ein Quick-View-Modal im Lieferando-Stil
 */
defined('ABSPATH') || exit;

get_header('shop');
$product_id = get_the_ID();
?>
<style>
/* Modal-Styles (wie im Archiv) */
.gf-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9999; }
.gf-modal { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 10000; }
.gf-modal-box { background: #fff; width: 95%; max-width: 900px; max-height: 90vh; border-radius: 16px; overflow: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
.gf-modal-inner { padding: 1rem 1rem 1.25rem; position: relative; }
.gf-modal-close { position: absolute; top: 8px; right: 10px; font-size: 28px; width: 36px; height: 36px; border: 0; background: transparent; color: #888; cursor: pointer; }
.gf-modal-image { width: 100%; height: 280px; object-fit: cover; border-radius: 12px; }
.gf-modal-image-wrap { margin-bottom: 0.75rem; }
.gf-modal-title { font-size: 1.6rem; font-weight: 700; color: #ff8000; margin: 0.25rem 0; }
.gf-modal-price { display: inline-block; background: #ff8000; color:#fff; padding: 0.35rem 0.6rem; border-radius: 6px; font-weight: 700; margin: 0.4rem 0 0.8rem; }
.gf-modal-allergens { margin: 0.5rem 0 0.25rem; }
.gf-chip-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.gf-chip { display: inline-block; background: #fff2e6; color: #a23c16; border: 1px solid #ffd4b0; padding: 3px 8px; border-radius: 999px; font-size: 0.85rem; }
.gf-modal-desc { color: #444; line-height: 1.55; margin: 0.6rem 0 1rem; }
.gf-modal-cart .button { background-color: #ff8000; color:#fff; font-weight: 600; border-radius: 8px; padding: 0.7rem 1rem; }
.gf-modal-cart .button:hover { background-color: #e56f00; }
</style>

<div class="gf-modal-backdrop" id="gfModalBackdrop"></div>
<div class="gf-modal" id="gfModal" aria-hidden="false" role="dialog" aria-modal="true">
  <div class="gf-modal-box">
    <div id="gfModalContent">
      <?php
      if (function_exists('gf_render_quickview_inner')) {
        gf_render_quickview_inner($product_id);
      } else {
        echo '<div class="gf-modal-inner"><button class="gf-modal-close" aria-label="Schließen">×</button><p>Produktansicht nicht verfügbar.</p></div>';
      }
      ?>
    </div>
  </div>
</div>

<script>
(function(){
  const backdrop = document.getElementById('gfModalBackdrop');

  // Close: geht zurück zum Shop
  function goBack() {
    if (window.history.length > 1) { window.history.back(); }
    else { window.location.href = '<?php echo esc_js( wc_get_page_permalink('shop') ); ?>'; }
  }

  document.addEventListener('click', function(e){
    if (e.target.classList.contains('gf-modal-close') || e.target === backdrop) {
      e.preventDefault();
      goBack();
    }
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') goBack();
  });

  // Variations-Form initialisieren (falls variable)
  if (window.jQuery) {
    jQuery('#gfModalContent').find('.variations_form').each(function(){
      jQuery(this).wc_variation_form();
    });
  }
})();
</script>

<?php get_footer('shop'); ?>