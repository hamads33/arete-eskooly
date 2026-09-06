(function () {
  "use strict";
  const toast = message => window.showDashboardToast?.(message);

  document.querySelectorAll("[data-demo-submit]").forEach(form => form.addEventListener("submit", event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    toast(form.dataset.success || "Frontend demo completed. No live data was changed.");
  }));

  document.querySelectorAll("[data-demo-action]").forEach(control => {
    if (control.dataset.adminBound) return;
    control.dataset.adminBound = "true";
  });

  document.querySelectorAll("[data-tab-group]").forEach(group => {
    const buttons = [...group.querySelectorAll("[data-tab-target]")];
    const panels = [...document.querySelectorAll(`[data-tab-panel][data-tab-owner="${group.dataset.tabGroup}"]`)];
    buttons.forEach(button => button.addEventListener("click", () => {
      buttons.forEach(item => { const active=item===button; item.classList.toggle("is-active",active); item.setAttribute("aria-selected",String(active)); });
      panels.forEach(panel => panel.hidden=panel.dataset.tabPanel!==button.dataset.tabTarget);
    }));
  });

  document.querySelectorAll("[data-style-option]").forEach(button => button.addEventListener("click", () => {
    button.closest(".style-options").querySelectorAll("[data-style-option]").forEach(item=>item.classList.remove("is-active"));
    button.classList.add("is-active");
  }));

  const addSubjectButton=document.querySelector('[data-demo-action="Add subject row"]');
  const removeSubjectButton=document.querySelector('[data-demo-action="Remove last subject"]');
  if(addSubjectButton){
    const subjectGrid=addSubjectButton.closest("form").querySelector(".module-form-grid");
    addSubjectButton.addEventListener("click",()=>{
      const source=[...subjectGrid.children].slice(1,3);
      source.forEach((field,index)=>{
        const clone=field.cloneNode(true);const input=clone.querySelector("input");const label=clone.querySelector("label");
        input.value="";input.name=index===0?"subject_name[]":"subject_marks[]";input.removeAttribute("id");label.removeAttribute("for");subjectGrid.append(clone);
      });
    });
    removeSubjectButton?.addEventListener("click",()=>{if(subjectGrid.children.length>3){subjectGrid.lastElementChild.remove();subjectGrid.lastElementChild.remove();}});
  }

  function openModal(id) {
    const modal=document.getElementById(id); if (!modal) return;
    modal.hidden=false; modal.setAttribute("aria-hidden","false"); document.body.classList.add("no-scroll");
    window.setTimeout(()=>modal.querySelector("button:not([data-modal-close])")?.focus(),0);
  }
  function closeModal(modal) { modal.hidden=true; modal.setAttribute("aria-hidden","true"); document.body.classList.remove("no-scroll"); }
  document.querySelectorAll("[data-open-modal]").forEach(button=>button.addEventListener("click",()=>openModal(button.dataset.openModal)));
  document.querySelectorAll(".modal-shell").forEach(modal=>{
    modal.querySelectorAll("[data-modal-close]").forEach(button=>button.addEventListener("click",()=>closeModal(modal)));
    modal.querySelectorAll("[data-confirm-demo]").forEach(button=>button.addEventListener("click",()=>{closeModal(modal);toast(button.dataset.confirmDemo||"Confirmed for this frontend demo only.");}));
  });
  document.addEventListener("keydown",event=>{if(event.key==="Escape"){const open=[...document.querySelectorAll(".modal-shell:not([hidden])")].pop();if(open)closeModal(open);}});

  const feeMode=document.getElementById("feeCollectionMode");
  if(feeMode) document.querySelectorAll("[data-fee-mode]").forEach(button=>button.addEventListener("click",()=>{
    document.querySelectorAll("[data-fee-mode]").forEach(item=>item.classList.toggle("is-active",item===button));
    document.querySelectorAll("[data-fee-panel]").forEach(panel=>panel.hidden=panel.dataset.feePanel!==button.dataset.feeMode);
  }));

  const audience=document.getElementById("meetingAudience");
  function updateMeetingAudience(){
    if(!audience)return;
    const controls={SC:document.getElementById("meetingClass"),SS:document.getElementById("meetingStudent"),ST:document.getElementById("meetingTeacher")};
    Object.entries(controls).forEach(([value,control])=>{
      if(!control)return;
      const visible=audience.value===value;
      control.closest(".module-field").hidden=!visible;
      control.required=visible;
    });
  }
  audience?.addEventListener("change",updateMeetingAudience); updateMeetingAudience();
  const schedule=document.getElementById("scheduleMeeting");
  function updateSchedule(){const panel=document.getElementById("scheduleFields");if(panel){panel.hidden=!schedule.checked;panel.querySelectorAll("input").forEach(input=>input.required=schedule.checked);}}
  schedule?.addEventListener("change",updateSchedule); if(schedule)updateSchedule();

  const recipient=document.getElementById("certificateRecipientType");
  function updateRecipient(){
    if(!recipient)return;
    const controls={student:document.getElementById("certificateStudent"),staff:document.getElementById("certificateStaff")};
    Object.entries(controls).forEach(([value,control])=>{
      if(!control)return;
      const visible=recipient.value===value;
      control.closest(".module-field").hidden=!visible;
      control.disabled=!visible || control.options.length<2;
      control.required=visible;
    });
  }
  recipient?.addEventListener("change",updateRecipient); updateRecipient();

  const classDirectory=document.querySelector(".class-directory-page");
  if(classDirectory){
    const selects=[...classDirectory.querySelectorAll("[data-class-select]")];
    const selectAll=classDirectory.querySelector("[data-select-all-classes]");
    const bar=classDirectory.querySelector("[data-selection-bar]");
    const count=classDirectory.querySelector("[data-selection-count]");
    const updateSelection=()=>{const selected=selects.filter(input=>input.checked);if(bar){bar.hidden=selected.length===0;}if(count)count.textContent=`${selected.length} ${selected.length===1?"class":"classes"} selected`;if(selectAll){selectAll.checked=selected.length>0&&selected.length===selects.length;selectAll.indeterminate=selected.length>0&&selected.length<selects.length;}};
    selects.forEach(input=>input.addEventListener("change",updateSelection));
    selectAll?.addEventListener("change",()=>{selects.forEach(input=>{input.checked=selectAll.checked;});updateSelection();});
    updateSelection();
    const forceModal=document.getElementById("forceDeleteModal");let pendingForceForm=null;
    const closeForce=()=>{if(forceModal){forceModal.hidden=true;forceModal.setAttribute("aria-hidden","true");document.body.classList.remove("no-scroll");}};
    classDirectory.querySelector("[data-force-bulk]")?.addEventListener("click",()=>{pendingForceForm=document.getElementById("force-bulk-delete-form");if(!pendingForceForm||!selects.some(input=>input.checked))return;pendingForceForm.querySelectorAll("input[name='class_ids[]']").forEach(input=>input.remove());selects.filter(input=>input.checked).forEach(input=>{const clone=document.createElement("input");clone.type="hidden";clone.name="class_ids[]";clone.value=input.value;pendingForceForm.append(clone);});forceModal.hidden=false;forceModal.setAttribute("aria-hidden","false");document.body.classList.add("no-scroll");});
    classDirectory.querySelectorAll("[data-force-single]").forEach(button=>button.addEventListener("click",()=>{pendingForceForm=button.closest("form");forceModal.hidden=false;forceModal.setAttribute("aria-hidden","false");document.body.classList.add("no-scroll");}));
    forceModal?.querySelectorAll("[data-force-close]").forEach(control=>control.addEventListener("click",closeForce));forceModal?.querySelector("[data-force-confirm]")?.addEventListener("click",()=>pendingForceForm?.submit());
  }
  const studentDirectory=document.querySelector(".student-directory-page");
  if(studentDirectory){
    const selects=[...studentDirectory.querySelectorAll("[data-student-select]")];
    const selectAll=studentDirectory.querySelector("[data-select-all-students]");
    const bar=studentDirectory.querySelector("[data-student-selection-bar]");
    const count=studentDirectory.querySelector("[data-student-selection-count]");
    const updateSelection=()=>{const selected=selects.filter(input=>input.checked);if(bar)bar.hidden=selected.length===0;if(count)count.textContent=`${selected.length} ${selected.length===1?"student":"students"} selected`;if(selectAll){selectAll.checked=selected.length>0&&selected.length===selects.length;selectAll.indeterminate=selected.length>0&&selected.length<selects.length;}};
    selects.forEach(input=>input.addEventListener("change",updateSelection));
    selectAll?.addEventListener("change",()=>{selects.forEach(input=>{input.checked=selectAll.checked;});updateSelection();});
    updateSelection();
  }

  const accountHeadPage=document.querySelector("main#chart-of-accounts");
  if(accountHeadPage){
    const form=accountHeadPage.querySelector("form[data-demo-submit]");
    const tbody=accountHeadPage.querySelector("tbody");
    let pendingAccountRow=null;
    form.addEventListener("submit",event=>{
      if(!form.checkValidity())return;
      tbody.querySelector(".data-table__empty")?.remove();
      const row=document.createElement("tr");
      const id=String(tbody.children.length+1);const name=form.elements.head.value;const type=form.elements.type.value;
      [id,name,type].forEach(value=>{const cell=document.createElement("td");cell.textContent=value;row.append(cell);});
      const action=document.createElement("td");const button=document.createElement("button");button.className="danger-icon-button";button.type="button";button.setAttribute("aria-label",`Delete ${name}`);button.innerHTML='<i data-lucide="trash-2"></i>';button.addEventListener("click",()=>{pendingAccountRow=row;openModal("deleteHeadModal");});action.append(button);row.append(action);tbody.append(row);form.reset();window.lucide?.createIcons({attrs:{"stroke-width":1.9}});
    });
    document.querySelector("#deleteHeadModal [data-confirm-demo]")?.addEventListener("click",()=>{pendingAccountRow?.remove();pendingAccountRow=null;});
  }

  document.querySelectorAll("[data-char-counter]").forEach(field=>field.addEventListener("input",()=>{const output=document.getElementById(field.dataset.charCounter);if(output)output.textContent=String(Math.max(0,Number(field.maxLength)-field.value.length));}));
})();
