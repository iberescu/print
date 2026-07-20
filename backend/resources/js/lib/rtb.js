// RTB House events — the tag loads ONLY on brand-store hosts (app.blade.php).
// Offer ids are the alias feed ids ("{alias}-{product-slug}"), pre-provisioned
// in the daily feed so events are valid immediately. On the MAIN shop there is
// no tag: basketadds are queued (localStorage) and relayed into the cart's
// brand-store iframe via postMessage, where the tag actually lives — that's
// what makes RTB serve store-bound remarketing for main-shop activity.
// A uid event (sha256 of the known email, shared as page prop → window.__rtbUid)
// rides along with every push, matching RTB House's snippet spec.

const PENDING_KEY = 'rtb.pending';
export const RTB_MESSAGE = 'rtb:events';

function withUid(events) {
    return window.__rtbUid ? [...events, { eventType: 'uid', id: window.__rtbUid }] : events;
}

/** Push locally when the tag exists (store hosts). Returns false on the main shop. */
function push(...events) {
    if (!window.rtbhEvents) return false;
    window.rtbhEvents.push(...withUid(events));

    return true;
}

/** Main shop: keep the event for the next brand-store iframe to replay. */
function queue(event) {
    try {
        const q = JSON.parse(localStorage.getItem(PENDING_KEY) || '[]');
        q.push(event);
        localStorage.setItem(PENDING_KEY, JSON.stringify(q.slice(-20)));
    } catch { /* storage unavailable — drop */ }
}

export const rtbOfferId = (alias, slug) => (alias && slug ? `${alias}-${slug}` : null);

export function rtbHome() {
    push({ eventType: 'home' });
}

export function rtbOffer(alias, slug) {
    const id = rtbOfferId(alias, slug);
    if (id) push({ eventType: 'offer', offerId: id });
}

export function rtbBasketAdd(alias, slug) {
    const id = rtbOfferId(alias, slug);
    if (!id) return;
    const ev = { eventType: 'basketadd', offerId: id };
    if (!push(ev)) queue(ev);
}

export function rtbStartOrder() {
    push({ eventType: 'startorder' });
}

export function rtbConversion(offerIds, value, orderId) {
    if (!offerIds?.length) return;
    push({
        eventType: 'conversion',
        conversionClass: 'order',
        conversionSubClass: 'purchase',
        conversionId: String(orderId ?? ''),
        offerIds: offerIds.map(String),
        conversionValue: String(value ?? ''),
        conversionCurrency: 'USD',
    });
}

/** Cart page (main shop): replay everything queued into the store iframe. */
export function rtbFlushToFrame(iframe, targetOrigin) {
    let events = [];
    try {
        events = JSON.parse(localStorage.getItem(PENDING_KEY) || '[]');
        localStorage.removeItem(PENDING_KEY);
    } catch { /* storage unavailable */ }
    events = withUid(events);
    if (events.length && iframe?.contentWindow) {
        iframe.contentWindow.postMessage({ type: RTB_MESSAGE, events }, targetOrigin);
    }
}
