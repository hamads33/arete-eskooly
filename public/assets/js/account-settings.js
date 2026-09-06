(function () {
  "use strict";

  const form = document.getElementById("accountSettingsForm");
  const passwordInput = document.getElementById("accountPassword");
  const passwordToggle = document.getElementById("passwordToggle");
  const currencySelect = document.getElementById("accountCurrency");
  const symbolInput = document.getElementById("currencySymbol");
  const deleteButton = document.getElementById("deleteAccountButton");
  const modal = document.getElementById("deleteAccountModal");
  const cancelDeleteButton = document.getElementById("cancelDeleteButton");
  const confirmDeleteButton = document.getElementById("confirmDeleteButton");
  const modalBackdrop = modal?.querySelector("[data-modal-close]");
  let modalReturnFocus = null;

  function renderPasswordIcon(iconName) {
    passwordToggle.innerHTML = `<i data-lucide="${iconName}" aria-hidden="true"></i>`;
    window.lucide?.createIcons({ attrs: { "stroke-width": 1.9 } });
  }

  passwordToggle?.addEventListener("click", () => {
    const shouldShow = passwordInput.type === "password";
    passwordInput.type = shouldShow ? "text" : "password";
    passwordToggle.setAttribute("aria-label", shouldShow ? "Hide password" : "Show password");
    passwordToggle.setAttribute("aria-pressed", String(shouldShow));
    renderPasswordIcon(shouldShow ? "eye-off" : "eye");
    passwordInput.focus();
  });

  // DEMO FRONTEND BEHAVIOR ONLY.
  // Preserve actual backend behavior during PHP integration.
  const currencySymbols = { PKR: "Rs", USD: "$", SAR: "ر.س", AED: "د.إ", GBP: "£", EUR: "€" };
  currencySelect?.addEventListener("change", () => {
    symbolInput.value = currencySymbols[currencySelect.value] || symbolInput.value;
  });


  function openDeleteModal() {
    modalReturnFocus = document.activeElement;
    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("no-scroll");
    window.setTimeout(() => cancelDeleteButton.focus(), 0);
  }

  function closeDeleteModal() {
    modal.hidden = true;
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("no-scroll");
    modalReturnFocus?.focus();
  }

  deleteButton?.addEventListener("click", openDeleteModal);
  cancelDeleteButton?.addEventListener("click", closeDeleteModal);
  modalBackdrop?.addEventListener("click", closeDeleteModal);

  // STATIC PROTOTYPE ONLY — no destructive backend action.
  confirmDeleteButton?.addEventListener("click", () => {
    closeDeleteModal();
    window.showDashboardToast?.("No account was deleted — static prototype only.");
  });

  document.addEventListener("keydown", (event) => {
    if (modal?.hidden) return;
    if (event.key === "Escape") {
      event.preventDefault();
      closeDeleteModal();
      return;
    }
    if (event.key === "Tab") {
      const focusable = [...modal.querySelectorAll("button:not([disabled])")];
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
})();
