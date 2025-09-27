document.addEventListener("DOMContentLoaded", function () {
  const burger = document.getElementById("burger");
  const menu = document.getElementById("nav-menu");

  if (!burger || !menu) return;

  // Overlay erstellen (einmalig)
  let overlay = document.querySelector(".nav-overlay");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.className = "nav-overlay";
    document.body.appendChild(overlay);
  }

  function openMenu() {
    menu.classList.add("active");
    burger.classList.add("open");
    overlay.classList.add("show");
    document.body.style.overflow = "hidden";
  }
  function closeMenu() {
    menu.classList.remove("active");
    burger.classList.remove("open");
    overlay.classList.remove("show");
    document.body.style.overflow = "";
  }
  function toggleMenu(e) {
    e.preventDefault();
    e.stopPropagation();
    if (menu.classList.contains("active")) closeMenu();
    else openMenu();
  }

  // Toggle per Klick/Touch
  ["click", "touchstart"].forEach(evt =>
    burger.addEventListener(evt, toggleMenu, { passive: false })
  );

  // Links im Menü schließen das Menü
  menu.querySelectorAll("a").forEach(a => {
    a.addEventListener("click", () => closeMenu());
  });

  // Klick auf Overlay schließt
  ["click", "touchstart"].forEach(evt =>
    overlay.addEventListener(evt, closeMenu, { passive: true })
  );

  // Klick außerhalb von Menü & Burger schließt
  document.addEventListener("click", function (e) {
    if (!menu.classList.contains("active")) return;
    const clickedOutside =
      !menu.contains(e.target) && !burger.contains(e.target);
    if (clickedOutside) closeMenu();
  });

  // ESC schließt
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && menu.classList.contains("active")) closeMenu();
  });

  // Bei Resize auf Desktop Zustand zurücksetzen
  window.addEventListener("resize", function () {
    if (window.innerWidth > 800) {
      closeMenu();
    }
  });
});