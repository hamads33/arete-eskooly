(function () {
  "use strict";

  const dropdowns = Array.from(document.querySelectorAll("[data-dropdown]"));

  function closeDropdown(dropdown, restoreFocus) {
    const trigger = dropdown.querySelector("[data-dropdown-trigger]");
    dropdown.classList.remove("is-open");
    trigger?.setAttribute("aria-expanded", "false");
    if (restoreFocus) trigger?.focus();
  }

  function closeAll(except) {
    dropdowns.forEach((dropdown) => {
      if (dropdown !== except) closeDropdown(dropdown, false);
    });
  }

  dropdowns.forEach((dropdown) => {
    const trigger = dropdown.querySelector("[data-dropdown-trigger]");
    const menu = dropdown.querySelector(".dropdown-menu");
    if (!trigger || !menu) return;

    trigger.addEventListener("click", (event) => {
      event.stopPropagation();
      const willOpen = !dropdown.classList.contains("is-open");
      closeAll(dropdown);
      dropdown.classList.toggle("is-open", willOpen);
      trigger.setAttribute("aria-expanded", String(willOpen));
      if (willOpen) menu.querySelector("a, button")?.focus({ preventScroll: true });
    });

    menu.addEventListener("click", (event) => event.stopPropagation());
  });

  document.addEventListener("click", () => closeAll());
  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    const openDropdown = dropdowns.find((dropdown) => dropdown.classList.contains("is-open"));
    if (openDropdown) closeDropdown(openDropdown, true);
  });

  window.dashboardDropdowns = { closeAll };
})();
