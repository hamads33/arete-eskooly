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

  document.querySelector("[data-dashboard-refresh]")?.addEventListener("click", () => window.location.reload());
})();
