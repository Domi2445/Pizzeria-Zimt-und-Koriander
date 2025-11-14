<?php
/*
Template Name: Pizzeria Startseite
*/
get_header();
?>

<style>
/* Grundlayout */
body {
  font-family: 'Open Sans', sans-serif;
  background-color: #fffaf3;
  color: #333;
  margin: 0;
}

/* Hero */
.hero {
  background: url('https://source.unsplash.com/1600x600/?pizza') center/cover no-repeat;
  color: white;
  text-align: center;
  padding: 4rem 2rem;
}
.hero h2 {
  font-size: 3rem;
  font-family: 'Playfair Display', serif;
  margin-bottom: 1rem;
}
.cta-buttons a {
  display: inline-block;
  margin: 0.5rem;
  padding: 0.75rem 1.5rem;
  background-color: #ff6347;
  color: white;
  text-decoration: none;
  border-radius: 5px;
  font-weight: bold;
}

/* Sektionen */
.section {
  padding: 2rem;
  max-width: 1000px;
  margin: auto;
}
.info, .hours, .order {
  background-color: #f9f9f9;
  padding: 2rem;
  border-top: 1px solid #ddd;
  margin-top: 2rem;
}
.order ul, .hours ul {
  list-style: none;
  padding: 0;
}
.order li, .hours li {
  margin: 0.5rem 0;
  font-size: 1.1rem;
}

/* Modal */
.modal {
  display: none;
  position: fixed;
  z-index: 9999;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.6);
  animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.modal-content {
  background-color: #fff;
  margin: 3% auto;
  padding: 1rem;
  border-radius: 12px;
  width: 95%;
  max-width: 1200px;
  height: 80vh;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  display: flex;
  flex-direction: column;
  position: relative;
}
.modal-content iframe {
  flex: 1;
  width: 100%;
  height: 100%;
  border: none;
  border-radius: 8px;
}
.close {
  position: absolute;
  top: 12px;
  right: 16px;
  font-size: 28px;
  font-weight: bold;
  color: #aaa;
  cursor: pointer;
}
.close:hover {
  color: #000;
}

/* Responsive */
@media (max-width: 768px) {
  .modal-content {
    height: 90vh;
    width: 98%;
  }
}
</style>

<main>
  <section class="hero">
    <h2>Frisch. Regional. Unvergesslich.</h2>
    <div class="cta-buttons">
      <a href="tel:+4993349799490">📞 Anrufen</a>
      <a href="https://wa.me/4915252745487">💬 WhatsApp</a>
      <a href="#" id="openModalBtn">🌐 Online bestellen</a>
    </div>
  </section>

  <section id="order" class="order">
    <h2>Bestellen</h2>
    <ul>
      <li>📞 <a href="tel:+4993349799490">09334 9799490</a></li>
      <li>💬 <a href="https://wa.me/4915252745487">WhatsApp</a></li>
      <li>🌐 <a href="#" id="openModalLink">Online bestellen</a></li>
    </ul>
  </section>

  <section id="hours" class="hours">
    <h2>Öffnungszeiten</h2>
    <ul>
      <li>Montag – Samstag: 16:00 – 22:00 Uhr</li>
      <li>Sonntag: 14:30 – 22:00 Uhr</li>
    </ul>
  </section>

  <section id="speisekarte" class="section">
    <h2>Unsere Speisekarte</h2>
    <?php echo do_shortcode('[gloriafood_menu]'); ?>
  </section>

  <section id="kontakt" class="info">
    <h2>Kontakt</h2>
    <p>Zimt & Koriander<br>Mergentheimer Straße 6<br>97232 Giebelstadt</p>
    <p>E-Mail: <a href="mailto:info@pizzeriazimtundkoriander.de">info@pizzeriazimtundkoriander.de</a></p>
    <p>💳 Kartenzahlung & 💶 Barzahlung möglich</p>
    <p>🎁 Jetzt auch Gutscheine erhältlich – online & vor Ort</p>
  </section>

  <!-- Modal -->
  <div id="orderModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <iframe src="https://www.foodbooking.com/api/fb/1d_mm_m"></iframe>
    </div>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('orderModal');
  const openBtn = document.getElementById('openModalBtn');
  const openLink = document.getElementById('openModalLink');
  const closeBtn = modal.querySelector('.close');

  openBtn.onclick = (e) => {
    e.preventDefault();
    modal.style.display = 'block';
  };
  openLink.onclick = (e) => {
    e.preventDefault();
    modal.style.display = 'block';
  };
  closeBtn.onclick = () => modal.style.display = 'none';
  window.onclick = (e) => {
    if (e.target === modal) modal.style.display = 'none';
  };
});
</script>

<?php get_footer(); ?>