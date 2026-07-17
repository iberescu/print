// RTB House events for the brand-store remarketing. Offer ids are the alias
// feed ids ("{alias}-{product-slug}") — pre-provisioned in the daily feed, so
// basketadd/conversion are valid IMMEDIATELY, before the feed re-read swaps in
// the customer's mockup images. No-ops when the tag isn't configured.
function push(event) {
    if (window.rtbhEvents) window.rtbhEvents.push(event);
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
    if (id) push({ eventType: 'basketadd', offerId: id });
}

export function rtbConversion(offerIds, value, orderId) {
    if (!offerIds?.length) return;
    push({
        eventType: 'conversion',
        conversionClass: 'order',
        conversionSubClass: 'purchase',
        conversionId: String(orderId ?? ''),
        conversionValue: String(value ?? ''),
        offerIds: offerIds.map(String),
    });
}
