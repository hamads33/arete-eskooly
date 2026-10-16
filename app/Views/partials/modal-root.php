<div id="globalModalRoot" aria-live="polite">
<?php $lockInfo = \App\Platform\Features::lockMessage(); ?>
  <div id="planLockInfo" hidden data-title="<?= e($lockInfo['title']) ?>" data-message="<?= e($lockInfo['message']) ?>" data-whatsapp="<?= e($lockInfo['whatsapp']) ?>" data-school="<?= e((string) getenv('APP_NAME')) ?>" data-button="<?= e($lockInfo['button']) ?>"></div>
  <div class="modal-shell locked-module-modal" id="lockedModuleModal" role="dialog" aria-modal="true" aria-labelledby="lockedModuleTitle" aria-hidden="true" hidden>
    <div class="modal-shell__backdrop" data-locked-modal-close></div>
    <div class="modal-shell__dialog locked-module-modal__dialog">
      <button class="modal-close" type="button" data-locked-modal-close aria-label="Close"><i data-lucide="x"></i></button>
      <div class="locked-module-modal__icon"><i data-lucide="timer"></i></div>
      <span class="section-kicker">Coming soon</span>
      <h2 id="lockedModuleTitle">This module is undergoing testing</h2>
      <p id="lockedModuleMessage">It will be live soon. Thank you for your patience.</p>
      <button class="btn btn--primary" type="button" data-locked-modal-close>Got it</button>
    </div>
  </div>
  <script>
    window.showLockedModule = function (name) {
      var modal = document.getElementById('lockedModuleModal');
      var title = document.getElementById('lockedModuleTitle');
      if (!modal || !title) return;
      title.textContent = name + ' is undergoing testing';
      modal.hidden = false;
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('no-scroll');
    };
    document.addEventListener('click', function (event) {
      var control = event.target.closest('[data-locked-module]');
      if (control) {
        event.preventDefault();
        window.showLockedModule(control.getAttribute('data-locked-module'), control.getAttribute('data-locked-reason'));
      }
      var demoControl = event.target.closest('[data-'+'demo-action],[data-confirm-demo]');
      if (demoControl) {
        event.preventDefault();
        event.stopImmediatePropagation();
        window.showLockedModule(demoControl.getAttribute('data-demo-action') || 'This feature');
      }
    });
    document.addEventListener('submit', function(event){
      var demoForm=event.target.closest('[data-'+'demo-submit],[data-demo-form]');
      if(!demoForm)return;
      event.preventDefault();
      event.stopImmediatePropagation();
      var heading=document.querySelector('.module-page__header h1, main h1');
      window.showLockedModule(heading ? heading.textContent.trim() : 'This feature');
    },true);
  </script>
</div>
