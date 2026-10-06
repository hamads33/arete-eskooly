(function () {
  'use strict';

  if (!window.aretePwa || !window.aretePwa.enabled) {
    var disabledInstallPage = document.querySelector('[data-pwa-install-page]');
    if (disabledInstallPage) {
      disabledInstallPage.querySelectorAll('[data-install-state]').forEach(function (element) {
        element.hidden = element.dataset.installState !== 'unavailable';
      });
    }
    return;
  }
  var status = document.querySelector('[data-pwa-status]');
  var installButton = document.querySelector('[data-pwa-install]');
  var installBanner = document.querySelector('[data-pwa-install-banner]');
  var laterButtons = document.querySelectorAll('[data-pwa-install-later]');
  var installPage = document.querySelector('[data-pwa-install-page]');
  var pageInstallButton = document.querySelector('[data-pwa-page-install]');
  var recheckButton = document.querySelector('[data-pwa-recheck]');
  var installFeedback = document.querySelector('[data-install-feedback]');
  var installPrompt = window.__areteInstallPrompt || null;
  var statusTimer = null;
  var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var mobile = (window.navigator.userAgentData && window.navigator.userAgentData.mobile === true)
    || /android|iphone|ipad|ipod/i.test(window.navigator.userAgent)
    || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
  var userAgent = window.navigator.userAgent || '';
  var isIos = /iphone|ipad|ipod/i.test(userAgent)
    || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
  var isIosSafari = isIos && /safari/i.test(userAgent) && !/(crios|fxios|edgios|opios)/i.test(userAgent);
  var isInAppBrowser = /(FBAN|FBAV|Instagram|Messenger|WhatsApp|Line\/|; wv\)|\bwv\b)/i.test(userAgent);

  function showInstallState(name) {
    if (!installPage) return;
    installPage.querySelectorAll('[data-install-state]').forEach(function (element) {
      element.hidden = element.dataset.installState !== name;
    });
  }

  function showInstallFeedback(message) {
    if (!installFeedback) return;
    installFeedback.textContent = message;
    installFeedback.hidden = !message;
  }

  // The installed app and browser tabs share storage on the same origin, so a launch of the
  // installed app lets a later browser tab know the app exists. Browsers never offer the install
  // prompt for an installed app, which otherwise left this page stuck on "unavailable".
  var INSTALLED_KEY = 'arete-pwa-installed';
  function rememberInstalled(value) {
    try { value ? window.localStorage.setItem(INSTALLED_KEY, '1') : window.localStorage.removeItem(INSTALLED_KEY); } catch (error) {}
  }
  function knownInstalled() {
    try { return window.localStorage.getItem(INSTALLED_KEY) === '1'; } catch (error) { return false; }
  }
  if (standalone) rememberInstalled(true);
  var relatedInstalled = false;
  if (!standalone && navigator.getInstalledRelatedApps) {
    navigator.getInstalledRelatedApps().then(function (apps) {
      if (apps && apps.length) { relatedInstalled = true; updateInstallPage(); }
    }).catch(function () {});
  }

  function updateInstallPage() {
    if (!installPage) return;
    standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var desktopGuide = document.querySelector('[data-install-desktop-guide]');
    if (desktopGuide) desktopGuide.hidden = mobile;
    if (standalone) {
      showInstallState('installed');
    } else if (installPrompt) {
      showInstallState('available');
    } else if (relatedInstalled || (!mobile && knownInstalled())) {
      showInstallState('installed-browser');
    } else if (isInAppBrowser || (isIos && !isIosSafari)) {
      var guidance = document.querySelector('[data-install-browser-guidance]');
      if (guidance) guidance.textContent = isIos
        ? 'Open this page in Safari to add the app to your Home Screen.'
        : 'Open this page in Chrome to install the app.';
      showInstallState('open-browser');
    } else if (isIos) {
      showInstallState('ios');
    } else {
      showInstallState('unavailable');
    }
  }

  async function requestInstall() {
    if (!installPrompt) {
      updateInstallPage();
      return false;
    }
    var prompt = installPrompt;
    installPrompt = null;
    window.__areteInstallPrompt = null;
    if (pageInstallButton) pageInstallButton.disabled = true;
    try {
      await prompt.prompt();
      var choice = await prompt.userChoice;
      if (choice.outcome === 'accepted') {
        showInstallFeedback('');
        showInstallState('accepted');
      } else {
        showInstallFeedback('Installation was dismissed. You can use the browser menu to install, or check again.');
        showInstallState('unavailable');
      }
      return choice.outcome === 'accepted';
    } catch (error) {
      showInstallFeedback('The installation prompt is not available right now. Try again from your browser menu.');
      showInstallState('unavailable');
      return false;
    } finally {
      if (pageInstallButton) pageInstallButton.disabled = false;
    }
  }

  // A new window followed by a server redirect is not reliably handed off to the Google Meet
  // app from an installed mobile PWA. Keep the secure LMS join endpoint, but navigate to it in
  // this PWA window so the resulting meet.google.com redirect can use the OS app-link handler.
  if (standalone && mobile) {
    document.querySelectorAll('a[href*="/portal/student/live-classes/"][href$="/join"]').forEach(function (link) {
      link.removeAttribute('target');
    });
  }

  // "Later" / × hides the banner for this session and keeps it away for a week on this device.
  var DISMISS_DAYS = 7;
  var installDismissed = false;
  try { installDismissed = window.sessionStorage.getItem('arete-install-later') === '1'; } catch (error) {}
  try { installDismissed = installDismissed || Number(window.localStorage.getItem('arete-install-later-until') || 0) > Date.now(); } catch (error) {}
  function dismissInstallBanner() {
    installDismissed = true;
    if (installBanner) installBanner.hidden = true;
    try { window.sessionStorage.setItem('arete-install-later', '1'); } catch (error) {}
    try { window.localStorage.setItem('arete-install-later-until', String(Date.now() + DISMISS_DAYS * 86400000)); } catch (error) {}
  }
  if (installBanner) installBanner.hidden = standalone || installDismissed;

  function announce(message, state, persist) {
    if (!status) return;
    window.clearTimeout(statusTimer);
    status.textContent = message;
    status.dataset.state = state;
    status.hidden = false;
    if (!persist) statusTimer = window.setTimeout(function () { status.hidden = true; }, 3000);
  }

  function applyNetworkState() {
    var offline = !navigator.onLine;
    document.documentElement.classList.toggle('is-offline', offline);
    document.querySelectorAll('form button[type="submit"], form input[type="submit"]').forEach(function (button) {
      button.classList.toggle('pwa-network-disabled', offline);
      button.setAttribute('aria-disabled', offline ? 'true' : 'false');
    });
    if (offline) announce("You're offline. Live data and actions are temporarily unavailable.", 'offline', true);
  }

  window.addEventListener('offline', applyNetworkState);
  window.addEventListener('online', function () {
    applyNetworkState();
    announce('Connection restored.', 'online', false);
  });
  document.addEventListener('submit', function (event) {
    if (!navigator.onLine) {
      event.preventDefault();
      announce('Internet connection is required to submit this form.', 'offline', true);
    }
  }, true);
  document.addEventListener('click', function (event) {
    if (navigator.onLine) return;
    var link = event.target.closest('a[href]');
    if (!link) return;
    var href = link.getAttribute('href') || '';
    var networkOnly = link.hasAttribute('download') || /(?:\/live-classes\/.*\/join|\/pdf(?:\?|$)|\/reports\/|meet\.google\.com)/i.test(href);
    if (networkOnly) {
      event.preventDefault();
      announce('This action needs an internet connection.', 'offline', true);
    }
  }, true);
  document.addEventListener('change', function (event) {
    if (!navigator.onLine && event.target instanceof HTMLInputElement && event.target.type === 'file') {
      event.target.value = '';
      announce('Internet connection required to upload this file.', 'offline', true);
    }
  });

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    installPrompt = event;
    window.__areteInstallPrompt = event;
    rememberInstalled(false);
    if (!standalone && !installDismissed && installBanner) installBanner.hidden = false;
    updateInstallPage();
  });
  laterButtons.forEach(function (button) { button.addEventListener('click', dismissInstallBanner); });
  if (installBanner) {
    var swipeY = null;
    installBanner.addEventListener('touchstart', function (event) { swipeY = event.touches.length === 1 ? event.touches[0].clientY : null; }, { passive: true });
    installBanner.addEventListener('touchmove', function (event) {
      if (swipeY === null) return;
      var moved = Math.max(0, event.touches[0].clientY - swipeY);
      installBanner.style.transform = moved ? 'translateY(' + moved + 'px)' : '';
      installBanner.style.opacity = moved ? String(Math.max(0.3, 1 - moved / 160)) : '';
    }, { passive: true });
    installBanner.addEventListener('touchend', function (event) {
      if (swipeY === null) return;
      var moved = event.changedTouches[0].clientY - swipeY;
      installBanner.style.transform = '';
      installBanner.style.opacity = '';
      swipeY = null;
      if (moved > 50) dismissInstallBanner();
    });
  }
  installButton && installButton.addEventListener('click', async function () {
    if (!installPrompt) {
      window.location.href = '/install';
      return;
    }
    if (installBanner) installBanner.hidden = true;
    var accepted = await requestInstall();
    if (!accepted && !standalone && installBanner) installBanner.hidden = false;
  });
  pageInstallButton && pageInstallButton.addEventListener('click', requestInstall);
  recheckButton && recheckButton.addEventListener('click', function () { window.location.reload(); });
  window.addEventListener('appinstalled', function () {
    rememberInstalled(true);
    standalone = true;
    installPrompt = null;
    window.__areteInstallPrompt = null;
    if (installBanner) installBanner.hidden = true;
    showInstallFeedback('');
    showInstallState('installed');
  });

  if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
    navigator.serviceWorker.register('/sw.js', {scope: '/', updateViaCache: 'none'}).catch(function () {
      // Normal browser use must remain unaffected if registration is unavailable.
    });
  }
  applyNetworkState();
  if (installPage) {
    if (standalone || isIos || isInAppBrowser || installPrompt) {
      updateInstallPage();
    } else {
      window.setTimeout(updateInstallPage, 1200);
    }
  }
}());
