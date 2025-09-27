document.addEventListener("DOMContentLoaded", function () {
  function openModal() {
    document.getElementById("bestell-modal").classList.add("active");
    document.body.style.overflow = "hidden";
  }
  function closeModal() {
    document.getElementById("bestell-modal").classList.remove("active");
    document.body.style.overflow = "";
  }
  document.getElementById("bestell-btn").addEventListener("click", function (e) {
    e.preventDefault();
    openModal();
  });
  if (document.getElementById("bestell-btn-2")) {
    document.getElementById("bestell-btn-2").addEventListener("click", function (e) {
      e.preventDefault();
      openModal();
    });
  }
  document.getElementById("close-modal").addEventListener("click", closeModal);
  document.querySelector("#bestell-modal .modal-backdrop").addEventListener("click", closeModal);
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeModal();
  });
});