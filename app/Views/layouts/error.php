<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($pageTitle ?? 'Error') ?> · Greenfield Institute</title><link rel="stylesheet" href="<?= e(asset('css/variables.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/reset.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>"><style>body{display:grid;min-height:100vh;place-items:center;background:#f8f6f7;padding:24px}.error-card{max-width:560px;text-align:center;background:#fff;border:1px solid #eadde2;border-radius:18px;padding:48px;box-shadow:0 18px 45px rgba(84,9,37,.1)}.error-card h1{color:#6f1238;font-size:2rem;margin:14px 0}.error-card p{color:#6d6267;margin-bottom:24px}.error-card a{display:inline-flex;background:#7d1742;color:#fff;border-radius:10px;padding:12px 18px;text-decoration:none}</style></head>
<body><?= $content ?></body>
</html>
