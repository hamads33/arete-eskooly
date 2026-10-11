<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Opening <?= e($school) ?>…</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f7f7f8;font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;color:#211a1d}.box{text-align:center;padding:24px}.spin{width:34px;height:34px;margin:0 auto 14px;border:3px solid #f5e4e9;border-top-color:#7a1632;border-radius:50%;animation:s .8s linear infinite}@keyframes s{to{transform:rotate(360deg)}}button{margin-top:12px;border:0;border-radius:10px;background:#7a1632;color:#fff;padding:10px 18px;font:inherit;font-weight:650;cursor:pointer}</style></head>
<body><form class="box" method="post" action="<?= e($action) ?>" id="handoff">
<div class="spin" aria-hidden="true"></div>
<p>Signing you in to <strong><?= e($school) ?></strong> as its administrator…</p>
<input type="hidden" name="token" value="<?= e($token) ?>">
<noscript><button type="submit">Continue</button></noscript>
</form><script>document.getElementById('handoff').submit();</script></body></html>
