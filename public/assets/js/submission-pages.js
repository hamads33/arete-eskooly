// Answer pages for online tests: in-page camera capture (no leaving the app, so an installed PWA
// is never reloaded to the dashboard), a reviewable page list (view / retake / replace / reorder /
// remove), drafts kept on the device (IndexedDB) and an upload that keeps every page until the
// server confirms. Without JavaScript the plain <input type=file> form still works.
(() => {
  'use strict';

  const MAX_DIM = 2400;          // longest side of a stored photo, enough for handwriting
  const QUALITY = 0.85;
  const MAX_FILE = 5 * 1024 * 1024;        // the server's per-file rule (SecureAttachment)
  const MAX_TOTAL = 28 * 1024 * 1024;      // post_max_size is 30 MB for the whole form
  const MAX_FILES = 40;                    // max_file_uploads
  const DRAFT_DAYS = 7;
  const ALLOWED = ['image/jpeg', 'image/png', 'application/pdf'];

  const $ = (sel, root = document) => root.querySelector(sel);
  const h = (tag, attrs = {}, ...kids) => {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (v == null || v === false) continue;
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else el.setAttribute(k, v === true ? '' : v);
    }
    kids.flat().forEach(kid => kid != null && el.append(kid));
    return el;
  };
  const icon = name => h('i', { 'data-lucide': name, 'aria-hidden': 'true' });
  const btn = (label, iconName, cls, onclick, extra = {}) => h('button', { type: 'button', class: 'btn ' + cls, onclick, ...extra }, iconName ? icon(iconName) : null, h('span', { text: label }));
  const paintIcons = () => window.lucide?.createIcons({ attrs: { 'stroke-width': 1.9 } });
  const size = b => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
  const plural = (n, one, many) => n + ' ' + (n === 1 ? one : many);
  const uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
  const toast = msg => { const t = document.getElementById('toast'); if (t) t.dataset.type = 'info'; window.showDashboardToast?.(msg); };

  // ---------------------------------------------------------------- drafts (IndexedDB)
  const drafts = (() => {
    let dbp = null;
    const open = () => dbp ||= new Promise((resolve, reject) => {
      if (!('indexedDB' in window)) return reject(new Error('no idb'));
      const req = indexedDB.open('arete-submissions', 1);
      req.onupgradeneeded = () => req.result.createObjectStore('drafts', { keyPath: 'key' });
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
    const tx = async (mode, fn) => {
      try {
        const db = await open();
        return await new Promise((resolve, reject) => {
          const t = db.transaction('drafts', mode), store = t.objectStore('drafts');
          const out = fn(store);
          t.oncomplete = () => resolve(out && 'result' in out ? out.result : undefined);
          t.onerror = t.onabort = () => reject(t.error);
        });
      } catch { return undefined; } // private mode / blocked storage: drafts are a bonus only
    };
    return {
      get: key => tx('readonly', s => s.get(key)),
      put: rec => tx('readwrite', s => s.put(rec)),
      remove: key => tx('readwrite', s => s.delete(key)),
      all: () => tx('readonly', s => s.getAll()),
    };
  })();

  // ---------------------------------------------------------------- images
  const loadImage = blob => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(blob), img = new Image();
    img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
    img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('This picture could not be opened.')); };
    img.src = url;
  });
  const encode = (source, w, h2, quality) => new Promise(resolve => {
    const canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h2;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h2);
    ctx.drawImage(source, 0, 0, w, h2);
    canvas.toBlob(b => { canvas.width = canvas.height = 0; resolve(b); }, 'image/jpeg', quality);
  });
  /** Re-encodes big photos (browsers apply the EXIF rotation when drawing, so the result is upright). */
  async function prepareImage(file) {
    const img = await loadImage(file);
    const w = img.naturalWidth, h2 = img.naturalHeight;
    const scale = Math.min(1, MAX_DIM / Math.max(w, h2));
    if (ALLOWED.includes(file.type) && scale === 1 && file.size <= 1.5 * 1048576) return file;
    let dim = scale, blob = null;
    for (const q of [QUALITY, 0.75, 0.65]) {
      blob = await encode(img, Math.round(w * dim), Math.round(h2 * dim), q);
      if (blob && blob.size <= MAX_FILE) break;
      dim *= 0.85;
    }
    if (!blob) throw new Error('This picture could not be prepared.');
    return blob;
  }
  const jpegName = n => 'page-' + n + '.jpg';

  // ---------------------------------------------------------------- dialogs (focus-trapped, back button closes)
  // One history entry guards every open layer, so the phone's Back button closes the camera or
  // preview instead of leaving the test page.
  const layers = [];
  // history.state is the source of truth: back() is only ever called while our own guard entry is
  // current, so closing a layer can never navigate away from the test page.
  let pendingBack = false;
  const onGuard = () => !!(history.state && history.state.pagesLayer);
  if (onGuard()) { try { history.replaceState(null, ''); } catch { /* ignore */ } } // reloaded with a layer open
  const trap = (root, e) => {
    if (e.key !== 'Tab') return;
    const items = [...root.querySelectorAll('button:not([disabled]),a[href],input:not([type=file]),[tabindex="0"]')].filter(x => x.offsetParent !== null);
    if (!items.length) return;
    const first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  };
  const pushGuard = () => { if (pendingBack || onGuard()) return; try { history.pushState({ pagesLayer: true }, ''); } catch { /* sandboxed */ } };
  function openLayer(el, onClose, { escape = true } = {}) {
    const layer = { el, onClose, opener: document.activeElement, escape, locked: false };
    layers.push(layer);
    el.hidden = false;
    document.documentElement.classList.add('pages-layer-open');
    pushGuard();
    return layer;
  }
  function closeLayer(layer, fromPop = false) {
    const i = layers.indexOf(layer);
    if (i < 0) return;
    layers.splice(i, 1);
    layer.el.hidden = true;
    if (!layers.length) document.documentElement.classList.remove('pages-layer-open');
    layer.onClose?.();
    layer.opener?.focus?.({ preventScroll: true });
    // Drop the guard entry only if nothing reopens right away (e.g. a confirm leading to the camera).
    if (!fromPop) setTimeout(() => { if (!layers.length && onGuard() && !pendingBack) { pendingBack = true; history.back(); } }, 0);
  }
  window.addEventListener('popstate', () => {
    if (pendingBack) { pendingBack = false; if (layers.length) pushGuard(); return; }
    const top = layers[layers.length - 1];
    if (!top) return;
    if (!top.locked) closeLayer(top, true);
    if (layers.length) pushGuard();
  });
  document.addEventListener('keydown', e => {
    const top = layers[layers.length - 1];
    if (!top) return;
    trap(top.el, e);
    if (e.key === 'Escape' && top.escape && !top.locked) { e.preventDefault(); closeLayer(top); }
  });

  /** A small confirm sheet. Resolves true when the main button is pressed. */
  function ask({ title, body, confirm, cancel = 'Cancel', danger = false, iconName = null }) {
    return new Promise(resolve => {
      let result = false;
      const ok = btn(confirm, iconName, danger ? 'btn--danger-solid' : 'btn--primary', () => { result = true; closeLayer(layer); });
      const el = h('div', { class: 'pages-sheet', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pagesSheetTitle' },
        h('div', { class: 'pages-sheet__card' },
          h('h2', { id: 'pagesSheetTitle', text: title }),
          ...(Array.isArray(body) ? body : [body]).map(t => typeof t === 'string' ? h('p', { text: t }) : t),
          h('div', { class: 'pages-sheet__actions' }, btn(cancel, null, 'btn--secondary', () => closeLayer(layer)), ok)));
      el.addEventListener('click', e => { if (e.target === el) closeLayer(layer); });
      document.body.append(el);
      paintIcons();
      const layer = openLayer(el, () => { el.remove(); resolve(result); });
      ok.focus();
    });
  }

  // ---------------------------------------------------------------- viewer (lightbox)
  const viewer = (() => {
    let el, img, doc, count, list = [], index = 0, layer = null, zoom = false;
    const build = () => {
      img = h('img', { alt: '' });
      doc = h('div', { class: 'pages-viewer__doc', hidden: true });
      count = h('span', { class: 'pages-viewer__count' });
      const stage = h('div', { class: 'gallery-lightbox__stage' }, img, doc);
      img.addEventListener('click', () => { zoom = !zoom; stage.classList.toggle('is-zoomed', zoom); });
      let x = null;
      stage.addEventListener('touchstart', e => { x = e.touches.length === 1 && !zoom ? e.touches[0].clientX : null; }, { passive: true });
      stage.addEventListener('touchend', e => { if (x === null) return; const d = e.changedTouches[0].clientX - x; if (Math.abs(d) > 50) show(index + (d < 0 ? 1 : -1)); x = null; });
      el = h('div', { class: 'gallery-lightbox pages-viewer', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Page preview', hidden: true },
        h('div', { class: 'gallery-lightbox__bar' }, count, h('button', { type: 'button', class: 'pages-viewer__close', 'aria-label': 'Close preview', onclick: () => closeLayer(layer) }, icon('x'), h('span', { text: 'Close' }))),
        stage,
        h('button', { type: 'button', class: 'gallery-lightbox__nav is-prev', 'aria-label': 'Previous page', onclick: () => show(index - 1) }, '‹'),
        h('button', { type: 'button', class: 'gallery-lightbox__nav is-next', 'aria-label': 'Next page', onclick: () => show(index + 1) }, '›'));
      el.addEventListener('keydown', e => { if (e.key === 'ArrowLeft') show(index - 1); if (e.key === 'ArrowRight') show(index + 1); });
      document.body.append(el);
      paintIcons();
    };
    const show = i => {
      index = (i + list.length) % list.length;
      const p = list[index];
      zoom = false; el.querySelector('.gallery-lightbox__stage').classList.remove('is-zoomed');
      count.textContent = 'Page ' + (index + 1) + ' of ' + list.length + ' · ' + size(p.blob.size);
      const isImg = p.type.startsWith('image/');
      img.hidden = !isImg; doc.hidden = isImg;
      if (isImg) { img.src = p.url; img.alt = 'Page ' + (index + 1) + ' — check that it is sharp, upright and the whole page is visible'; }
      else { doc.replaceChildren(icon('file-text'), h('strong', { text: 'PDF file' }), h('small', { text: p.name + ' · ' + size(p.blob.size) }), h('a', { class: 'btn btn--secondary', href: p.url, target: '_blank', rel: 'noopener', text: 'Open PDF' })); paintIcons(); }
      el.querySelectorAll('.gallery-lightbox__nav').forEach(b => { b.hidden = list.length < 2; });
    };
    return {
      open(pages, i) {
        if (!el) build();
        list = pages;
        show(i);
        layer = openLayer(el, () => { img.removeAttribute('src'); });
        el.querySelector('.pages-viewer__close').focus();
      },
    };
  })();

  // ---------------------------------------------------------------- camera
  const camera = (() => {
    let el, video, shot, msg, tip, countEl, captureBtn, switchBtn, torchBtn, doneBtn, useBtn, retakeBtn, live, review;
    let stream = null, layer = null, target = null, replaceAt = null, pending = null, devices = [], torchOn = false, starting = false;

    const stop = () => { stream?.getTracks().forEach(t => t.stop()); stream = null; video && (video.srcObject = null); };
    const setMode = mode => {
      live.hidden = mode !== 'live'; review.hidden = mode !== 'review';
      shot.hidden = mode !== 'review'; video.hidden = mode === 'review';
      if (mode !== 'error') msg.hidden = true;
      tip.hidden = mode === 'error';
    };
    const updateCount = () => {
      const n = target.pages.length + (replaceAt === null ? 1 : 0);
      countEl.textContent = replaceAt !== null ? 'Retake page ' + (replaceAt + 1) : 'Page ' + n;
      const added = target.sessionAdded || 0;
      doneBtn.hidden = replaceAt !== null || !added;
      doneBtn.querySelector('span').textContent = 'Done (' + added + ')';
    };
    const fail = err => {
      stop();
      const name = err?.name || '';
      let text = 'The camera could not be started on this device.';
      if (!window.isSecureContext) text = 'The camera only works on a secure (https) connection. Upload photos from your device instead.';
      else if (!navigator.mediaDevices?.getUserMedia) text = 'This browser cannot open the camera inside the page. Upload photos from your device instead.';
      else if (name === 'NotAllowedError' || name === 'SecurityError') text = 'Camera access is blocked. Allow camera permission in your browser settings or upload photos from your device instead.';
      else if (name === 'NotFoundError' || name === 'OverconstrainedError') text = 'No camera was found on this device. Upload photos from your device instead.';
      else if (name === 'NotReadableError' || name === 'AbortError' || name === 'TrackStartError') text = 'The camera is being used by another app. Close that app and press Try again, or upload photos instead.';
      setMode('error'); video.hidden = true; live.hidden = true; review.hidden = true;
      msg.hidden = false;
      msg.replaceChildren(icon('camera-off'), h('p', { text }),
        h('div', { class: 'pages-camera__msg-actions' },
          btn('Upload From Device', 'folder-up', 'btn--primary', () => { const t = target; closeLayer(layer); t.pick(); }),
          name === 'NotReadableError' || name === 'AbortError' ? btn('Try again', 'refresh-cw', 'btn--secondary', start) : null));
      paintIcons();
      msg.querySelector('button')?.focus();
    };
    async function start(deviceId) {
      if (starting) return;
      starting = true;
      stop();
      setMode('live');
      captureBtn.disabled = true;
      try {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) throw Object.assign(new Error('unsupported'), { name: 'Unsupported' });
        const base = { width: { ideal: 2560 }, height: { ideal: 1920 } };
        const video1 = typeof deviceId === 'string' ? { ...base, deviceId: { exact: deviceId } } : { ...base, facingMode: { ideal: 'environment' } };
        try { stream = await navigator.mediaDevices.getUserMedia({ video: video1, audio: false }); }
        catch (e) { if (e.name !== 'OverconstrainedError') throw e; stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false }); }
        if (!layer) { stop(); return; } // closed while the permission prompt was open
        video.srcObject = stream;
        await video.play().catch(() => {});
        const track = stream.getVideoTracks()[0];
        torchOn = false;
        const caps = track.getCapabilities?.() || {};
        torchBtn.hidden = !caps.torch;
        torchBtn.setAttribute('aria-pressed', 'false');
        devices = (await navigator.mediaDevices.enumerateDevices().catch(() => [])).filter(d => d.kind === 'videoinput');
        switchBtn.hidden = devices.length < 2;
        captureBtn.disabled = false;
        captureBtn.focus();
      } catch (err) {
        fail(err);
      } finally { starting = false; }
    }
    const switchCamera = () => {
      if (devices.length < 2 || !stream) return;
      const current = stream.getVideoTracks()[0]?.getSettings?.().deviceId;
      const at = devices.findIndex(d => d.deviceId === current);
      start(devices[(at + 1) % devices.length].deviceId);
    };
    const toggleTorch = async () => {
      const track = stream?.getVideoTracks()[0];
      if (!track) return;
      try { torchOn = !torchOn; await track.applyConstraints({ advanced: [{ torch: torchOn }] }); torchBtn.setAttribute('aria-pressed', String(torchOn)); }
      catch { torchOn = false; torchBtn.hidden = true; }
    };
    const capture = async () => {
      if (!video.videoWidth) return;
      captureBtn.disabled = true;
      const w = video.videoWidth, h2 = video.videoHeight, scale = Math.min(1, MAX_DIM / Math.max(w, h2));
      const blob = await encode(video, Math.round(w * scale), Math.round(h2 * scale), QUALITY);
      captureBtn.disabled = false;
      if (!blob) return toast('The photo could not be taken. Please try again.');
      pending = blob;
      shot.src = URL.createObjectURL(blob);
      shot.alt = 'Photo you just took — check that it is sharp and the whole page is visible';
      setMode('review');
      useBtn.focus();
    };
    const retake = () => { if (shot.src) URL.revokeObjectURL(shot.src); shot.removeAttribute('src'); pending = null; setMode('live'); captureBtn.focus(); };
    const use = () => {
      if (!pending) return;
      const blob = pending;
      pending = null;
      if (shot.src) URL.revokeObjectURL(shot.src);
      shot.removeAttribute('src');
      if (replaceAt !== null) {
        target.replace(replaceAt, blob, 'camera');
        toast('Page ' + (replaceAt + 1) + ' replaced.');
        closeLayer(layer);
        return;
      }
      target.add([{ blob, name: jpegName(target.pages.length + 1), type: 'image/jpeg', source: 'camera' }]);
      target.sessionAdded = (target.sessionAdded || 0) + 1;
      if (target.pages.length >= target.max) { toast('You have added the maximum of ' + target.max + ' pages.'); closeLayer(layer); return; }
      toast('Page ' + target.pages.length + ' added. Take the next page or press Done.');
      setMode('live'); updateCount(); captureBtn.focus();
    };
    const build = () => {
      video = h('video', { playsinline: true, muted: true, autoplay: true, 'aria-label': 'Camera preview' });
      video.muted = true;
      shot = h('img', { class: 'pages-camera__shot', hidden: true });
      msg = h('div', { class: 'pages-camera__msg', role: 'alert', hidden: true });
      countEl = h('span', { class: 'pages-camera__count', 'aria-live': 'polite' });
      captureBtn = h('button', { type: 'button', class: 'pages-camera__shutter', 'aria-label': 'Capture page', onclick: capture }, h('span'));
      switchBtn = btn('Switch', 'switch-camera', 'pages-camera__tool', switchCamera, { hidden: true, 'aria-label': 'Switch camera' });
      torchBtn = btn('Flash', 'flashlight', 'pages-camera__tool', toggleTorch, { hidden: true, 'aria-pressed': 'false' });
      doneBtn = btn('Done', 'check', 'pages-camera__tool pages-camera__done', () => closeLayer(layer), { hidden: true });
      useBtn = btn('Use Photo', 'check', 'btn--primary pages-camera__big', use);
      retakeBtn = btn('Retake', 'rotate-ccw', 'btn--secondary pages-camera__big', retake);
      live = h('div', { class: 'pages-camera__controls' }, h('div', { class: 'pages-camera__side' }, switchBtn), captureBtn, h('div', { class: 'pages-camera__side' }, doneBtn));
      review = h('div', { class: 'pages-camera__controls is-review', hidden: true }, retakeBtn, useBtn);
      el = h('div', { class: 'pages-camera', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Take photos of your answer pages', hidden: true },
        h('div', { class: 'pages-camera__bar' }, btn('Close', 'x', 'pages-camera__tool', () => closeLayer(layer)), countEl, torchBtn),
        h('div', { class: 'pages-camera__stage' }, video, shot, h('div', { class: 'pages-camera__guide', 'aria-hidden': 'true' }), msg),
        tip = h('p', { class: 'pages-camera__tip', text: 'Fit the whole page inside the frame, in good light, then press the round button.' }),
        live, review);
      document.body.append(el);
      paintIcons();
      // Never leave the camera running in the background (switching apps, locking the phone).
      document.addEventListener('visibilitychange', () => {
        if (!layer) return;
        if (document.hidden) stop(); else if (live && !live.hidden && !stream) start();
      });
      window.addEventListener('pagehide', stop);
    };
    return {
      open(collector, index = null) {
        if (!el) build();
        target = collector; replaceAt = index; pending = null;
        if (index === null) collector.sessionAdded = 0;
        layer = openLayer(el, () => { stop(); retake(); layer = null; });
        updateCount();
        start();
      },
    };
  })();

  // ---------------------------------------------------------------- one upload box → page list
  class Collector {
    constructor(label, form, onChange) {
      this.label = label;
      this.input = $('input[type=file]', label);
      this.field = this.input.name;
      this.max = Math.max(1, Math.min(MAX_FILES, parseInt(label.dataset.maxPages, 10) || 20));
      this.required = label.hasAttribute('data-required');
      this.title = label.dataset.pagesLabel || 'Your answer';
      this.pages = [];
      this.onChange = onChange;
      this.replaceIndex = null;
      this.input.required = false;
      this.input.disabled = true; // the managed pages are sent instead of the native picker's files
      this.picker = h('input', { type: 'file', accept: 'image/*,application/pdf,.pdf', multiple: true, hidden: true, tabindex: '-1', 'aria-hidden': 'true' });
      this.picker.addEventListener('change', () => this.fromPicker());
      label.hidden = true;
      this.root = h('div', { class: 'pages-box', 'data-pages-box': true });
      this.list = h('ol', { class: 'pages-list', 'aria-label': this.title + ' pages' });
      this.summary = h('p', { class: 'pages-box__summary', 'aria-live': 'polite' });
      this.empty = h('div', { class: 'pages-box__start' },
        btn('Take Photos', 'camera', 'btn--primary pages-box__cta', () => camera.open(this)),
        btn('Upload From Device', 'folder-up', 'btn--secondary pages-box__cta', () => this.pick()));
      this.more = h('div', { class: 'pages-box__more', hidden: true },
        h('span', { class: 'pages-box__more-label' }, icon('plus'), h('span', { text: 'Add another page' })),
        btn('Take Photo', 'camera', 'btn--secondary', () => camera.open(this)),
        btn('Upload', 'folder-up', 'btn--secondary', () => this.pick()));
      this.hint = h('p', { class: 'pages-box__hint' }, icon('lightbulb'), h('span', { text: 'Lay each page flat in good light. Up to ' + plural(this.max, 'page', 'pages') + ' · JPG, PNG or PDF.' }));
      this.root.append(this.empty, this.hint, this.list, this.more, this.summary, this.picker);
      label.after(this.root);
      this.render();
    }
    pick(replaceIndex = null) {
      this.replaceIndex = replaceIndex;
      this.picker.multiple = replaceIndex === null;
      this.picker.value = '';
      this.picker.click();
    }
    async fromPicker() {
      const files = [...this.picker.files];
      this.picker.value = '';
      if (!files.length) return;
      this.root.classList.add('is-busy');
      const rejected = [], ready = [];
      for (const f of files) {
        try {
          if (f.type === 'application/pdf' || /\.pdf$/i.test(f.name)) {
            if (f.size > MAX_FILE) throw new Error('larger than 5 MB');
            ready.push({ blob: f, name: f.name.replace(/[^\w.-]+/g, '_').slice(-60) || 'page.pdf', type: 'application/pdf', source: 'upload' });
          } else if (f.type.startsWith('image/') || /\.(jpe?g|png|heic|heif|webp)$/i.test(f.name)) {
            const blob = await prepareImage(f).catch(() => { throw new Error('this picture format cannot be used — take the photo with the camera button instead'); });
            if (blob.size > MAX_FILE) throw new Error('still larger than 5 MB');
            const png = blob.type === 'image/png';
            ready.push({ blob, name: png ? 'page.png' : jpegName(this.pages.length + ready.length + 1), type: png ? 'image/png' : 'image/jpeg', source: 'upload' });
          } else throw new Error('use a photo (JPG/PNG) or a PDF');
        } catch (e) { rejected.push(f.name + ': ' + e.message); }
      }
      this.root.classList.remove('is-busy');
      if (this.replaceIndex !== null && ready[0]) { this.replace(this.replaceIndex, ready[0].blob, 'upload', ready[0]); toast('Page ' + (this.replaceIndex + 1) + ' replaced.'); }
      else if (ready.length) {
        const room = this.max - this.pages.length;
        if (ready.length > room) rejected.push(plural(ready.length - room, 'page was', 'pages were') + ' not added — this test accepts up to ' + this.max + ' pages.');
        this.add(ready.slice(0, Math.max(0, room)));
        if (room > 0) toast(plural(Math.min(room, ready.length), 'page', 'pages') + ' added.');
      }
      this.replaceIndex = null;
      if (rejected.length) ask({ title: 'Some files were not added', body: rejected, confirm: 'OK', cancel: 'Close' });
    }
    add(items) {
      items.forEach(it => this.pages.push({ id: uid(), blob: it.blob, name: it.name, type: it.type, source: it.source, url: URL.createObjectURL(it.blob) }));
      this.render(); this.onChange();
    }
    replace(i, blob, source, meta = {}) {
      const old = this.pages[i];
      if (!old) return;
      URL.revokeObjectURL(old.url);
      this.pages[i] = { id: uid(), blob, name: meta.name || jpegName(i + 1), type: meta.type || 'image/jpeg', source, url: URL.createObjectURL(blob) };
      this.render(); this.onChange();
      this.list.children[i]?.querySelector('.page-card__thumb')?.focus();
    }
    move(i, d) {
      const j = i + d;
      if (j < 0 || j >= this.pages.length) return;
      [this.pages[i], this.pages[j]] = [this.pages[j], this.pages[i]];
      this.render(); this.onChange();
      this.list.children[j]?.querySelector(d < 0 ? '[data-up]' : '[data-down]')?.focus();
      toast('Page moved to position ' + (j + 1) + '.');
    }
    async remove(i) {
      const ok = await ask({ title: 'Remove page ' + (i + 1) + '?', body: 'The remaining pages will be renumbered.', confirm: 'Remove', danger: true, iconName: 'trash-2' });
      if (!ok) return;
      URL.revokeObjectURL(this.pages[i].url);
      this.pages.splice(i, 1);
      this.render(); this.onChange();
      (this.list.children[Math.min(i, this.pages.length - 1)]?.querySelector('.page-card__thumb') || this.root.querySelector('.pages-box__cta'))?.focus();
    }
    bytes() { return this.pages.reduce((n, p) => n + p.blob.size, 0); }
    render() {
      const n = this.pages.length;
      this.label.dataset.pages = String(n);
      this.empty.hidden = n > 0;
      this.more.hidden = n === 0 || n >= this.max;
      this.list.replaceChildren(...this.pages.map((p, i) => {
        const isImg = p.type.startsWith('image/');
        const thumb = h('button', { type: 'button', class: 'page-card__thumb', 'aria-label': 'View page ' + (i + 1), onclick: () => viewer.open(this.pages, i) },
          isImg ? h('img', { src: p.url, alt: 'Page ' + (i + 1) + ' of ' + this.title.toLowerCase(), loading: 'lazy', decoding: 'async' }) : h('span', { class: 'page-card__doc' }, icon('file-text'), h('span', { text: 'PDF' })));
        return h('li', { class: 'page-card' }, thumb,
          h('div', { class: 'page-card__body' },
            h('div', { class: 'page-card__meta' }, h('strong', { text: 'Page ' + (i + 1) }), h('small', { text: (p.source === 'camera' ? 'Camera' : 'Uploaded') + ' · ' + size(p.blob.size) })),
            h('div', { class: 'page-card__actions' },
              btn('View', 'eye', 'btn--ghost', () => viewer.open(this.pages, i)),
              p.source === 'camera' ? btn('Retake', 'camera', 'btn--ghost', () => camera.open(this, i)) : btn('Replace', 'replace', 'btn--ghost', () => this.pick(i)),
              btn('Up', 'arrow-up', 'btn--ghost', () => this.move(i, -1), { 'data-up': true, disabled: i === 0, 'aria-label': 'Move page ' + (i + 1) + ' up' }),
              btn('Down', 'arrow-down', 'btn--ghost', () => this.move(i, 1), { 'data-down': true, disabled: i === n - 1, 'aria-label': 'Move page ' + (i + 1) + ' down' }),
              btn('Remove', 'trash-2', 'btn--ghost page-card__remove', () => this.remove(i), { 'aria-label': 'Remove page ' + (i + 1) }))));
      }));
      this.summary.textContent = n ? plural(n, 'page', 'pages') + ' ready · ' + size(this.bytes()) + (n >= this.max ? ' · maximum reached' : '') : '';
      paintIcons();
    }
  }

  // ---------------------------------------------------------------- the test form
  function enhanceForm(form) {
    const key = form.dataset.draftKey;
    const submitBtn = $('[data-submit-button]', form);
    const submitText = $('[data-submit-text]', form);
    const summary = $('[data-submit-summary]', form);
    let busy = false, saveTimer = null, uploading = false;
    const collectors = [];
    const total = () => collectors.reduce((n, c) => n + c.pages.length, 0);
    const totalBytes = () => collectors.reduce((n, c) => n + c.bytes(), 0);
    const refresh = () => {
      const n = total();
      if (summary) { summary.hidden = !n; summary.textContent = plural(n, 'page', 'pages') + ' · ' + size(totalBytes()); }
      if (submitText) submitText.textContent = n ? 'Submit ' + plural(n, 'Page', 'Pages') : 'Submit final answers';
      form.dispatchEvent(new Event('change'));
    };
    const save = () => {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(() => {
        if (!key) return;
        const fields = {};
        collectors.forEach(c => { if (c.pages.length) fields[c.field] = c.pages.map(p => ({ blob: p.blob, name: p.name, type: p.type, source: p.source })); });
        if (!Object.keys(fields).length) drafts.remove(key);
        else drafts.put({ key, title: form.dataset.testTitle || 'Online test', url: form.dataset.testUrl || location.href, savedAt: Date.now(), fields });
      }, 350);
    };
    const changed = () => { refresh(); save(); };
    form.querySelectorAll('[data-page-upload]').forEach(label => { if ($('input[type=file]', label)) collectors.push(new Collector(label, form, changed)); });
    refresh();

    // Restore unsent pages (e.g. the phone reloaded the app while the student was away).
    if (key) drafts.get(key).then(rec => {
      if (!rec?.fields) return;
      let restored = 0;
      collectors.forEach(c => {
        const items = (rec.fields[c.field] || []).filter(x => x && x.blob).slice(0, c.max - c.pages.length);
        if (items.length) { c.add(items); restored += items.length; }
      });
      if (!restored) return;
      const note = h('div', { class: 'pages-restored', role: 'status' }, icon('history'),
        h('span', { text: 'We restored ' + plural(restored, 'unsent page', 'unsent pages') + ' from your previous session.' }),
        btn('Discard', null, 'btn--ghost', async () => {
          if (!await ask({ title: 'Discard restored pages?', body: 'They will be removed from this device.', confirm: 'Discard', danger: true })) return;
          collectors.forEach(c => { c.pages.forEach(p => URL.revokeObjectURL(p.url)); c.pages = []; c.render(); });
          note.remove(); changed();
        }));
      form.prepend(note);
      paintIcons();
    });

    // ---- upload with progress, retry and session recovery
    const progress = (() => {
      let el, bar, pct, rows, status, actions, layer;
      return {
        open(pages) {
          rows = pages.map(p => h('li', { class: 'is-waiting' }, h('span', { class: 'pages-progress__dot', 'aria-hidden': 'true' }), h('span', { text: p.label }), h('small', { text: 'Waiting…' })));
          bar = h('span', { class: 'pages-progress__fill' });
          pct = h('strong', { text: '0%' });
          status = h('p', { class: 'pages-progress__status', 'aria-live': 'polite', text: 'Uploading your submission… keep this screen open.' });
          actions = h('div', { class: 'pages-sheet__actions', hidden: true });
          el = h('div', { class: 'pages-sheet', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pagesProgressTitle' },
            h('div', { class: 'pages-sheet__card pages-progress' },
              h('h2', { id: 'pagesProgressTitle', text: 'Uploading your submission' }),
              h('div', { class: 'pages-progress__track', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-label': 'Upload progress' }, bar), pct,
              status, rows.length ? h('ol', { class: 'pages-progress__list' }, rows) : null, actions));
          document.body.append(el);
          layer = openLayer(el, () => el.remove(), { escape: false });
          layer.locked = true;
        },
        set(fraction, bounds) {
          const v = Math.round(fraction * 100);
          bar.style.width = v + '%'; pct.textContent = v + '%';
          el.querySelector('[role=progressbar]').setAttribute('aria-valuenow', String(v));
          rows.forEach((r, i) => {
            const state = fraction >= bounds[i + 1] ? 'done' : fraction >= bounds[i] ? 'active' : 'waiting';
            r.className = 'is-' + state;
            r.lastChild.textContent = state === 'done' ? '✓ Sent' : state === 'active' ? 'Uploading…' : 'Waiting…';
          });
        },
        message(text, buttons = [], tone = '') {
          status.textContent = text;
          status.className = 'pages-progress__status' + (tone ? ' is-' + tone : '');
          actions.replaceChildren(...buttons);
          actions.hidden = !buttons.length;
          paintIcons();
          buttons[buttons.length - 1]?.focus();
        },
        close() { layer.locked = false; closeLayer(layer); },
      };
    })();

    const freshToken = async () => {
      const res = await fetch(form.dataset.testUrl || location.href, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
      if (res.redirected && /\/login/.test(res.url)) throw Object.assign(new Error('signed out'), { reason: 'signed_out' });
      const m = (await res.text()).match(/name="_token" value="([a-f0-9]+)"/);
      if (!m) throw new Error('no token');
      form.querySelectorAll('input[name=_token]').forEach(i => { i.value = m[1]; });
    };

    const send = (attempt = 1) => new Promise(resolve => {
      const data = new FormData(form);
      collectors.forEach(c => { data.delete(c.field); c.pages.forEach(p => data.append(c.field, p.blob, p.name)); });
      data.set('page_count', String(total()));
      const pages = collectors.flatMap(c => c.pages.map((p, i) => ({ size: p.blob.size, label: (c.title === 'Solved paper' ? 'Page ' : c.title + ', page ') + (i + 1) })));
      const sum = pages.reduce((n, p) => n + p.size, 0) || 1;
      const bounds = [0];
      pages.reduce((acc, p) => { acc += p.size / sum; bounds.push(acc * 0.97); return acc; }, 0);
      if (attempt === 1) progress.open(pages.map(p => ({ label: p.label })));
      else progress.message('Uploading again…');
      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.timeout = 10 * 60 * 1000;
      xhr.upload.onprogress = e => { if (e.lengthComputable) progress.set(e.loaded / e.total, bounds); };
      xhr.onload = () => {
        let body = {};
        try { body = JSON.parse(xhr.responseText); } catch { /* not JSON */ }
        resolve({ status: xhr.status, body, url: xhr.responseURL, bounds });
      };
      xhr.onerror = () => resolve({ status: 0, body: {}, bounds });
      xhr.ontimeout = () => resolve({ status: -1, body: {}, bounds });
      xhr.send(data);
    });

    async function upload() {
      uploading = true;
      let wake = null;
      try { wake = await navigator.wakeLock?.request('screen'); } catch { /* optional */ }
      const done = () => { uploading = false; busy = false; submitBtn.disabled = false; wake?.release?.().catch(() => {}); };
      const tryAgain = () => btn('Try again', 'refresh-cw', 'btn--primary', () => { progress.close(); setTimeout(() => start(), 50); });
      const review = () => btn('Review pages', 'images', 'btn--secondary', () => progress.close());
      const start = async (attempt = 1) => {
        busy = true; submitBtn.disabled = true; uploading = true;
        if (!navigator.onLine) {
          if (attempt === 1) progress.open([]);
          progress.message('You are offline. Your pages are still on this device — connect to the internet and press Try again.', [review(), tryAgain()], 'error');
          return done();
        }
        const r = await send(attempt);
        if (r.status === 200 && r.body.ok) {
          progress.set(1, r.bounds.map(() => 0));
          progress.message('✓ Submitted. ' + (r.body.message || ''), [], 'ok');
          if (key) await drafts.remove(key);
          collectors.forEach(c => { c.pages = []; });
          uploading = false;
          setTimeout(() => location.assign(r.body.redirect || '/portal/student/online-tests'), 1100);
          return;
        }
        if (r.status === 419 && r.body.reason === 'csrf' && attempt === 1) {
          try { await freshToken(); return start(2); } catch (e) { if (e.reason === 'signed_out') r.body = { reason: 'signed_out' }; }
        }
        if (r.status === 401 || r.body.reason === 'signed_out' || (r.url && /\/login(\?|$)/.test(r.url))) {
          progress.message('You were signed out. Your pages are saved on this device. Sign in again, open this test and press Submit.', [btn('Sign in', 'log-in', 'btn--primary', () => location.assign('/login'))], 'error');
          return done();
        }
        if (r.status === 413 || r.body.reason === 'too_large') {
          progress.message(r.body.message || 'This upload is too large for the server. Remove some pages and try again.', [review()], 'error');
          return done();
        }
        if (r.status === 422) {
          progress.message(r.body.message || 'The submission was not accepted.', [review(), tryAgain()], 'error');
          return done();
        }
        progress.message(r.status === -1 ? 'The upload took too long and was stopped. Your pages are still available.' : 'Upload failed. Your captured pages are still available.', [review(), tryAgain()], 'error');
        done();
      };
      await start();
    }

    window.addEventListener('beforeunload', e => { if (uploading) { e.preventDefault(); e.returnValue = ''; } });

    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (busy) return;
      if (!form.reportValidity()) return;
      const missing = collectors.find(c => c.required && !c.pages.length);
      if (missing) {
        await ask({ title: 'Add your pages first', body: 'Take a photo of every answer page (or upload them) before submitting.', confirm: 'Take Photos', cancel: 'Close', iconName: 'camera' }) && camera.open(missing);
        return;
      }
      const unanswered = [...form.querySelectorAll('[data-answer-card]')].findIndex(card => {
        const text = $('textarea', card), box = $('[data-page-upload]', card);
        return text && !text.value.trim() && !(+box?.dataset.pages);
      });
      if (unanswered >= 0) {
        await ask({ title: 'Question ' + (unanswered + 1) + ' has no answer', body: 'Type an answer or add a photo of it before submitting.', confirm: 'OK', cancel: 'Close' });
        form.querySelectorAll('[data-answer-card]')[unanswered].scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      const n = total(), bytes = totalBytes();
      if (n > MAX_FILES) return ask({ title: 'Too many pages', body: 'One submission can carry up to ' + MAX_FILES + ' pages. Please remove ' + (n - MAX_FILES) + '.', confirm: 'OK' });
      if (bytes > MAX_TOTAL) return ask({ title: 'Submission is too large', body: 'Your pages add up to ' + size(bytes) + '; the limit is ' + size(MAX_TOTAL) + '. Remove a page or upload smaller photos.', confirm: 'OK' });
      const ok = await ask(n
        ? { title: 'Ready to submit?', body: [plural(n, 'page', 'pages') + ' (' + size(bytes) + ') will be submitted.', 'Make sure every page is readable and in the correct order. You cannot change it afterwards.'], confirm: 'Submit ' + plural(n, 'Page', 'Pages'), cancel: 'Review Again', iconName: 'send' }
        : { title: 'Submit your final answers?', body: 'You cannot edit them afterwards.', confirm: 'Submit', cancel: 'Review Again', iconName: 'send' });
      if (ok) upload();
    });
  }

  // ---------------------------------------------------------------- unsent-draft banner (dashboard / tests list)
  async function draftBanner(box) {
    const owner = box.dataset.owner;
    const submitted = new Set((box.dataset.submitted || '').split(',').filter(Boolean));
    const all = (await drafts.all()) || [];
    const mine = [];
    for (const d of all) {
      const pages = Object.values(d.fields || {}).reduce((n, list) => n + list.length, 0);
      const [, test] = /-t(\d+)$/.exec(d.key) || [];
      if (Date.now() - (d.savedAt || 0) > DRAFT_DAYS * 86400000 || !pages || (owner && d.key.startsWith(owner + '-') && submitted.has(test))) { drafts.remove(d.key); continue; }
      if (owner && d.key.startsWith(owner + '-')) mine.push({ ...d, pages });
    }
    if (!mine.length) return;
    box.replaceChildren(...mine.map(d => h('div', { class: 'pages-restored pages-restored--banner', role: 'status' }, icon('cloud-upload'),
      h('span', {}, h('strong', { text: 'Unsent submission: ' }), d.title + ' — ' + plural(d.pages, 'page is', 'pages are') + ' saved on this device.'),
      h('a', { class: 'btn btn--primary', href: d.url }, h('span', { text: 'Continue & submit' })))));
    box.hidden = false;
    paintIcons();
  }

  const form = $('[data-test-attempt]');
  if (form && typeof FormData === 'function' && typeof Blob === 'function' && form.querySelector('[data-page-upload]')) enhanceForm(form);
  else if (form) form.addEventListener('submit', e => { if (!confirm('Submit your final answers? You cannot edit them afterwards.')) e.preventDefault(); });
  document.querySelectorAll('[data-submission-drafts]').forEach(draftBanner);
})();
