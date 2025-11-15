<?php
/**
 * Minimaler Theme-Entry (KEINE Funktionsdefinitionen hier!)
 * Nutzt die Standard-Loop.
 */
get_header();
?>
<main id="primary" class="site-main">
  <?php
  if ( have_posts() ) {
    while ( have_posts() ) {
      the_post();
      the_content();
    }
  } else {
    echo '<p>Keine Inhalte gefunden.</p>';
  }
  ?>
</main>
<?php
get_footer();