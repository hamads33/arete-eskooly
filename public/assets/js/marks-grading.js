(function () {
  "use strict";
  const list = document.getElementById("gradingList");
  const template = document.getElementById("gradingRowTemplate");
  const warning = document.getElementById("gradingWarning");
  const modal = document.getElementById("deleteGradeModal");
  let pendingRow = null;
  // DEMO DATA — replace with PHP grading records.
  const demoGrades = JSON.parse(list.dataset.grades || "[]");

  function addRow(values = ["","","","PASS"], isNew = false) {
    const row = template.content.firstElementChild.cloneNode(true); row.dataset.new = String(isNew);
    const inputs = row.querySelectorAll("input"); inputs[0].value = values[0]; inputs[1].value = values[1]; inputs[2].value = values[2];
    const status = row.querySelector("select"); status.value = values[3]; status.dataset.status = values[3]; status.addEventListener("change", () => { status.dataset.status = status.value; validateRows(false); });
    row.querySelector("button").addEventListener("click", () => { if (row.dataset.new === "true" && !inputs[0].value) { row.remove(); validateRows(false); } else { pendingRow = row; modal.hidden = false; modal.setAttribute("aria-hidden", "false"); document.getElementById("cancelDeleteGrade").focus(); } });
    row.querySelectorAll("input").forEach(input => input.addEventListener("input", () => validateRows(false)));
    list.append(row);
    window.lucide?.createIcons({ attrs: { "stroke-width": 1.9 } });
  }
  function validateRows(showToast) {
    const rows = [...list.children]; let valid = true; const ranges = [];
    rows.forEach(row => {
      const [grade,from,to] = row.querySelectorAll("input"); const fromValue = Number(from.value); const toValue = Number(to.value); let message = "";
      if (!grade.value.trim()) message = "Grade is required.";
      else if (from.value === "" || to.value === "" || fromValue < 0 || fromValue > 100 || toValue < 0 || toValue > 100) message = "Use percentage values from 0 to 100.";
      else if (fromValue > toValue) message = "From percentage cannot exceed Upto percentage.";
      row.classList.toggle("has-error", Boolean(message)); row.querySelector(".grading-row__error").textContent = message; if (message) valid = false; else ranges.push({row,from:fromValue,to:toValue});
    });
    let overlap = false;
    for (let i=0;i<ranges.length;i+=1) for (let j=i+1;j<ranges.length;j+=1) if (Math.max(ranges[i].from,ranges[j].from) <= Math.min(ranges[i].to,ranges[j].to)) overlap = true;
    warning.classList.toggle("is-visible", overlap); if (overlap) valid = false;
    if (showToast) window.showDashboardToast?.(valid ? "Grading scale is ready to save." : "Review the highlighted grading fields.");
    return valid;
  }
  demoGrades.forEach(values => addRow(values));
  document.getElementById("addGradeButton")?.addEventListener("click", () => { addRow(undefined,true); list.lastElementChild.querySelector("input").focus(); });
  document.getElementById("gradingForm")?.addEventListener("submit", event => { if (!validateRows(false)) event.preventDefault(); });
  document.querySelectorAll("[data-grading-mode]").forEach(tab => tab.addEventListener("click", () => { const mode=tab.dataset.gradingMode; document.querySelectorAll("[data-grading-mode]").forEach(item => { const active=item===tab; item.classList.toggle("is-active",active); item.setAttribute("aria-selected",String(active)); }); document.getElementById("gradingBuilderPanel").hidden=mode!=="grading"; document.getElementById("failCriteriaPanel").hidden=mode!=="fail"; }));
  function closeModal() { modal.hidden=true; modal.setAttribute("aria-hidden","true"); pendingRow=null; }
  document.getElementById("cancelDeleteGrade")?.addEventListener("click", closeModal);
  modal.querySelector("[data-modal-close]")?.addEventListener("click", closeModal);
  document.getElementById("confirmDeleteGrade")?.addEventListener("click", () => { pendingRow?.remove(); closeModal(); validateRows(false); window.showDashboardToast?.("Grade removed. Save the scale to persist this change."); });
  document.addEventListener("keydown", event => { if (event.key === "Escape" && !modal.hidden) closeModal(); });
})();
