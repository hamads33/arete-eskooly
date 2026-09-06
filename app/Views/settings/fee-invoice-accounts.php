<?php
/** Approved static page content. Backend integration status is documented in docs/BACKEND-INTEGRATION-STATUS.md. */
?>
<main class="main" id="fee-invoice-accounts">
      <div class="main__inner">
        <div class="settings-page">
          <header class="settings-page__header"><div><nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>General Settings</span><i data-lucide="chevron-right"></i><span aria-current="page">Accounts for Fees Invoice</span></nav><h1>Accounts for Fees Invoice</h1><p>Manage bank details displayed on fee invoices and challans.</p></div><span class="page-security-badge"><i data-lucide="receipt-text"></i>Invoice details</span></header>
          <form class="settings-card" id="bankAccountForm" method="post" enctype="multipart/form-data" action="<?= url('/admin/settings/fee-invoice-accounts') ?>"><?= csrf_field() ?>
            <header class="settings-card__header"><div><span class="section-kicker">New account</span><h2>Add Bank</h2><p>Provide the bank details students and families should use for fee payments.</p></div></header>
            <div class="settings-card__body bank-form-layout">
              <!-- STATIC PREVIEW ONLY — replace with backend upload in PHP integration -->
              <div class="bank-logo-uploader"><span class="bank-logo-preview" id="bankLogoPreview"><i data-lucide="landmark"></i></span><div class="upload-control"><input id="bankLogo" name="bank_logo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" data-image-input data-preview-target="bankLogoPreview" required><label class="upload-control__button" for="bankLogo"><i data-lucide="image-up"></i>Choose Logo</label><span class="upload-control__hint">JPG or PNG · Maximum 500KB</span></div></div>
              <div class="settings-grid">
                <div class="form-field"><label class="form-label" for="bankName">Bank Name <span class="required-marker">*</span></label><div class="field-control"><i data-lucide="building-2"></i><input class="form-input" id="bankName" name="bank_name" type="text" placeholder="Your Bank Name" required></div></div>
                <div class="form-field"><label class="form-label" for="bankAccountNumber">Account Number <span class="required-marker">*</span></label><div class="field-control"><i data-lucide="hash"></i><input class="form-input" id="bankAccountNumber" name="account_number" type="text" placeholder="Bank Account No" inputmode="numeric" required></div></div>
                <div class="form-field form-field--wide"><label class="form-label" for="bankAddress">Bank / Branch Address <span class="required-marker">*</span></label><textarea class="form-input form-textarea" id="bankAddress" name="bank_address" placeholder="Bank Address" required></textarea></div>
                <div class="form-field form-field--wide"><label class="form-label" for="bankInstructions">Instructions</label><textarea class="form-input form-textarea" id="bankInstructions" name="instructions" placeholder="Write Instructions"></textarea></div>
              </div>
            </div>
            <footer class="settings-card__footer"><button class="btn btn--primary" type="submit"><i data-lucide="plus"></i>Add Bank</button></footer>
          </form>
          <section class="settings-card">
            <header class="settings-card__header bank-list-header"><div><span class="section-kicker">Invoice accounts</span><h2>Saved Bank Accounts</h2><p>Accounts currently available for fee invoices.</p></div><span class="count-badge" id="bankCount"><?= count($banks) ?> <?= count($banks)===1?'account':'accounts' ?></span></header>
            <div class="settings-card__body"><?php if(!$banks):?><div class="bank-empty" id="bankEmptyState"><div><i data-lucide="landmark"></i><strong>No banks found.</strong><p>Add your first bank account using the form above.</p></div></div><?php else:?><table class="responsive-table" id="bankAccountsTable"><thead><tr><th>Bank Name</th><th>Logo</th><th>Account No.</th><th>Actions</th></tr></thead><tbody id="bankTableBody"><?php foreach($banks as $bank):?><tr><td data-label="Bank"><?= e($bank['bank_name']) ?></td><td data-label="Logo"><img class="table-logo" src="<?= url('/'.$bank['logo_path']) ?>" alt=""></td><td data-label="Account No."><?= e($bank['account_number']) ?></td><td data-label="Actions"><form method="post" action="<?= url('/admin/settings/fee-invoice-accounts/'.$bank['id'].'/delete') ?>" onsubmit="return confirm('Delete this bank account?')"><?= csrf_field() ?><button class="danger-icon-button" type="submit" aria-label="Delete <?= e($bank['bank_name']) ?>"><i data-lucide="trash-2"></i></button></form></td></tr><?php endforeach ?></tbody></table><?php endif ?></div>
          </section>
        </div>
      </div>
    </main>

<div class="account-modal" id="deleteBankModal" role="dialog" aria-modal="true" aria-labelledby="deleteBankTitle" aria-describedby="deleteBankDescription" aria-hidden="true" hidden><button class="account-modal__backdrop" type="button" data-modal-close aria-label="Close bank deletion confirmation"></button><div class="account-modal__dialog"><span class="account-modal__icon"><i data-lucide="trash-2"></i></span><h2 id="deleteBankTitle">Delete this bank?</h2><p id="deleteBankDescription">This removes the bank only from the current frontend demo list. No backend data will be changed.</p><div class="account-modal__actions"><button class="btn btn--secondary" id="cancelDeleteBank" type="button">Cancel</button><button class="btn btn--danger" id="confirmDeleteBank" type="button">Delete Bank</button></div></div></div>
