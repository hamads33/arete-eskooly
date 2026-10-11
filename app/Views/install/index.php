<main class="install-main">
  <section class="install-card" aria-labelledby="install-title" data-pwa-install-page>
    <a class="install-brand" href="<?= e(url('/')) ?>" aria-label="<?= e(brand()['short']) ?> home">
      <span class="install-brand__mark"><img src="<?= e(brand()['logo']) ?>" width="104" height="104" alt="<?= e(brand()['short']) ?>"></span>
      <span><?= e(mb_strtoupper(brand()['name'])) ?></span>
      <?php if(brand()['caption']!==''):?><small><?= e(brand()['caption']) ?></small><?php endif ?>
    </a>

    <div class="install-copy">
      <h1 id="install-title">Install the app on your device</h1>
      <p>Get faster access and launch your school workspace directly from your home screen.</p>
    </div>

    <div class="install-state" data-install-state="loading" aria-live="polite" aria-atomic="true">
      <span class="install-loader" aria-hidden="true"></span>
      <p>Checking installation options&hellip;</p>
    </div>

    <div class="install-state" data-install-state="available" hidden>
      <button class="install-primary" type="button" data-pwa-page-install>
        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3v12m0 0 5-5m-5 5-5-5M5 20h14"/></svg>
        Install App
      </button>
      <p class="install-hint">Your browser will ask you to confirm.</p>
    </div>

    <div class="install-state install-state--success" data-install-state="installed" hidden>
      <span class="install-check" aria-hidden="true">&#10003;</span>
      <div><strong>App Installed</strong><p>Open <?= e(brand()['short']) ?> normally from your device.</p></div>
    </div>

    <div class="install-state install-state--success" data-install-state="accepted" hidden>
      <span class="install-check" aria-hidden="true">&#10003;</span>
      <div><strong>Installation started</strong><p>Follow your browser's confirmation to finish.</p></div>
    </div>

    <div class="install-state install-state--guide" data-install-state="ios" hidden>
      <h2>Install on iPhone or iPad</h2>
      <ol>
        <li><span aria-hidden="true">1</span><p>Tap the <strong>Share</strong> button in Safari.</p></li>
        <li><span aria-hidden="true">2</span><p>Select <strong>Add to Home Screen</strong>.</p></li>
        <li><span aria-hidden="true">3</span><p>Tap <strong>Add</strong> to finish.</p></li>
      </ol>
    </div>

    <div class="install-state install-state--guide" data-install-state="open-browser" hidden>
      <h2>Open in your browser to install</h2>
      <p data-install-browser-guidance>Open this page in Chrome to install the app.</p>
      <p class="install-hint">Use this browser's menu and choose “Open in browser” or copy this page's link.</p>
    </div>

    <div class="install-state install-state--success" data-install-state="installed-browser" hidden>
      <span class="install-check" aria-hidden="true">&#10003;</span>
      <div><strong>Already installed on this device</strong><p>Open <?= e(brand()['short']) ?> from your desktop or Start menu, or click <strong>Open in app</strong> in the browser's address bar.</p></div>
    </div>

    <div class="install-state install-state--guide" data-install-state="unavailable" hidden>
      <span class="install-state__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 3v11m0 0 4-4m-4 4-4-4M5 20h14"/></svg>
      </span>
      <h2>Install prompt unavailable</h2>
      <p class="install-state__intro">Your browser has not offered one-click installation on this page. If <?= e(brand()['short']) ?> is already installed, open it from your desktop or Start menu.</p>
      <p class="install-state__intro" data-install-desktop-guide hidden>To install manually in Chrome or Edge, click the install icon at the right end of the address bar, or open the browser menu (&#8942;) and choose <strong>Cast, save and share &rarr; Install page as app</strong>.</p>
      <button class="install-secondary install-secondary--full" type="button" data-pwa-recheck>Try Again</button>
    </div>

    <p class="install-feedback" data-install-feedback role="status" aria-live="polite" hidden></p>

    <div class="install-benefits" aria-label="App benefits">
      <span><i aria-hidden="true">&#10003;</i> Secure</span>
      <span><i aria-hidden="true">&#10003;</i> Fast</span>
      <span><i aria-hidden="true">&#10003;</i> Easy to access</span>
    </div>
  </section>
  <p class="install-footnote">One connected workspace for your school.</p>
</main>
