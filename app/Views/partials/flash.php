<?php $notification = \App\Support\Flash::get(); ?>
<div class="toast<?= $notification ? ' is-visible' : '' ?>" id="toast" role="status" aria-live="polite"<?= $notification ? ' data-type="' . e($notification['type']) . '"' : '' ?>><i data-lucide="circle-check"></i><span id="toastMessage"><?= e($notification['message'] ?? 'Demo action') ?></span></div>
