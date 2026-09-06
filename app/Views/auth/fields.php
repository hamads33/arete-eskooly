<?php foreach($fields as[$id,$label,$type,$autocomplete,$placeholder]): $message=error($id); ?>
<div class="auth-field"><label for="<?= e($id) ?>"><?= e($label) ?></label><div class="auth-control">
<input id="<?= e($id) ?>" name="<?= e($id) ?>" type="<?= e($type) ?>" autocomplete="<?= e($autocomplete) ?>" placeholder="<?= e($placeholder) ?>" <?= $type==='password'&&$autocomplete==='new-password'?'minlength="8" maxlength="72"':'' ?> value="<?= $type==='password'?'':e(old($id)) ?>" required <?= $message?'aria-invalid="true" aria-describedby="'.e($id).'-error"':'' ?>>
<?php if($type==='password'):?><button class="password-toggle" type="button" data-password-toggle="<?= e($id) ?>" aria-label="Show <?= e(strtolower($label)) ?>" aria-pressed="false"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/></svg></button><?php endif ?>
</div><?php if($message):?><p class="auth-error" role="alert" id="<?= e($id) ?>-error"><?= e($message) ?></p><?php endif ?></div>
<?php endforeach ?>
