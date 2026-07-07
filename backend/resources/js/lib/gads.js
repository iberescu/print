// Google Ads helpers — safe no-ops when the tag isn't configured (window.__gads
// is set by app.blade.php only when GOOGLE_ADS_TAG_ID exists).

/** Fire a conversion: kind is 'purchase' | 'logo' | 'cart'. */
export function adsConversion(kind, params = {}) {
    const g = window.__gads;
    if (!g || !g[kind] || typeof window.gtag !== 'function') return;
    window.gtag('event', 'conversion', { send_to: `${g.tag}/${g[kind]}`, currency: 'USD', ...params });
}

/** Plain gtag event (remarketing signals like view_item). */
export function adsEvent(name, params = {}) {
    if (!window.__gads || typeof window.gtag !== 'function') return;
    window.gtag('event', name, params);
}
