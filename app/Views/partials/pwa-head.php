  <meta name="theme-color" content="#7A1632">
  <meta name="application-name" content="Arete">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Arete">
  <link rel="manifest" href="<?= e(url('/manifest.webmanifest?v=2')) ?>">
  <link rel="apple-touch-icon" sizes="192x192" href="<?= e(url('/assets/pwa/arete-icon-192-v2.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/pwa.css?v=1')) ?>">
  <script>window.addEventListener('beforeinstallprompt',function(event){event.preventDefault();window.__areteInstallPrompt=event;});</script>
