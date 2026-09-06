(function () {
  "use strict";
  const tabs = [...document.querySelectorAll("[data-rules-tab]")];
  const panels = [...document.querySelectorAll("[data-rules-panel]")];
  const unsaved = document.getElementById("rulesUnsaved");
  let activePanel = panels[0];

  function activateRules(name) {
    tabs.forEach(tab => { const active = tab.dataset.rulesTab === name; tab.classList.toggle("is-active", active); tab.setAttribute("aria-selected", String(active)); });
    panels.forEach(panel => { const active = panel.dataset.rulesPanel === name; panel.hidden = !active; if (active) activePanel = panel; });
  }
  tabs.forEach(tab => tab.addEventListener("click", () => activateRules(tab.dataset.rulesTab)));
  panels.forEach(panel => panel.querySelector(".rich-editor").addEventListener("input", () => { unsaved.hidden = false; }));
  document.querySelectorAll("[data-editor-command]").forEach(button => button.addEventListener("click", () => { activePanel.querySelector(".rich-editor").focus(); document.execCommand(button.dataset.editorCommand, false); }));
  document.getElementById("editorFontSize")?.addEventListener("change", event => { activePanel.querySelector(".rich-editor").focus(); document.execCommand("fontSize", false, event.target.value); });
  document.getElementById("editorColor")?.addEventListener("input", event => { activePanel.querySelector(".rich-editor").focus(); document.execCommand("hiliteColor", false, event.target.value); });
  if (document.getElementById("studentRulesInput").value) document.getElementById("studentRulesEditor").innerHTML=document.getElementById("studentRulesInput").value;
  if (document.getElementById("employeeRulesInput").value) document.getElementById("employeeRulesEditor").innerHTML=document.getElementById("employeeRulesInput").value;
  document.getElementById("rulesForm")?.addEventListener("submit", () => { document.getElementById("studentRulesInput").value=document.getElementById("studentRulesEditor").innerHTML; document.getElementById("employeeRulesInput").value=document.getElementById("employeeRulesEditor").innerHTML; });
  activateRules("student");
})();
