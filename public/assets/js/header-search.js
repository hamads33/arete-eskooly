(function () {
  "use strict";
  const search = document.getElementById("studentSearch");
  const input = document.getElementById("studentSearchInput");
  const results = document.getElementById("studentSearchResults");
  const mobileToggle = document.getElementById("mobileSearchToggle");

  if (!search || !input || !results || !mobileToggle) return;

  function closeSearch() {
    search.classList.remove("has-results");
    input.setAttribute("aria-expanded", "false");
  }

  function renderResults(query) {
    const normalized = query.trim().toLowerCase();
    if (!normalized) { closeSearch(); results.replaceChildren(); return; }
    const matches = (window.dashboardData?.students ?? []).filter((student) => [student.name, student.registration, student.className].some((value) => String(value ?? "").toLowerCase().includes(normalized)));
    results.replaceChildren();

    if (!matches.length) {
      const empty = document.createElement("div");
      empty.className = "search-empty";
      empty.textContent = window.dashboardData ? "No students match your search." : "Search the student directory.";
      if (!window.dashboardData) {
        const link = document.createElement('a');
        link.className = 'dropdown-item';
        link.href = '/admin/students?student_search=' + encodeURIComponent(query.trim());
        link.textContent = 'Find students: ' + query.trim();
        results.append(link);
      }
      results.append(empty);
    } else {
      matches.forEach((student) => {
        // Built from text nodes: a name typed into the admission form can never inject markup.
        const link = document.createElement("a");
        link.className = "dropdown-item";
        link.setAttribute("role", "option");
        link.href = '/admin/students?student_search=' + encodeURIComponent(student.registration || student.name);
        const avatar = document.createElement("span");
        avatar.className = "avatar";
        avatar.textContent = student.initials || "";
        const content = document.createElement("span");
        content.className = "dropdown-item__content";
        const name = document.createElement("strong");
        name.textContent = student.name;
        const meta = document.createElement("p");
        meta.textContent = [student.registration, student.className].filter(Boolean).join(" · ");
        content.append(name, meta);
        link.append(avatar, content);
        results.append(link);
      });
    }
    search.classList.add("has-results");
    input.setAttribute("aria-expanded", "true");
  }

  input?.addEventListener("input", (event) => renderResults(event.target.value));
  input?.addEventListener("focus", () => { if (input.value) renderResults(input.value); });
  mobileToggle?.addEventListener("click", () => {
    const open = search.classList.toggle("is-mobile-open");
    mobileToggle.setAttribute("aria-expanded", String(open));
    if (open) input.focus(); // Keep focus inside the tap gesture for mobile keyboards.
    else closeSearch();
  });
  document.addEventListener("click", (event) => { if (!search.contains(event.target)) closeSearch(); });
  document.addEventListener("keydown", (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") { event.preventDefault(); search.classList.add("is-mobile-open"); input.focus(); }
    if (event.key === "Escape") { closeSearch(); search.classList.remove("is-mobile-open"); mobileToggle?.setAttribute("aria-expanded", "false"); }
  });

  input.addEventListener('keydown', event => {
    if (event.key === 'Enter' && input.value.trim()) {
      event.preventDefault();
      window.location.href = '/admin/students?student_search=' + encodeURIComponent(input.value.trim());
    }
  });
})();
