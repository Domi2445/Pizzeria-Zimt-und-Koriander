<!-- Footer -->
<footer>
  <nav>
    <a href="https://neu.pizzeriazimtundkoriander.de/impressum">Impressum</a>
    <a href="https://neu.pizzeriazimtundkoriander.de/datenschutz">Datenschutz</a>
	  <div class="payment">
    Wir akzeptieren: 💳 Kartenzahlung & 💶 Barzahlung
  </div>
  </nav>
</footer>

<!-- Footer-Styles -->
<style>
  footer {
    
    text-align: center;
    padding: 1rem 0;
    font-size: 0.9rem;
    margin-top: 2rem;
    font-family: Arial, sans-serif;
  }

  footer nav a {
    margin: 0 10px;
    color: #333;
    text-decoration: none;
  }

  footer nav a:hover {
    text-decoration: underline;
  }
	footer .payment 
	{
		margin-top: 0.5rem;
		color: #444;
		font-size: 0.85rem;
	}

</style>

<!-- GloriaFood Script -->
<script src="https://www.fbgcdn.com/embedder/js/ewm2.js" defer async></script>

<!-- Burger-Menü-Funktion -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const burger = document.querySelector('.burger');
    const nav = document.querySelector('.nav-links');
    if (burger && nav) {
      burger.addEventListener('click', () => nav.classList.toggle('nav-active'));
    }
    console.log("Footer-Script aktiv ✔️");
  });
</script>

<?php wp_footer(); ?>
</body>
</html>
