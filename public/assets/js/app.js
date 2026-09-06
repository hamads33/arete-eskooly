(function () {
  "use strict";

  if (window.lucide) window.lucide.createIcons({ attrs: { "stroke-width": 1.9 } });

  const toast = document.getElementById("toast");
  const toastMessage = document.getElementById("toastMessage");
  let toastTimer;
  window.showDashboardToast = function (message) {
    window.clearTimeout(toastTimer);
    toastMessage.textContent = message;
    toast.classList.add("is-visible");
    toastTimer = window.setTimeout(() => toast.classList.remove("is-visible"), 2800);
  };

  document.querySelectorAll("[data-demo-action]").forEach((control) => {
    control.addEventListener("click", (event) => {
      if (control.tagName === "A") event.preventDefault();
      window.showDashboardToast(`${control.dataset.demoAction} is undergoing testing and will be available soon.`);
    });
  });

  document.querySelectorAll('a[href="#"]').forEach((link) => {
    link.addEventListener("click", (event) => event.preventDefault());
  });

  const lockedModal = document.getElementById("lockedModuleModal");
  const lockedTitle = document.getElementById("lockedModuleTitle");
  document.querySelectorAll("[data-locked-module]").forEach((control) => {
    control.addEventListener("click", () => {
      lockedTitle.textContent = `${control.dataset.lockedModule} is undergoing testing`;
      lockedModal.hidden = false;
      lockedModal.setAttribute("aria-hidden", "false");
      document.body.classList.add("no-scroll");
    });
  });
  document.querySelectorAll("[data-locked-modal-close]").forEach((control) => control.addEventListener("click", () => {
    lockedModal.hidden = true;
    lockedModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("no-scroll");
  }));

  document.querySelectorAll("[data-language]").forEach((button) => {
    button.addEventListener("click", () => window.showDashboardToast(`${button.dataset.language} selected.`));
  });

  const fullscreenButton = document.getElementById("fullscreenButton");
  fullscreenButton?.addEventListener("click", async () => {
    try {
      if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
      else await document.exitFullscreen();
    } catch (error) {
      window.showDashboardToast("Fullscreen is unavailable in this browser context.");
    }
  });

  document.addEventListener("fullscreenchange", () => {
    fullscreenButton?.setAttribute("aria-label", document.fullscreenElement ? "Exit fullscreen" : "Enter fullscreen");
  });

  // Official report actions use the centralized server-side PDF engine.
  const reportPath = (() => {
    const path = window.location.pathname;
    const query = new URLSearchParams(window.location.search);
    const mappings = [
      [/\/admin\/students\/admission-letter$/, () => query.get("student_id") && `/admin/reports/admission-letter/${query.get("student_id")}/pdf`],
      [/\/admin\/employees\/job-letter$/, () => query.get("employee_id") && `/admin/reports/job-letter/${query.get("employee_id")}/pdf`],
      [/\/admin\/students\/report-card$/, () => query.get("student") && query.get("exam") && `/admin/reports/report-card/${query.get("student")}/pdf?exam=${query.get("exam")}`],
      [/\/admin\/students\/basic-list$/, () => `/admin/reports/students/basic-list/pdf?${query}`],
      [/\/admin\/attendance\/student-report$/, () => `/admin/reports/attendance/students/pdf?${query}`],
      [/\/admin\/attendance\/employee-report$/, () => `/admin/reports/attendance/employees/pdf?${query}`],
      [/\/admin\/reports\/admissions$/, () => `/admin/reports/admissions/pdf?${query}`],
      [/\/admin\/reports\/contacts$/, () => `/admin/reports/contacts/pdf?${query}`],
      [/\/admin\/accounts\/statement$/, () => `/admin/reports/accounts/statement/pdf?${query}`],
      [/\/admin\/salary\/pay$/, () => `/admin/reports/salary-sheet/pdf?month=${document.querySelector('[name="salary_month"]')?.value || new Date().toISOString().slice(0, 7)}`],
      [/\/admin\/salary\/receipt\/(\d+)$/, match => `/admin/reports/salary-slip/${match[1]}/pdf`],
      [/\/admin\/fees\/receipt\/(\d+)$/, match => `/admin/reports/fee-receipt/${match[1]}/pdf`],
    ];
    for (const [pattern, build] of mappings) { const match = path.match(pattern); if (match) return build(match); }
    return null;
  })();
  if (reportPath) {
    const header = document.querySelector(".module-page__header");
    if (header && !header.querySelector("[data-pdf-actions]")) {
      const separator = reportPath.includes("?") ? "&" : "?";
      const actions = document.createElement("div");
      actions.className = "filter-actions"; actions.dataset.pdfActions = "";
      actions.innerHTML = `<a class="btn btn--secondary" target="_blank" rel="noopener" href="${reportPath}">Preview PDF</a><a class="btn btn--primary" href="${reportPath}${separator}download=1">Download PDF</a>`;
      header.append(actions);
    }
  }
  if (/\/admin\/certificates$/.test(window.location.pathname)) {
    document.querySelectorAll(".data-table tbody tr").forEach(row => {
      const id = row.cells?.[0]?.textContent?.trim(); const actionCell = row.cells?.[5];
      if (!/^\d+$/.test(id || "") || !actionCell) return;
      const link = document.createElement("a"); link.className = "btn btn--secondary"; link.target = "_blank"; link.rel = "noopener"; link.href = `/admin/reports/certificate/${id}/pdf`; link.textContent = "PDF";
      actionCell.prepend(link);
    });
  }
  const templateMain = document.querySelector("[data-template-settings]");
  const templateGrid = templateMain?.querySelector("form .settings-grid");
  if (templateMain && templateGrid) {
    let settings = {}; try { settings = JSON.parse(templateMain.dataset.templateSettings || "{}"); } catch (_) {}
    const signatureFields = document.createElement("div"); signatureFields.className = "form-field form-field--wide";
    signatureFields.innerHTML = `<label class="form-label">Signature Labels</label><div class="settings-grid"><input class="form-input" name="signature_one" maxlength="60" placeholder="e.g. Principal"><input class="form-input" name="signature_two" maxlength="60" placeholder="e.g. Parent / Guardian"></div><p class="field-hint">Leave both blank to use the report's standard signatures.</p>`;
    signatureFields.querySelector('[name="signature_one"]').value = String(settings.signature_one || "");
    signatureFields.querySelector('[name="signature_two"]').value = String(settings.signature_two || "");
    templateGrid.append(signatureFields);
  }
})();
