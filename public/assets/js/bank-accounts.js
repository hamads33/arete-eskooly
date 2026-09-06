(function () {
  "use strict";
  const form = document.getElementById("bankAccountForm");
  const body = document.getElementById("bankTableBody");
  const empty = document.getElementById("bankEmptyState");
  const table = document.getElementById("bankAccountsTable");
  const count = document.getElementById("bankCount");
  const preview = document.getElementById("bankLogoPreview");
  const modal = document.getElementById("deleteBankModal");
  let rowToDelete = null;

  function updateEmptyState() {
    const total = body.children.length;
    count.textContent = `${total} ${total === 1 ? "account" : "accounts"}`;
    empty.hidden = total > 0;
    table.hidden = total === 0;
  }
  function createCell(label, content) {
    const cell = document.createElement("td"); cell.dataset.label = label;
    if (content instanceof Node) cell.append(content); else cell.textContent = content;
    return cell;
  }
  function requestDelete(row) { rowToDelete = row; modal.hidden = false; modal.setAttribute("aria-hidden", "false"); document.getElementById("cancelDeleteBank").focus(); }
  function closeDelete() { modal.hidden = true; modal.setAttribute("aria-hidden", "true"); rowToDelete = null; }

  // STATIC DEMO ONLY — replace with backend persistence in PHP integration.
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const row = document.createElement("tr");
    const bankWrap = document.createElement("span"); bankWrap.className = "table-bank";
    const logo = document.createElement("img"); logo.className = "table-logo"; logo.alt = "";
    logo.src = preview.querySelector("img")?.src || "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 80 80'%3E%3Crect width='80' height='80' rx='16' fill='%23faf3f5'/%3E%3Cpath d='M15 34h50L40 17 15 34Zm5 5v22m13-22v22m14-22v22m13-22v22M14 65h52' stroke='%237a1632' stroke-width='4' fill='none'/%3E%3C/svg%3E";
    const bankName = document.createElement("span"); bankName.textContent = document.getElementById("bankName").value;
    bankWrap.append(logo, bankName);
    const deleteControl = document.createElement("button"); deleteControl.type = "button"; deleteControl.className = "danger-icon-button"; deleteControl.setAttribute("aria-label", `Delete ${bankName.textContent}`); deleteControl.innerHTML = '<i data-lucide="trash-2"></i>'; deleteControl.addEventListener("click", () => requestDelete(row));
    row.append(createCell("Bank", bankWrap), createCell("Logo", "Uploaded"), createCell("Account No.", document.getElementById("bankAccountNumber").value), createCell("Actions", deleteControl));
    body.append(row); window.lucide?.createIcons({ attrs: { "stroke-width": 1.9 } }); form.reset(); preview.innerHTML = '<i data-lucide="landmark"></i>'; window.lucide?.createIcons({ attrs: { "stroke-width": 1.9 } }); updateEmptyState(); window.showDashboardToast?.("Bank added to the frontend demo list.");
  });
  document.getElementById("cancelDeleteBank")?.addEventListener("click", closeDelete);
  modal.querySelector("[data-modal-close]")?.addEventListener("click", closeDelete);
  document.getElementById("confirmDeleteBank")?.addEventListener("click", () => { rowToDelete?.remove(); closeDelete(); updateEmptyState(); window.showDashboardToast?.("Demo bank removed from this page only."); });
  document.addEventListener("keydown", event => { if (event.key === "Escape" && !modal.hidden) closeDelete(); });
  updateEmptyState();
})();
