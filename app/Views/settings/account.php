<?php
/** Approved static page content. Backend integration status is documented in docs/BACKEND-INTEGRATION-STATUS.md. */
?>
<main class="main" id="account-settings">
      <div class="main__inner">
        <div class="account-page">
          <header class="account-page__header">
            <div>
              <nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right" aria-hidden="true"></i><span>General Settings</span><i data-lucide="chevron-right" aria-hidden="true"></i><span aria-current="page">Account Settings</span></nav>
              <h1>Account Settings</h1>
              <p>Manage your account credentials, localization preferences and subscription information.</p>
            </div>
            <span class="page-security-badge"><i data-lucide="shield-check"></i>Secure account controls</span>
          </header>

          <div class="account-layout">
            <form class="account-card settings-form" id="accountSettingsForm" method="post" action="<?= url('/admin/settings/account') ?>"><?= csrf_field() ?>
              <section class="settings-section" aria-labelledby="accountAccessTitle">
                <div class="section-heading"><span class="section-heading__icon"><i data-lucide="key-round"></i></span><div><h2 id="accountAccessTitle">Account Access</h2><p>Update the credentials used to access the administrator account.</p></div></div>
                <div class="form-grid">
                  <div class="form-field">
                    <label class="form-label" for="accountEmail">Username / Email <span class="required-marker" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                    <div class="field-control"><i data-lucide="mail" aria-hidden="true"></i>
                      <!-- DEMO DATA: replace with authenticated PHP account email -->
                      <input class="form-input" id="accountEmail" name="account_email" type="email" value="<?= e(old('account_email', user()['email'] ?? '')) ?>" autocomplete="username" required>
                    </div>
                  </div>
                  <div class="form-field">
                    <label class="form-label" for="accountPassword">Password <span class="required-marker" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                    <!-- PHP INTEGRATION: preserve actual password/update behavior from backend -->
                    <div class="field-control field-control--password"><i data-lucide="lock-keyhole" aria-hidden="true"></i><input class="form-input" id="accountPassword" name="account_password" type="password" value="" autocomplete="new-password" minlength="8" maxlength="72"><button class="password-toggle" id="passwordToggle" type="button" aria-label="Show password" aria-pressed="false"><i data-lucide="eye" aria-hidden="true"></i></button></div>
                    <p class="field-help">Leave blank to keep the current password; new passwords require 8–72 characters.</p>
                  </div>
                </div>
              </section>

              <section class="settings-section" aria-labelledby="regionalPreferencesTitle">
                <div class="section-heading"><span class="section-heading__icon"><i data-lucide="globe-2"></i></span><div><h2 id="regionalPreferencesTitle">Regional Preferences</h2><p>Control how dates, times and monetary values appear.</p></div></div>
                <div class="form-grid">
                  <div class="form-field">
                    <label class="form-label" for="accountTimezone">Time Zone <span class="required-marker" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                    <div class="field-control field-control--select"><i data-lucide="clock-3" aria-hidden="true"></i><select class="form-select" id="accountTimezone" name="timezone" required>
                      <!-- DEMO OPTIONS: replace with actual backend-supported timezone list -->
                      <?php foreach(['Asia/Karachi','Asia/Dubai','Asia/Riyadh','Europe/London','America/New_York','UTC'] as $tz): ?><option value="<?= e($tz) ?>" <?= old('timezone',$institute['timezone']??'Asia/Karachi')===$tz?'selected':'' ?>><?= e($tz) ?></option><?php endforeach ?>
                    </select></div>
                  </div>
                  <div class="form-field">
                    <label class="form-label" for="accountCurrency">Currency <span class="required-marker" aria-hidden="true">*</span><span class="sr-only">(required)</span></label>
                    <div class="field-control field-control--select"><i data-lucide="coins" aria-hidden="true"></i><select class="form-select" id="accountCurrency" name="currency" required>
                      <!-- DEMO OPTIONS: replace with actual backend-supported currency list -->
                      <?php foreach(['PKR'=>'Pakistani Rupee','USD'=>'Dollars','SAR'=>'Saudi Riyal','AED'=>'UAE Dirham','GBP'=>'British Pound','EUR'=>'Euro'] as $code=>$label): ?><option value="<?= $code ?>" <?= old('currency',$institute['currency']??'PKR')===$code?'selected':'' ?>><?= e($label) ?> (<?= $code ?>)</option><?php endforeach ?>
                    </select></div>
                  </div>
                  <div class="form-field form-field--symbol">
                    <label class="form-label" for="currencySymbol">Currency Symbol</label>
                    <div class="field-control"><i data-lucide="badge-dollar-sign" aria-hidden="true"></i><input class="form-input" id="currencySymbol" name="currency_symbol" type="text" value="<?= e(old('currency_symbol',$institute['currency_symbol']??'Rs')) ?>" maxlength="8" autocomplete="off"></div>
                  </div>
                </div>
                <div class="settings-note"><i data-lucide="info" aria-hidden="true"></i><span>Changes to time zone and currency affect how information is displayed throughout the dashboard.</span></div>
              </section>

              <div class="settings-actions"><button class="btn btn--primary" type="submit"><i data-lucide="save"></i>Update Settings</button></div>
            </form>

            <aside class="account-card account-overview" aria-labelledby="accountOverviewTitle">
              <header class="account-overview__header"><h2 id="accountOverviewTitle">Account Overview</h2><p>Your current institute and plan details.</p></header>
              <div class="account-identity">
                <?php if(!empty($institute['logo_path'])):?><img class="account-logo" src="<?= url('/'.$institute['logo_path']) ?>" alt="<?= e($institute['name']??'Institute') ?> logo"><?php else:?><span class="account-logo"><i data-lucide="school"></i></span><?php endif ?>
                <h3><?= e($institute['name']??'Institute') ?></h3><p>Administrator Account</p><span class="account-identity__email"><?= e(user()['email']??'') ?></span>
              </div>
              <div class="account-details">
                <div class="account-detail"><span class="account-detail__icon"><i data-lucide="mail"></i></span><span class="account-detail__copy"><span>Username / Email</span><strong data-field="account-email"><?= e(user()['email']??'') ?></strong></span></div>
                <div class="account-detail"><span class="account-detail__icon"><i data-lucide="globe"></i></span><span class="account-detail__copy"><span>Timezone</span><strong><?= e($institute['timezone']??'Asia/Karachi') ?></strong></span></div>
                <div class="account-detail"><span class="account-detail__icon"><i data-lucide="badge-dollar-sign"></i></span><span class="account-detail__copy"><span>Currency</span><strong><?= e(($institute['currency']??'PKR').' ('.($institute['currency_symbol']??'Rs').')') ?></strong></span></div>
                <div class="account-detail"><span class="account-detail__icon"><i data-lucide="shield-check"></i></span><span class="account-detail__copy"><span>Account Status</span><strong>Active</strong></span><span class="status-badge">Active</span></div>
              </div>
            </aside>
          </div>

          <section class="danger-zone" aria-labelledby="dangerZoneTitle" hidden>
            <div class="danger-zone__content"><span class="danger-zone__icon"><i data-lucide="triangle-alert"></i></span><div><h2 id="dangerZoneTitle">Danger Zone</h2><p>Permanently delete this account and its associated access. This action may be irreversible after backend integration.</p></div></div>
            <button class="btn btn--danger" id="deleteAccountButton" type="button"><i data-lucide="trash-2"></i>Delete Account</button>
          </section>
        </div>
      </div>
    </main>

<div class="account-modal" id="deleteAccountModal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle" aria-describedby="deleteModalDescription" aria-hidden="true" hidden>
    <button class="account-modal__backdrop" type="button" data-modal-close aria-label="Close delete confirmation"></button>
    <div class="account-modal__dialog">
      <span class="account-modal__icon"><i data-lucide="trash-2"></i></span>
      <h2 id="deleteModalTitle">Delete Account?</h2>
      <p id="deleteModalDescription">This is a destructive action. Account deletion will be handled by the application backend during integration.</p>
      <div class="account-modal__actions"><button class="btn btn--secondary" id="cancelDeleteButton" type="button">Cancel</button><button class="btn btn--danger" id="confirmDeleteButton" type="button"><i data-lucide="trash-2"></i>Delete Account</button></div>
    </div>
  </div>
