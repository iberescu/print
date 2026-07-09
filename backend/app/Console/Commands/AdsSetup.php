<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Build the Google Ads account via the REST API (v22): conversion actions,
 * two Search campaigns (business cards + AI logo maker), remarketing user
 * lists and a Display remarketing campaign. EVERY campaign is created PAUSED —
 * nothing serves or spends until it is enabled by hand in the Ads UI.
 *
 * Idempotent by name: rerunning skips anything that already exists, so it can
 * top up a partial run. The account bills in RON — budget/bid micros below are
 * RON (280 RON/day ≈ $62, 27 RON cpc cap ≈ $6).
 *
 *   php artisan ads:setup
 */
class AdsSetup extends Command
{
    protected $signature = 'ads:setup';

    protected $description = 'Create the (paused) Google Ads campaign structure via the API';

    private const API = 'https://googleads.googleapis.com/v22';
    private const SITE = 'https://www.runmyprint.com';
    private const US = 'geoTargetConstants/2840';
    private const EN = 'languageConstants/1000';

    private string $cid;
    private array $headers;

    public function handle(): int
    {
        $cfg = config('services.google_ads');
        foreach (['developer_token', 'client_id', 'client_secret', 'refresh_token', 'customer_id'] as $k) {
            if (empty($cfg[$k])) {
                $this->error("services.google_ads.$k missing");

                return self::FAILURE;
            }
        }
        $this->cid = $cfg['customer_id'];

        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $cfg['client_id'], 'client_secret' => $cfg['client_secret'],
            'refresh_token' => $cfg['refresh_token'], 'grant_type' => 'refresh_token',
        ])->throw()->json('access_token');
        $this->headers = [
            'Authorization' => 'Bearer '.$token,
            'developer-token' => $cfg['developer_token'],
            'login-customer-id' => (string) $cfg['login_customer_id'],
        ];

        $labels = $this->conversionActions();
        $this->searchCampaigns();
        $this->shoppingCampaign($cfg['merchant_id'] ?? null);
        $lists = $this->userLists();
        $this->remarketingCampaign($lists['all']);

        $this->newLine();
        $this->info('== add these to .env (site conversion labels) ==');
        $this->line('GOOGLE_ADS_TAG_ID=AW-'.$this->gaql('SELECT customer.conversion_tracking_setting.conversion_tracking_id FROM customer')[0]['customer']['conversionTrackingSetting']['conversionTrackingId']);
        foreach (['purchase' => 'GOOGLE_ADS_LABEL_PURCHASE', 'logo' => 'GOOGLE_ADS_LABEL_LOGO', 'cart' => 'GOOGLE_ADS_LABEL_CART'] as $k => $env) {
            $this->line($env.'='.($labels[$k] ?? '??'));
        }
        $this->newLine();
        $this->warn('All campaigns are PAUSED. Review in the Ads UI, then enable when ready.');

        return self::SUCCESS;
    }

    // ---- api plumbing -------------------------------------------------------

    private function gaql(string $query): array
    {
        $r = Http::withHeaders($this->headers)->post(self::API."/customers/{$this->cid}/googleAds:search", ['query' => $query])->throw()->json();

        return $r['results'] ?? [];
    }

    private function mutate(string $service, array $operations): array
    {
        $r = Http::withHeaders($this->headers)
            ->post(self::API."/customers/{$this->cid}/{$service}:mutate", ['operations' => $operations]);
        if ($r->failed()) {
            $this->error(json_encode($r->json('error.details.0.errors') ?? $r->json('error.message'), JSON_PRETTY_PRINT));
            $r->throw();
        }

        return array_column($r->json('results') ?? [], 'resourceName');
    }

    /** Resource name of an existing row by name, or null. */
    private function existing(string $entity, string $name): ?string
    {
        $extra = in_array($entity, ['campaign', 'ad_group']) ? " AND {$entity}.status != 'REMOVED'" : '';
        $rows = $this->gaql("SELECT {$entity}.resource_name FROM {$entity} WHERE {$entity}.name = '".addslashes($name)."'".$extra);
        $key = lcfirst(str_replace('_', '', ucwords($entity, '_')));

        return $rows[0][$key]['resourceName'] ?? null;
    }

    // ---- conversion actions -------------------------------------------------

    private function conversionActions(): array
    {
        $wanted = [
            'purchase' => ['name' => 'RMP Purchase', 'category' => 'PURCHASE', 'value' => true],
            'logo'     => ['name' => 'RMP Logo Download', 'category' => 'DEFAULT', 'value' => false], // DOWNLOAD is app-installs-only
            'cart'     => ['name' => 'RMP Add To Cart', 'category' => 'ADD_TO_CART', 'value' => false],
        ];
        foreach ($wanted as $w) {
            if (! $this->existing('conversion_action', $w['name'])) {
                $this->mutate('conversionActions', [['create' => [
                    'name' => $w['name'], 'type' => 'WEBPAGE', 'category' => $w['category'], 'status' => 'ENABLED',
                    'countingType' => 'ONE_PER_CLICK',
                    'clickThroughLookbackWindowDays' => 30,
                    'valueSettings' => ['defaultValue' => 0, 'alwaysUseDefaultValue' => ! $w['value']],
                ]]]);
                $this->info("conversion action: {$w['name']} created");
            }
        }

        // labels live inside the event tag snippets
        $labels = [];
        foreach ($this->gaql('SELECT conversion_action.name, conversion_action.tag_snippets FROM conversion_action') as $row) {
            $ca = $row['conversionAction'];
            foreach ($wanted as $key => $w) {
                if (($ca['name'] ?? '') === $w['name']) {
                    foreach ($ca['tagSnippets'] ?? [] as $snip) {
                        if (preg_match('#AW-\d+/([\w-]+)#', $snip['eventSnippet'] ?? '', $m)) {
                            $labels[$key] = $m[1];
                            break;
                        }
                    }
                }
            }
        }

        return $labels;
    }

    // ---- search campaigns ---------------------------------------------------

    private function searchCampaigns(): void
    {
        $rsaBC = [
            'headlines' => [
                ['text' => '50 Business Cards For $7.50', 'pinnedField' => 'HEADLINE_1'],
                ['text' => 'Business Cards From $7.50'], ['text' => 'Custom Business Cards'],
                ['text' => 'Design Your Card Online'], ['text' => 'Premium Stocks & Finishes'],
                ['text' => 'Free Shipping Over $50'], ['text' => '100+ Designer Templates'],
                ['text' => 'Make Your Card In Minutes'], ['text' => 'Upload Art Or Design Online'],
                ['text' => 'Thick Stock. Sharp Print.'], ['text' => 'Satisfaction Guaranteed'],
                ['text' => 'Your Logo On Your Card'],
            ],
            'descriptions' => [
                ['text' => 'Design online or upload artwork — 50 full-colour business cards for $7.50, in minutes.'],
                ['text' => 'Pick a template, add your logo and details, approve the preview. Free shipping over $50.'],
                ['text' => 'Premium paper stocks and finishes. Sharp full-colour print, satisfaction guaranteed.'],
                ['text' => 'No design skills needed — our editor lays out your card. See it on real products first.'],
            ],
            'path1' => 'business-cards',
        ];
        $rsaLogo = [
            'headlines' => [
                ['text' => 'Free AI Logo Maker', 'pinnedField' => 'HEADLINE_1'],
                ['text' => 'Create A Free Logo Online'], ['text' => 'Unlimited Free Logos'],
                ['text' => 'Download SVG + PNG Free'], ['text' => 'AI Logos In Seconds'],
                ['text' => 'No Watermarks. No Signup.'], ['text' => 'True Vector Logo Files'],
                ['text' => 'Your Logo, Designed By AI'], ['text' => 'Describe It. AI Designs It.'],
                ['text' => 'Logo Maker For Business'], ['text' => 'Free Logo Generator'],
                ['text' => 'Then Print It On Anything'],
            ],
            'descriptions' => [
                ['text' => 'Describe your business — AI designs unique logos in seconds. Free SVG + PNG download.'],
                ['text' => 'Unlimited free generations, no watermark, commercial use. Then print it on anything.'],
                ['text' => 'True vector SVG — razor sharp on business cards, shirts and signs. 100% free download.'],
                ['text' => 'Pick style and colours, get four concepts per round. Refine until it is yours — free.'],
            ],
            'path1' => 'logo-maker',
        ];

        $this->searchCampaign(
            name: 'RMP — Business Cards (Search)',
            budgetName: 'RMP Business Cards Budget',
            budgetMicros: 280_000_000,       // 280 RON/day ≈ $62
            cpcCeilMicros: 27_000_000,       // 27 RON ≈ $6
            finalUrl: self::SITE.'/product/standard-business-cards',
            negatives: ['free business cards', 'vistaprint', 'moo', 'canva', 'gotprint', 'staples', 'template psd', 'mockup', 'dimensions', 'card holder', 'jobs', 'near me'],
            adGroups: [
                'Core' => ['EXACT', ['business cards', 'custom business cards', 'business card printing', 'order business cards', 'business cards online']],
                'Price intent' => ['PHRASE', ['cheap business cards', 'affordable business cards', 'business cards deal', 'business card prices']],
                'Fast' => ['PHRASE', ['business cards fast', 'quick business cards', 'fast business card printing']],
                'Design online' => ['PHRASE', ['design business cards online', 'business card maker', 'create business cards', 'business card templates']],
            ],
            rsa: $rsaBC,
        );

        $rsaQr = [
            'headlines' => [
                ['text' => 'Free QR Code Generator', 'pinnedField' => 'HEADLINE_1'],
                ['text' => 'QR Codes In One Click'], ['text' => 'URL, vCard, Email Or Phone'],
                ['text' => 'Download SVG + PNG Free'], ['text' => 'No Signup. No Watermark.'],
                ['text' => 'Print-Ready Vector QR'], ['text' => 'Make Your QR Code Free'],
                ['text' => 'Codes That Never Expire'], ['text' => 'QR For Your Business'],
                ['text' => 'Then Print It On Anything'], ['text' => 'Free Forever. Unlimited.'],
                ['text' => 'Scan-To-Save Contact QR'],
            ],
            'descriptions' => [
                ['text' => 'Create a free QR code for your website, contact card, email or phone — in seconds.'],
                ['text' => 'True vector SVG plus high-res PNG. Print-ready, no watermark, no account needed.'],
                ['text' => 'Static codes that never expire — free, unlimited, yours for commercial use.'],
                ['text' => 'Point it at your site, then see it printed on business cards, stickers and more.'],
            ],
            'path1' => 'qr-codes',
        ];

        $this->searchCampaign(
            name: 'RMP — QR Code Generator (Search)',
            budgetName: 'RMP QR Generator Budget',
            budgetMicros: 90_000_000,        // 90 RON/day ≈ $20
            cpcCeilMicros: 7_000_000,        // 7 RON ≈ $1.55 — QR terms run cheap
            finalUrl: self::SITE.'/qr-code-generator',
            negatives: ['api', 'python', 'javascript', 'library', 'scanner', 'reader app', 'dynamic qr pricing', 'tracking analytics', 'barcode inventory'],
            adGroups: [
                'Generator' => ['EXACT', ['qr code generator', 'free qr code generator', 'qr code maker', 'create qr code', 'qr generator']],
                'Use case' => ['PHRASE', ['qr code for website', 'qr code for business card', 'vcard qr code', 'contact qr code', 'qr code for menu']],
            ],
            rsa: $rsaQr,
        );

        $this->searchCampaign(
            name: 'RMP — AI Logo Maker (Search)',
            budgetName: 'RMP Logo Maker Budget',
            budgetMicros: 180_000_000,       // 180 RON/day ≈ $40
            cpcCeilMicros: 11_000_000,       // 11 RON ≈ $2.40
            finalUrl: self::SITE.'/logo-maker',
            negatives: ['designer job', 'hire designer', 'logo course', 'photoshop', 'illustrator tutorial', 'png to svg', 'tattoo', 'canva', 'looka', 'wix'],
            adGroups: [
                'Maker' => ['EXACT', ['logo maker', 'free logo maker', 'ai logo maker', 'logo generator', 'free logo generator']],
                'Create design' => ['PHRASE', ['create a logo', 'make a logo', 'design a logo online', 'free logo design', 'logo creator']],
                'Business intent' => ['PHRASE', ['logo for small business', 'business logo maker', 'company logo maker']],
            ],
            rsa: $rsaLogo,
        );
    }

    private function searchCampaign(string $name, string $budgetName, int $budgetMicros, int $cpcCeilMicros, string $finalUrl, array $negatives, array $adGroups, array $rsa): void
    {
        if ($this->existing('campaign', $name)) {
            $this->line("campaign exists, skipping: $name");

            return;
        }

        $budget = $this->existing('campaign_budget', $budgetName)
            ?? $this->mutate('campaignBudgets', [['create' => [
                'name' => $budgetName, 'amountMicros' => (string) $budgetMicros, 'deliveryMethod' => 'STANDARD', 'explicitlyShared' => false,
            ]]])[0];

        $campaign = $this->mutate('campaigns', [['create' => [
            'name' => $name, 'status' => 'PAUSED', 'advertisingChannelType' => 'SEARCH',
            'campaignBudget' => $budget,
            'targetSpend' => ['cpcBidCeilingMicros' => (string) $cpcCeilMicros],
            'networkSettings' => ['targetGoogleSearch' => true, 'targetSearchNetwork' => false, 'targetContentNetwork' => false, 'targetPartnerSearchNetwork' => false],
            'geoTargetTypeSetting' => ['positiveGeoTargetType' => 'PRESENCE', 'negativeGeoTargetType' => 'PRESENCE'],
            'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
        ]]])[0];
        $this->info("campaign created (PAUSED): $name");

        // USA + English + campaign-level negative keywords
        $crit = [
            ['create' => ['campaign' => $campaign, 'location' => ['geoTargetConstant' => self::US]]],
            ['create' => ['campaign' => $campaign, 'language' => ['languageConstant' => self::EN]]],
        ];
        foreach ($negatives as $neg) {
            $crit[] = ['create' => ['campaign' => $campaign, 'negative' => true, 'keyword' => ['text' => $neg, 'matchType' => 'PHRASE']]];
        }
        $this->mutate('campaignCriteria', $crit);

        foreach ($adGroups as $agName => [$match, $keywords]) {
            $ag = $this->mutate('adGroups', [['create' => [
                'name' => "$name · $agName", 'campaign' => $campaign, 'status' => 'ENABLED', 'type' => 'SEARCH_STANDARD',
            ]]])[0];
            $this->mutate('adGroupCriteria', array_map(fn ($kw) => ['create' => [
                'adGroup' => $ag, 'status' => 'ENABLED', 'keyword' => ['text' => $kw, 'matchType' => $match],
            ]], $keywords));
            $this->mutate('adGroupAds', [['create' => [
                'adGroup' => $ag, 'status' => 'ENABLED',
                'ad' => ['finalUrls' => [$finalUrl], 'responsiveSearchAd' => $rsa],
            ]]]);
            $this->line("  ad group ready: $agName (".count($keywords)." keywords + RSA)");
        }
    }

    // ---- shopping ------------------------------------------------------------

    /**
     * Standard Shopping campaign focused on business cards. Needs a linked
     * Merchant Center (GOOGLE_ADS_MERCHANT_ID) — the feed /feed/google.xml sets
     * g:product_type to the category name, so a campaign-level listing scope of
     * productType LEVEL1 = "Business Cards" limits it to those products; a single
     * "all products" listing-group UNIT bids on that scoped inventory. PAUSED.
     */
    private function shoppingCampaign(?string $merchantId): void
    {
        $name = 'RMP — Business Cards (Shopping)';
        if (! $merchantId) {
            $this->warn("skip Shopping: link Merchant Center + set GOOGLE_ADS_MERCHANT_ID (feed ready at /feed/google.xml). \"$name\" not created.");

            return;
        }
        if ($this->existing('campaign', $name)) {
            $this->line("campaign exists, skipping: $name");

            return;
        }

        $budget = $this->existing('campaign_budget', 'RMP Shopping Budget')
            ?? $this->mutate('campaignBudgets', [['create' => [
                'name' => 'RMP Shopping Budget', 'amountMicros' => (string) 180_000_000, // 180 RON/day ≈ $40
                'deliveryMethod' => 'STANDARD', 'explicitlyShared' => false,
            ]]])[0];

        $campaign = $this->mutate('campaigns', [['create' => [
            'name' => $name, 'status' => 'PAUSED', 'advertisingChannelType' => 'SHOPPING',
            'campaignBudget' => $budget,
            'manualCpc' => ['enhancedCpcEnabled' => false],
            'shoppingSetting' => [
                'merchantId' => (string) $merchantId,
                'feedLabel' => 'US',
                'campaignPriority' => 0,
            ],
            'networkSettings' => ['targetGoogleSearch' => true, 'targetSearchNetwork' => false, 'targetContentNetwork' => false, 'targetPartnerSearchNetwork' => false],
            'geoTargetTypeSetting' => ['positiveGeoTargetType' => 'PRESENCE', 'negativeGeoTargetType' => 'PRESENCE'],
            'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
        ]]])[0];
        $this->info("campaign created (PAUSED): $name");

        // USA + English + limit the whole campaign's inventory to Business Cards
        $this->mutate('campaignCriteria', [
            ['create' => ['campaign' => $campaign, 'location' => ['geoTargetConstant' => self::US]]],
            ['create' => ['campaign' => $campaign, 'language' => ['languageConstant' => self::EN]]],
            ['create' => ['campaign' => $campaign, 'listingScope' => ['dimensions' => [
                ['productType' => ['level' => 'LEVEL1', 'value' => 'Business Cards']],
            ]]]],
        ]);

        $ag = $this->mutate('adGroups', [['create' => [
            'name' => "$name · Products", 'campaign' => $campaign, 'status' => 'ENABLED', 'type' => 'SHOPPING_PRODUCT_ADS',
        ]]])[0];

        // one root "everything" listing-group UNIT (the scoped = business cards)
        $this->mutate('adGroupCriteria', [['create' => [
            'adGroup' => $ag, 'status' => 'ENABLED',
            'listingGroup' => ['type' => 'UNIT'],
            'cpcBidMicros' => (string) 3_000_000, // 3 RON max CPC ≈ $0.65
        ]]]);

        // a Product Shopping Ad pulls creative from the Merchant feed
        $this->mutate('adGroupAds', [['create' => [
            'adGroup' => $ag, 'status' => 'ENABLED', 'ad' => ['shoppingProductAd' => (object) []],
        ]]]);
        $this->line('  shopping ad group ready: Products (business-cards listing scope + product ad)');
    }

    // ---- remarketing ---------------------------------------------------------

    private function userLists(): array
    {
        $lists = [];
        foreach ([
            'all' => ['RMP All Visitors', 'runmyprint.com'],
            'logo' => ['RMP Logo Maker Visitors', 'runmyprint.com/logo-maker'],
        ] as $key => [$name, $urlContains]) {
            $lists[$key] = $this->existing('user_list', $name)
                ?? $this->mutate('userLists', [['create' => [
                    'name' => $name, 'membershipLifeSpan' => '30',
                    'ruleBasedUserList' => ['prepopulationStatus' => 'REQUESTED', 'flexibleRuleUserList' => [
                        'inclusiveRuleOperator' => 'AND',
                        'inclusiveOperands' => [['rule' => ['ruleItemGroups' => [['ruleItems' => [[
                            'name' => 'url__', 'stringRuleItem' => ['operator' => 'CONTAINS', 'value' => $urlContains],
                        ]]]]]]],
                    ]],
                ]]])[0];
        }
        $this->info('user lists ready (visitors backfill once the tag is live)');

        return $lists;
    }

    private function remarketingCampaign(string $userList): void
    {
        $name = 'RMP — Remarketing (Display)';
        if ($this->existing('campaign', $name)) {
            $this->line("campaign exists, skipping: $name");

            return;
        }

        $budget = $this->existing('campaign_budget', 'RMP Remarketing Budget')
            ?? $this->mutate('campaignBudgets', [['create' => [
                'name' => 'RMP Remarketing Budget', 'amountMicros' => '90000000', 'deliveryMethod' => 'STANDARD', 'explicitlyShared' => false, // 90 RON ≈ $20
            ]]])[0];

        $campaign = $this->mutate('campaigns', [['create' => [
            'name' => $name, 'status' => 'PAUSED', 'advertisingChannelType' => 'DISPLAY',
            'campaignBudget' => $budget, 'maximizeConversions' => (object) [],
            'geoTargetTypeSetting' => ['positiveGeoTargetType' => 'PRESENCE', 'negativeGeoTargetType' => 'PRESENCE'],
            'containsEuPoliticalAdvertising' => 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING',
        ]]])[0];
        $this->mutate('campaignCriteria', [
            ['create' => ['campaign' => $campaign, 'location' => ['geoTargetConstant' => self::US]]],
            ['create' => ['campaign' => $campaign, 'language' => ['languageConstant' => self::EN]]],
        ]);
        $this->info("campaign created (PAUSED): $name");

        $ag = $this->mutate('adGroups', [['create' => [
            'name' => "$name · All visitors", 'campaign' => $campaign, 'status' => 'ENABLED', 'type' => 'DISPLAY_STANDARD',
        ]]])[0];
        $this->mutate('adGroupCriteria', [['create' => [
            'adGroup' => $ag, 'status' => 'ENABLED', 'userList' => ['userList' => $userList],
        ]]]);

        // image assets from our own storage: hero (landscape), product (square), brand logo
        $landscape = $this->imageAsset('RMP Landscape', 'heroes/home.webp', 1200, 628);
        $square = $this->imageAsset('RMP Square', 'products/standard-business-cards.webp', 600, 600);
        $logo = $this->imageAsset('RMP Logo', 'brand/logo.png', 512, 512, pad: true);

        $this->mutate('adGroupAds', [['create' => [
            'adGroup' => $ag, 'status' => 'ENABLED',
            'ad' => [
                'finalUrls' => [self::SITE.'/cart'],
                'responsiveDisplayAd' => [
                    'marketingImages' => [['asset' => $landscape]],
                    'squareMarketingImages' => [['asset' => $square]],
                    'squareLogoImages' => $logo ? [['asset' => $logo]] : [], // logoImages is the 4:1 slot; 1:1 goes here
                    'headlines' => [['text' => 'Pick Up Where You Left Off'], ['text' => '50 Business Cards For $7.50'], ['text' => 'Your Logo On Real Products']],
                    'longHeadline' => ['text' => 'Come back and put your logo on business cards, shirts and more — from $7.50'],
                    'descriptions' => [['text' => 'Your designs are saved — finish your order in minutes.'], ['text' => 'Business cards from $7.50. Free shipping over $50.']],
                    'businessName' => 'RunMyPrint',
                ],
            ],
        ]]]);
        $this->info('responsive display ad created');
    }

    /** Centre-crop a storage image to exactly {w}x{h}, upload as an IMAGE asset. */
    private function imageAsset(string $name, string $path, int $w, int $h, bool $pad = false): ?string
    {
        if ($existing = $this->existing('asset', $name)) {
            return $existing;
        }
        if (! Storage::disk('public')->exists($path)) {
            $this->warn("image missing, skipping asset: $path");

            return null;
        }

        $src = imagecreatefromstring((string) Storage::disk('public')->get($path));
        $sw = imagesx($src);
        $sh = imagesy($src);
        $dst = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        if ($pad) {
            // fit inside with white padding (logos must not be cropped)
            $scale = min($w / $sw, $h / $sh) * 0.9;
            $nw = (int) ($sw * $scale);
            $nh = (int) ($sh * $scale);
            imagecopyresampled($dst, $src, (int) (($w - $nw) / 2), (int) (($h - $nh) / 2), 0, 0, $nw, $nh, $sw, $sh);
        } else {
            // cover: centre-crop to the target aspect
            $scale = max($w / $sw, $h / $sh);
            $cw = (int) ($w / $scale);
            $ch = (int) ($h / $scale);
            imagecopyresampled($dst, $src, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $w, $h, $cw, $ch);
        }
        ob_start();
        imagejpeg($dst, null, 88);
        $bytes = ob_get_clean();

        $res = $this->mutate('assets', [['create' => [
            'name' => $name, 'type' => 'IMAGE', 'imageAsset' => ['data' => base64_encode($bytes)],
        ]]])[0];
        $this->line("  asset uploaded: $name (".$w.'x'.$h.')');

        return $res;
    }
}
