/**
 * Popup for modules that are not production ready.
 *
 * The sidebar already renders these entries as locked and calls window.showLockedModule().
 * That function was referenced but never defined, so clicking a locked item silently threw;
 * this supplies it. The server enforces the same rule in PreviewModuleMiddleware — this is
 * only the explanation, never the protection.
 */
(function () {
    'use strict';

    var MESSAGE = 'This module is under testing and will be live soon.';

    function closeDialog(overlay) {
        if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
        }
        document.removeEventListener('keydown', onKeydown);
    }

    function onKeydown(event) {
        if (event.key === 'Escape') {
            closeDialog(document.getElementById('previewLockOverlay'));
        }
    }

    window.showLockedModule = function (label, reason) {
        var plan = reason === 'plan';
        var existing = document.getElementById('previewLockOverlay');
        if (existing) {
            closeDialog(existing);
        }

        var overlay = document.createElement('div');
        overlay.id = 'previewLockOverlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', (label || 'Module') + (plan ? ' not in your plan' : ' under testing'));
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:grid;place-items:center;' +
            'background:rgba(40,8,22,.45);padding:24px';

        var card = document.createElement('div');
        card.style.cssText = 'max-width:420px;width:100%;background:#fff;border:1px solid #eadde2;' +
            'border-radius:16px;padding:28px;text-align:center;box-shadow:0 18px 45px rgba(84,9,37,.18)';

        var icon = document.createElement('div');
        icon.textContent = plan ? '🔒' : '🚧';
        icon.style.cssText = 'font-size:30px;margin-bottom:8px';

        var info = document.getElementById('planLockInfo');
        var data = info ? info.dataset : {};

        var kicker = document.createElement('div');
        kicker.textContent = plan ? (data.title || 'Upgrade to unlock') : 'Coming soon';
        kicker.style.cssText = 'display:inline-block;background:#fbeef3;color:#7d1742;border-radius:999px;' +
            'padding:4px 12px;font-size:.75rem;font-weight:700;letter-spacing:.03em;text-transform:uppercase;margin-bottom:10px';

        var title = document.createElement('h2');
        title.textContent = (label || 'This module') + (plan ? ' is not in your plan' : ' is under testing');
        title.style.cssText = 'color:#7a1632;font-size:1.15rem;margin:0 0 8px';

        var body = document.createElement('p');
        body.textContent = plan ? (data.message || "This feature is not included in your school's current plan.") : MESSAGE;
        body.style.cssText = 'color:#6d6267;margin:0 0 20px;font-size:.92rem;line-height:1.55;white-space:pre-line';

        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;flex-direction:column;gap:10px';

        if (plan && data.whatsapp) {
            var school = data.school || '';
            var text = 'Hello, we would like to unlock ' + (label || 'a feature') + (school ? ' for ' + school.trim() : '') + '.';
            var wa = document.createElement('a');
            wa.href = 'https://wa.me/' + encodeURIComponent(data.whatsapp) + '?text=' + encodeURIComponent(text);
            wa.target = '_blank';
            wa.rel = 'noopener';
            wa.textContent = data.button || 'Contact us on WhatsApp';
            wa.style.cssText = 'display:block;background:#1fa855;color:#fff;border-radius:10px;padding:12px 22px;' +
                'font-weight:700;text-decoration:none';
            actions.appendChild(wa);
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = plan && data.whatsapp ? 'Not now' : 'Got it';
        button.style.cssText = plan && data.whatsapp
            ? 'background:transparent;color:#7d1742;border:1px solid #eadde2;border-radius:10px;padding:11px 22px;cursor:pointer;font-weight:600'
            : 'background:#7d1742;color:#fff;border:0;border-radius:10px;padding:11px 22px;cursor:pointer;font-weight:600';
        button.addEventListener('click', function () { closeDialog(overlay); });
        actions.appendChild(button);

        card.appendChild(icon);
        card.appendChild(kicker);
        card.appendChild(title);
        card.appendChild(body);
        card.appendChild(actions);
        overlay.appendChild(card);

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeDialog(overlay);
            }
        });

        document.body.appendChild(overlay);
        document.addEventListener('keydown', onKeydown);
        button.focus();
    };

}());
