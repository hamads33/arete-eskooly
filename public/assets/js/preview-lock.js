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

        var title = document.createElement('h2');
        title.textContent = (label || 'This module') + (plan ? ' is not in your plan' : ' is under testing');
        title.style.cssText = 'color:#7a1632;font-size:1.15rem;margin:0 0 8px';

        var body = document.createElement('p');
        body.textContent = plan ? "This feature is not included in your school's current plan. Contact Arete Coaching Center to add it." : MESSAGE;
        body.style.cssText = 'color:#6d6267;margin:0 0 20px;font-size:.92rem;line-height:1.5';

        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Got it';
        button.style.cssText = 'background:#7d1742;color:#fff;border:0;border-radius:10px;' +
            'padding:11px 22px;cursor:pointer;font-weight:600';
        button.addEventListener('click', function () { closeDialog(overlay); });

        card.appendChild(icon);
        card.appendChild(title);
        card.appendChild(body);
        card.appendChild(button);
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
