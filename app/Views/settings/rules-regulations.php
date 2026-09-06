<?php
/** Approved static page content. Backend integration status is documented in docs/BACKEND-INTEGRATION-STATUS.md. */
?>
<main class="main" id="rules-regulations">
      <div class="main__inner">
        <div class="settings-page">
          <header class="settings-page__header"><div><nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>General Settings</span><i data-lucide="chevron-right"></i><span aria-current="page">Rules &amp; Regulations</span></nav><h1>Rules &amp; Regulations</h1><p>Manage the policies printed on student admission and employee job letters.</p></div><span class="page-security-badge"><i data-lucide="file-text"></i>Policy editor</span></header>
          <form class="settings-card" id="rulesForm" method="post" action="<?= url('/admin/settings/rules-regulations') ?>"><?= csrf_field() ?><textarea hidden name="student_rules" id="studentRulesInput"><?= e($rules['student']??'') ?></textarea><textarea hidden name="employee_rules" id="employeeRulesInput"><?= e($rules['employee']??'') ?></textarea>
            <header class="settings-card__header"><div class="editor-tabs" role="tablist" aria-label="Rules type"><button class="editor-tab is-active" type="button" role="tab" aria-selected="true" data-rules-tab="student">Student Rules</button><button class="editor-tab" type="button" role="tab" aria-selected="false" data-rules-tab="employee">Employee Rules</button></div><span class="unsaved-indicator" id="rulesUnsaved" hidden>Unsaved changes</span></header>
            <div class="settings-card__body">
              <div class="rich-toolbar" role="toolbar" aria-label="Text formatting">
                <button class="toolbar-button" type="button" data-editor-command="bold" aria-label="Bold"><i data-lucide="bold"></i></button>
                <button class="toolbar-button" type="button" data-editor-command="underline" aria-label="Underline"><i data-lucide="underline"></i></button>
                <button class="toolbar-button" type="button" data-editor-command="italic" aria-label="Italic"><i data-lucide="italic"></i></button>
                <span class="toolbar-separator" aria-hidden="true"></span>
                <label class="sr-only" for="editorColor">Text highlight color</label><input class="toolbar-color" id="editorColor" type="color" value="#f3e4c4" title="Text highlight color">
                <label class="sr-only" for="editorFontSize">Font size</label><select class="toolbar-select" id="editorFontSize" title="Font size"><option value="2">Small</option><option value="3" selected>Normal</option><option value="4">Large</option><option value="5">Extra large</option></select>
                <span class="toolbar-separator" aria-hidden="true"></span>
                <button class="toolbar-button" type="button" data-editor-command="insertUnorderedList" aria-label="Bulleted list"><i data-lucide="list"></i></button>
                <button class="toolbar-button" type="button" data-editor-command="insertOrderedList" aria-label="Numbered list"><i data-lucide="list-ordered"></i></button>
              </div>
              <section data-rules-panel="student" role="tabpanel"><div class="editor-meta"><div><h2>Rules for Students</h2><p>Displayed on the Admission Letter.</p></div></div><!-- DEMO DATA — replace with PHP rules value --><div class="rich-editor" id="studentRulesEditor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Rules for Students"><p><strong>Student conduct and attendance</strong></p><ol><li>Students should attend classes regularly and arrive on time.</li><li>Institute property and fellow students must be treated with respect.</li><li>Parents or guardians should notify the office about extended absence.</li></ol></div></section>
              <section data-rules-panel="employee" role="tabpanel" hidden><div class="editor-meta"><div><h2>Rules for Employees</h2><p>Displayed on the Job Letter.</p></div></div><!-- DEMO DATA — replace with PHP rules value --><div class="rich-editor" id="employeeRulesEditor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Rules for Employees"><p><strong>Professional standards</strong></p><ul><li>Employees are expected to maintain professional conduct.</li><li>Attendance records and assigned responsibilities must remain current.</li><li>Confidential institute information must be handled appropriately.</li></ul></div></section>
            </div>
            <footer class="settings-card__footer"><div class="inline-info"><i data-lucide="info"></i><span>Student and employee rules are saved together.</span></div><button class="btn btn--primary" id="saveRulesButton" type="submit"><i data-lucide="save"></i>Save Changes</button></footer>
          </form>
        </div>
      </div>
    </main>
