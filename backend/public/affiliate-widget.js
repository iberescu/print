/**
 * RunMyPrint affiliate widget. Drop on any page:
 *
 *   <script async src="https://www.runmyprint.com/affiliate-widget.js"></script>
 *   <div data-rmp-affiliate="YOUR_KEY" data-logo-url="https://example.com/logo.png"></div>
 *
 * Pass the visitor's logo via data-logo-url (public URL) or their site via
 * data-website. The widget renders that logo on real printed products and
 * links to runmyprint.com. Impressions are counted only when the ad has
 * actually rendered images AND is at least half visible.
 */
(function () {
    'use strict';

    var script = document.currentScript;
    var ORIGIN = (script && /^https?:\/\//.test(script.src))
        ? script.src.replace(/\/affiliate-widget\.js.*$/, '')
        : 'https://www.runmyprint.com';

    function el(tag, css, text) {
        var n = document.createElement(tag);
        if (css) n.style.cssText = css;
        if (text) n.textContent = text;
        return n;
    }

    function initUnit(host) {
        if (host.__rmpInit) return;
        host.__rmpInit = true;

        var key = host.getAttribute('data-rmp-affiliate');
        var logo = host.getAttribute('data-logo-url') || '';
        var site = host.getAttribute('data-website') || '';
        if (!key || (!logo && !site)) return;

        var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;

        var q = 'key=' + encodeURIComponent(key)
            + (logo ? '&logo_url=' + encodeURIComponent(logo) : '')
            + (site ? '&website=' + encodeURIComponent(site) : '');

        fetch(ORIGIN + '/affiliate/widget/capture?' + q)
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.capture) poll(d.capture, 0); })
            .catch(function () { /* the ad simply doesn't render */ });

        function poll(capture, tries) {
            if (tries > 40) return; // ~3 min then give up quietly
            fetch(ORIGIN + '/affiliate/widget/status?key=' + encodeURIComponent(key) + '&capture=' + capture)
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ready) render(capture, d.images);
                    else if (!d.done) setTimeout(function () { poll(capture, tries + 1); }, 4500);
                })
                .catch(function () { setTimeout(function () { poll(capture, tries + 1); }, 6000); });
        }

        function render(capture, images) {
            var go = ORIGIN + '/affiliate/go?key=' + encodeURIComponent(key) + '&capture=' + capture;

            var box = el('a', 'display:block;text-decoration:none;font-family:system-ui,-apple-system,Segoe UI,sans-serif;'
                + 'border:1px solid #e3ded2;border-radius:14px;overflow:hidden;background:#fff;color:#1c2434;max-width:520px;');
            box.href = go;
            box.target = '_blank';
            box.rel = 'noopener sponsored';

            var shown = Math.min(4, images.length);
            var grid = el('div', 'display:grid;grid-template-columns:repeat(' + shown + ',1fr);gap:1px;background:#eee;');
            images.slice(0, 4).forEach(function (im) {
                var cell = el('div', 'aspect-ratio:1/1;background:#fff;overflow:hidden;');
                var img = el('img', 'width:100%;height:100%;object-fit:cover;display:block;');
                img.src = im.url;
                img.alt = im.label || 'Your logo on a printed product';
                img.loading = 'lazy';
                cell.appendChild(img);
                grid.appendChild(cell);
            });
            box.appendChild(grid);

            var bar = el('div', 'display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;');
            var copy = el('div');
            copy.appendChild(el('div', 'font-weight:700;font-size:14px;', 'Your logo on real products'));
            copy.appendChild(el('div', 'font-size:12px;color:#6b7280;', 'Business cards from $7.50 · printed by RunMyPrint'));
            var cta = el('span', 'flex-shrink:0;background:#398aff;color:#fff;font-weight:600;font-size:13px;'
                + 'padding:8px 14px;border-radius:999px;', 'Get yours →');
            bar.appendChild(copy);
            bar.appendChild(cta);
            box.appendChild(bar);

            root.appendChild(box);

            // one impression, only once at least half of the ad is on screen
            var counted = false;
            function count() {
                if (counted) return;
                counted = true;
                var url = ORIGIN + '/affiliate/widget/track?key=' + encodeURIComponent(key) + '&capture=' + capture + '&event=impression';
                if (navigator.sendBeacon) navigator.sendBeacon(url);
                else fetch(url).catch(function () {});
            }
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(function (entries, obs) {
                    entries.forEach(function (e) {
                        if (e.intersectionRatio >= 0.5) { count(); obs.disconnect(); }
                    });
                }, { threshold: 0.5 }).observe(box);
            } else {
                count();
            }
        }
    }

    function scan() {
        var nodes = document.querySelectorAll('[data-rmp-affiliate]');
        for (var i = 0; i < nodes.length; i++) initUnit(nodes[i]);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
    else scan();
})();
