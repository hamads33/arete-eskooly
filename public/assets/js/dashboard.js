(function () {
  "use strict";

  const monthLabel = document.getElementById("calendarMonth");
  const calendarGrid = document.getElementById("calendarGrid");
  const now = new Date();
  let visibleDate = new Date(now.getFullYear(), now.getMonth(), 1);
  const weekdays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

  function renderCalendar() {
    const year = visibleDate.getFullYear();
    const month = visibleDate.getMonth();
    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    monthLabel.textContent = visibleDate.toLocaleDateString("en-US", { month: "long", year: "numeric" });
    calendarGrid.replaceChildren();

    weekdays.forEach((weekday) => {
      const label = document.createElement("div");
      label.className = "calendar-weekday";
      label.setAttribute("role", "columnheader");
      label.textContent = weekday;
      calendarGrid.append(label);
    });

    for (let index = 0; index < firstWeekday; index += 1) {
      const empty = document.createElement("span");
      empty.className = "calendar-day is-empty";
      empty.setAttribute("aria-hidden", "true");
      calendarGrid.append(empty);
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
      const cell = document.createElement("button");
      const isToday = day === now.getDate() && month === now.getMonth() && year === now.getFullYear();
      cell.type = "button";
      const events = window.dashboardData?.calendarEvents ?? [];
      cell.className = "calendar-day" + (isToday ? " is-today" : "") + (events.includes(`${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`) ? " has-event" : "");
      cell.textContent = String(day);
      cell.setAttribute("role", "gridcell");
      cell.setAttribute("aria-label", new Date(year, month, day).toLocaleDateString("en-US", { dateStyle: "full" }));
      cell.addEventListener("click", () => window.showDashboardToast?.("Calendar date selected."));
      calendarGrid.append(cell);
    }
  }

  document.getElementById("calendarPrev")?.addEventListener("click", () => { visibleDate = new Date(visibleDate.getFullYear(), visibleDate.getMonth() - 1, 1); renderCalendar(); });
  document.getElementById("calendarNext")?.addEventListener("click", () => { visibleDate = new Date(visibleDate.getFullYear(), visibleDate.getMonth() + 1, 1); renderCalendar(); });
  renderCalendar();

  const search = document.getElementById("studentSearch");
  const input = document.getElementById("studentSearchInput");
  const results = document.getElementById("studentSearchResults");
  const mobileToggle = document.getElementById("mobileSearchToggle");

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
      empty.textContent = "No students match your search.";
      results.append(empty);
    } else {
      matches.forEach((student) => {
        const button = document.createElement("button");
        button.className = "dropdown-item";
        button.type = "button";
        button.setAttribute("role", "option");
        button.innerHTML = `<span class="avatar">${student.initials}</span><span class="dropdown-item__content"><strong>${student.name}</strong><p>${student.registration} · ${student.className}</p></span>`;
        button.addEventListener("click", () => { input.value = student.name; closeSearch(); window.showDashboardToast?.(`${student.name} selected — profile route connects during PHP integration.`); });
        results.append(button);
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
    if (open) window.setTimeout(() => input.focus(), 50);
    else closeSearch();
  });
  document.addEventListener("click", (event) => { if (!search.contains(event.target)) closeSearch(); });
  document.addEventListener("keydown", (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") { event.preventDefault(); search.classList.add("is-mobile-open"); input.focus(); }
    if (event.key === "Escape") { closeSearch(); search.classList.remove("is-mobile-open"); mobileToggle?.setAttribute("aria-expanded", "false"); }
  });

  document.querySelector("[data-dashboard-refresh]")?.addEventListener("click", () => window.location.reload());
})();
