(function () {
  "use strict";

  const shell = document.getElementById("appShell");
  const sidebar = document.getElementById("sidebar");
  const toggle = document.getElementById("sidebarToggle");
  const closeButton = document.getElementById("mobileSidebarClose");
  const overlay = document.getElementById("sidebarOverlay");
  const mobileQuery = window.matchMedia("(max-width: 1023px)");
  const menuSearch = document.getElementById("sidebarSearchInput");

  if (!shell || !sidebar || !toggle || !closeButton || !overlay) return;
  const main = shell.querySelector('main');
  function setMobileSidebar(open, returnFocus = false) {
    open = mobileQuery.matches && open;
    if (!open && sidebar.contains(document.activeElement)) toggle.focus({ preventScroll: true });
    sidebar.inert = mobileQuery.matches && !open;
    if (sidebar.inert) sidebar.setAttribute('aria-hidden', 'true');
    else sidebar.removeAttribute('aria-hidden');
    overlay.hidden = !open;
    if (main) main.inert = open;
    sidebar.classList.toggle("is-open", open);
    overlay.classList.toggle("is-visible", open);
    document.body.classList.toggle("no-scroll", open);
    toggle.setAttribute("aria-expanded", String(open));
    if (open) closeButton.focus({ preventScroll: true });
  }

  function toggleSidebar() {
    if (mobileQuery.matches) {
      setMobileSidebar(!sidebar.classList.contains("is-open"));
      return;
    }
    const collapsed = shell.classList.toggle("is-collapsed");
    toggle.setAttribute("aria-expanded", String(!collapsed));
  }

  toggle.addEventListener("click", toggleSidebar);
  closeButton.addEventListener("click", () => setMobileSidebar(false, true));
  overlay.addEventListener("click", () => setMobileSidebar(false, true));

  document.querySelectorAll("[data-submenu-trigger]").forEach((trigger) => {
    trigger.addEventListener("click", () => {
      if (!mobileQuery.matches && shell.classList.contains("is-collapsed")) {
        shell.classList.remove("is-collapsed");
        toggle.setAttribute("aria-expanded", "true");
      }
      const submenu = trigger.nextElementSibling;
      const open = !submenu.classList.contains("is-open");
      submenu.classList.toggle("is-open", open);
      trigger.setAttribute("aria-expanded", String(open));
    });
  });

  sidebar.querySelectorAll(".nav-link, .submenu-link").forEach((link) => {
    link.addEventListener("click", () => {
      sidebar.querySelectorAll(".nav-link.is-active, .submenu-link.is-active").forEach((activeLink) => activeLink.classList.remove("is-active"));
      link.classList.add("is-active");
      if (mobileQuery.matches && link.matches("a")) setMobileSidebar(false);
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Tab" && mobileQuery.matches && sidebar.classList.contains("is-open")) {
      const items = [...sidebar.querySelectorAll('a[href],button,input,select,textarea,[tabindex="0"]')].filter(el => !el.disabled && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
      const first = items[0], last = items[items.length - 1];
      if (first && event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (last && !event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    if (event.key === "Escape" && sidebar.classList.contains("is-open")) {
      setMobileSidebar(false);
      toggle.focus();
    }
  });

  mobileQuery.addEventListener("change", () => {
    setMobileSidebar(false);
    shell.classList.remove("is-collapsed");
    toggle.setAttribute("aria-expanded", String(!mobileQuery.matches));
  });

  setMobileSidebar(false);

  menuSearch?.addEventListener("input", () => {
    const query = menuSearch.value.trim().toLowerCase();
    sidebar.querySelectorAll(".nav-list > .nav-item").forEach((item) => {
      const matches = !query || item.textContent.toLowerCase().includes(query);
      item.classList.toggle("is-search-hidden", !matches);
      if (query && matches) {
        const submenu = item.querySelector(".submenu");
        const trigger = item.querySelector("[data-submenu-trigger]");
        submenu?.classList.add("is-open");
        trigger?.setAttribute("aria-expanded", "true");
      }
    });
  });
})();
