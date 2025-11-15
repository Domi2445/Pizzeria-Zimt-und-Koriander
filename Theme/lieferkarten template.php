<?php
/**
 * Template Name: Lieferinfo (Karte & Öffnungszeiten, untereinander)
 */
get_header();
?>
<div class="lieferplugin-info-box">
  <div class="lieferplugin-info-kartenbereich">
    <?php echo do_shortcode('[lieferkarte]'); ?>
  </div>
  <div class="lieferplugin-info-zeitenbereich">
    <?php echo do_shortcode('[oeffnungszeiten]'); ?>
	<?php echo do_shortcode('[bestellsystem]'); ?>
  </div>
</div>
<?php
get_footer();
?>