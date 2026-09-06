(function () {
  "use strict";
  const form = document.getElementById("feeParticularsForm");
  const monthMode = document.getElementById("feeMonthMode");
  const monthField = document.getElementById("specificMonthField");
  const monthSelect = document.getElementById("specificMonth");
  const scope = document.getElementById("feeScope");
  const classField = document.getElementById("classScopeField");
  const classSelect = document.getElementById("scopeClass");
  const studentField = document.getElementById("studentScopeField");
  const studentSelect = document.getElementById("scopeStudent");
  const periodSummary = document.getElementById("periodSummary");
  const audienceSummary = document.getElementById("audienceSummary");
  const modal = document.getElementById("resetFeesModal");
  const initialValues = [...form.querySelectorAll("input[type='number']")].map(input => input.value);

  function updateConditions() {
    const specificMonth = monthMode.value === "specific";
    monthField.hidden = !specificMonth;
    monthSelect.required = specificMonth;
    const classScope = scope.value === "class";
    const studentScope = scope.value === "student";
    classField.hidden = !(classScope || studentScope);
    studentField.hidden = !studentScope;
    classSelect.required = classScope || studentScope;
    studentSelect.required = studentScope;
    periodSummary.textContent = specificMonth ? monthSelect.options[monthSelect.selectedIndex].text : "All Months";
    audienceSummary.textContent = scope.options[scope.selectedIndex].text;
  }
  [monthMode, monthSelect, scope].forEach(control => control.addEventListener("change", updateConditions));
  updateConditions();

  document.getElementById("feeHelpButton")?.addEventListener("click", () => window.showDashboardToast?.("Student settings take priority over Class, then All Students."));
  document.getElementById("openResetFees")?.addEventListener("click", () => { modal.hidden = false; modal.setAttribute("aria-hidden", "false"); document.getElementById("cancelResetFees").focus(); });
  function closeModal() { modal.hidden = true; modal.setAttribute("aria-hidden", "true"); document.getElementById("openResetFees").focus(); }
  document.getElementById("cancelResetFees")?.addEventListener("click", closeModal);
  modal.querySelector("[data-modal-close]")?.addEventListener("click", closeModal);
  document.getElementById("confirmResetFees")?.addEventListener("click", () => {
    form.querySelectorAll("input[type='number']").forEach((input,index) => { input.value = initialValues[index]; });
    closeModal();
    window.showDashboardToast?.("Unsaved values reset. Save Changes to persist the displayed amounts.");
  });
  document.addEventListener("keydown", event => { if (event.key === "Escape" && !modal.hidden) closeModal(); });
})();
