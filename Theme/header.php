<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php bloginfo('name'); ?><?php wp_title(' | ', true, 'left'); ?></title>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header>
  <a class="skip-link screen-reader-text" href="#main">Zum Inhalt springen</a>

  <div id="topbar">
    <button id="burger-menu" aria-label="Menü öffnen" aria-expanded="false" aria-controls="menue">&#9776;</button>
  </div>

  <div id="grid2">
    <div class="logo">
      <a href="<?php echo esc_url(home_url('/')); ?>">
        <?php
          if (has_custom_logo()) {
            the_custom_logo();
          } else {
            echo '<img src="' . esc_url(get_template_directory_uri() . '/assets/logo.jpg') . '" alt="Zimt & Koriander" class="logo">';
          }
        ?>
      </a>
    </div>

    <div id="navigation">
      <?php $home = esc_url( home_url( '/' ) ); ?>
      <nav id="menue">
        <a href="<?php echo $home; ?>#speisekarte">Speisekarte</a>
        <a href="<?php echo $home; ?>#order">Bestellen</a>
        <a href="<?php echo $home; ?>#hours">Öffnungszeiten</a>
        <a href="<?php echo $home; ?>#kontakt">Kontakt</a>
        <span class="glf-button glf-link"
              data-glf-cuid="add20180-ec84-47d2-9072-c8f0a4a01184"
              data-glf-ruid="16bfdb1e-5e6f-41d8-bd9a-9409c1ae77bf">
          Online Bestellen
        </span>
      </nav>
    </div>
  </div>
</header>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const burger = document.getElementById("burger-menu");
    const menue = document.getElementById("menue");

    if (burger && menue) {
      burger.addEventListener("click", function () {
        const isOpen = menue.classList.toggle("open");
        burger.setAttribute("aria-expanded", isOpen ? "true" : "false");
      });
    }
  });
</script>

<style>
/* Layout */
#grid2 {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* Logo */
.logo img {
  max-width: 100%;
  display: block;
  border-radius: 8px;
  margin-top: 25px;
}
.logo {
  height: 100px;
  width: 200px;
  margin-right: 40px;
  margin-left: 10px;
}

/* GloriaFood Link */
.glf-link {
  display: inline-block;
  color: black;
  text-decoration: none;
  text-transform: uppercase;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  font-size: 15px;
  font-weight: 600;
  padding: 4px 8px;
  margin-right: 3px;
  border-radius: 3px;
  cursor: pointer;
}
@media (min-width: 701px) {
  .glf-link:hover {
    background-color: rgba(238, 21, 5, 0.795);
    color: white;
  }
}
@media (max-width: 700px) {
  .glf-link {
    padding: 4px 8px !important;
    background: none !important;
    color: black !important;
    text-transform: uppercase !important;
    font-size: 14px !important;
    border-radius: 0 !important;
  }
  .glf-link:hover {
    background-color: transparent !important;
    text-decoration: underline !important;
  }
}

/* Topbar + Burger */
#topbar {
  position: fixed;
  top: 0; left: 0;
  width: 100%;
  height: 50px;
  background-color: rgb(235, 218, 125);
  display: flex;
  align-items: center;
  padding-left: 10px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
  z-index: 9000;
}
#burger-menu {
  font-size: 30px;
  background: none;
  border: none;
  cursor: pointer;
  color: black;
  width: 40px;
  height: 40px;
  padding: 0;
  position: relative;
  z-index: 2100;
  user-select: none;
}

/* Menu Base */
#menue {
  display: flex;
  justify-content: flex-end;
}

/* Desktop */
@media (min-width: 701px) {
  #topbar { display: none; }
  #burger-menu { display: none !important; }
  #menue {
    position: static !important;
    width: auto !important;
    height: auto !important;
    background: transparent !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 5px !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    visibility: visible !important;
    opacity: 1 !important;
  }
  #menue a {
    border-style: solid !important;
    border-width: medium !important;
    padding: 4px 8px !important;
    color: black !important;
    text-decoration: none !important;
    text-transform: uppercase !important;
    border-radius: 3px !important;
    margin-right: 3px !important;
  }
  #menue a:hover {
    background-color: rgba(238, 21, 5, 0.795) !important;
    border-radius: 3px !important;
  }
  .logo { height: 100px !important; width: 200px !important; }
}

/* Mobile: Sliding menu */
#menue {
  position: fixed;
  top: 50px;
  left: -250px;
  width: 250px;
  height: calc(100vh - 50px);
  background-color: rgb(235, 218, 125);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  margin-top: 0;
  padding-top: 10px;
  box-shadow: 2px 0 5px rgba(0,0,0,0.3);
  transition: left 0.3s ease;
  z-index: 1500;
  overflow-y: auto;
}
#menue.open { left: 0; }

@media (max-width: 700px) {
  main { padding-top: 60px !important; }
  #menue a {
    border: none !important;
    padding: 4px 8px !important;
    color: black !important;
    text-decoration: none !important;
    text-transform: uppercase !important;
    border-radius: 0 !important;
    margin-right: 3px !important;
    background: none !important;
  }
  #menue a:hover { background-color: transparent !important; }
  #burger-menu { display: block !important; }
}
</style>