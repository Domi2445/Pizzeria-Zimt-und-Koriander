document.addEventListener("DOMContentLoaded", function () {
  const burger = document.getElementById("burger");
  const menu = document.getElementById("nav-menu");
  if (!burger || !menu) return;

  // Overlay einmalig anfügen
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
    burger.setAttribute("aria-expanded", "true");
    document.body.style.overflow = "hidden";
  }
  function closeMenu() {
    menu.classList.remove("active");
    burger.classList.remove("open");
    overlay.classList.remove("show");
    burger.setAttribute("aria-expanded", "false");
    document.body.style.overflow = "";
  }
  function toggleMenu(e) {
    e.preventDefault();
    e.stopPropagation();
    if (menu.classList.contains("active")) closeMenu();
    else openMenu();
  }

  ["click", "touchstart"].forEach(evt =>
    burger.addEventListener(evt, toggleMenu, { passive: false })
  );

  // Links schließen Menü
  menu.querySelectorAll("a").forEach(a => a.addEventListener("click", closeMenu));

  // Overlay klick schließt
  ["click", "touchstart"].forEach(evt =>
    overlay.addEventListener(evt, closeMenu, { passive: true })
  );

  // Klick außerhalb schließt
  document.addEventListener("click", function (e) {
    if (!menu.classList.contains("active")) return;
    if (!menu.contains(e.target) && !burger.contains(e.target)) closeMenu();
  });

  // ESC schließt
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && menu.classList.contains("active")) closeMenu();
  });

  // Resize-Guard
  window.addEventListener("resize", function () {
    if (window.innerWidth > 800) closeMenu();
  });
});