<?php
/** Approved static page content. Backend integration status is documented in docs/BACKEND-INTEGRATION-STATUS.md. */
?>
<main class="main" id="institute-profile">
      <div class="main__inner">
        <form class="settings-page" id="instituteProfileForm" method="post" enctype="multipart/form-data" action="<?= url('/admin/settings/institute-profile') ?>"><?= csrf_field() ?>
          <header class="settings-page__header"><div><nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= url('/admin/dashboard') ?>">Dashboard</a><i data-lucide="chevron-right"></i><span>General Settings</span><i data-lucide="chevron-right"></i><span aria-current="page">Institute Profile</span></nav><h1>Institute Profile</h1><p>Manage your institute identity, contact information and location details.</p></div><span class="page-security-badge"><i data-lucide="building-2"></i>Institute identity</span></header>
          <div class="profile-layout">
            <aside class="settings-card brand-panel">
              <div class="brand-panel__top"></div>
              <div class="brand-panel__content">
                <!-- STATIC PREVIEW ONLY — replace with backend upload in PHP integration -->
                <span class="logo-preview" id="instituteLogoPreview"><?php if(!empty($institute['logo_path'])):?><img src="<?= url('/'.$institute['logo_path']) ?>" alt="Institute logo"><?php else:?><i data-lucide="school"></i><?php endif ?></span>
                <h2>Brand Identity</h2><p>Use a clear square logo for the best result.</p>
                <div class="upload-control"><input id="instituteLogo" name="institute_logo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" data-image-input data-preview-target="instituteLogoPreview"><label class="upload-control__button" for="instituteLogo"><i data-lucide="image-up"></i>Change Logo</label><span class="upload-control__hint">JPG or PNG · Maximum 500KB</span></div>
              </div>
            </aside>
            <div class="profile-form">
              <section class="settings-card"><header class="settings-card__header"><div><span class="section-kicker">Identity</span><h2>General Information</h2><p>The name and message presented across your institute workspace.</p></div></header><div class="settings-card__body"><div class="settings-grid">
                <div class="form-field"><label class="form-label" for="instituteName">Name of Institute <span class="required-marker">*</span></label><div class="field-control"><i data-lucide="school"></i><input class="form-input" id="instituteName" name="institute_name" type="text" value="<?= e(old('institute_name',$institute['name']??'')) ?>" autocomplete="organization" required></div></div>
                <div class="form-field"><label class="form-label" for="targetLine">Target Line <span class="required-marker">*</span></label><div class="field-control"><i data-lucide="quote"></i><input class="form-input" id="targetLine" name="target_line" type="text" value="<?= e(old('target_line',$institute['target_line']??'')) ?>" required></div></div>
              </div></div></section>
              <section class="settings-card"><header class="settings-card__header"><div><span class="section-kicker">Reach & location</span><h2>Contact Information</h2><p>Keep the contact details shown in official institute records current.</p></div></header><div class="settings-card__body"><div class="settings-grid">
                <div class="form-field"><label class="form-label" for="institutePhone">Phone Number <span class="required-marker">*</span></label><div class="field-control"><i data-lucide="phone"></i><input class="form-input" id="institutePhone" name="phone_number" type="tel" value="<?= e(old('phone_number',$institute['phone']??'')) ?>" autocomplete="tel" required></div></div>
                <div class="form-field"><label class="form-label" for="instituteWebsite">Website</label><div class="field-control"><i data-lucide="globe"></i><input class="form-input" id="instituteWebsite" name="website" type="url" placeholder="Website URL" value="<?= e(old('website',$institute['website']??'')) ?>" autocomplete="url"></div></div>
                <div class="form-field form-field--wide"><label class="form-label" for="instituteAddress">Address <span class="required-marker">*</span></label><textarea class="form-input form-textarea" id="instituteAddress" name="address" autocomplete="street-address" required><?= e(old('address',$institute['address']??'')) ?></textarea></div>
                <div class="form-field"><label class="form-label" for="instituteCountry">Country <span class="required-marker">*</span></label><div class="field-control field-control--select"><i data-lucide="map-pin"></i><select class="form-select" id="instituteCountry" name="country" required><?php foreach(['Pakistan','United Arab Emirates','Saudi Arabia','United Kingdom','United States'] as $country):?><option <?= old('country',$institute['country']??'Pakistan')===$country?'selected':'' ?>><?= e($country) ?></option><?php endforeach ?></select></div></div>
              </div></div><footer class="settings-card__footer"><button class="btn btn--primary" type="submit"><i data-lucide="save"></i>Update Profile</button></footer></section>
            </div>
          </div>
        </form>
      </div>
    </main>
