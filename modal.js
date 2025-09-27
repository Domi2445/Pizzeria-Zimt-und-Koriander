document.addEventListener("DOMContentLoaded", function () {
  function openModal() {
    document.getElementById("bestell-modal").classList.add("active");
    document.getElementById("bestell-modal").setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }
  function closeModal() {
    document.getElementById("bestell-modal").classList.remove("active");
    document.getElementById("bestell-modal").setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }
  document.getElementById("bestell-btn").addEventListener("click", function (e) {
    e.preventDefault();
    openModal();
  });
  const b2 = document.getElementById("bestell-btn-2");
  if (b2) b2.addEventListener("click", function (e) {
    e.preventDefault();
    openModal();
  });
  document.getElementById("close-modal").addEventListener("click", closeModal);
  document.querySelector("#bestell-modal .modal-backdrop").addEventListener("click", closeModal);
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeModal();
  });
});