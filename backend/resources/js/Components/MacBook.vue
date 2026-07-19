<script setup>
defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: 'Website preview' },
});
</script>

<!-- A MacBook drawn in pure SVG; the `src` image becomes the screen. While the
     design is still generating (src null) the screen shows a soft pulse.
     Alternatively the #screen slot renders LIVE content (e.g. an iframe) on the
     display via foreignObject — size it 792×494 (or scale into that box). -->
<template>
    <svg viewBox="0 0 1200 720" role="img" :aria-label="alt" data-mac class="h-auto w-full select-none">
        <defs>
            <linearGradient id="mb-alu" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#e8eaee" />
                <stop offset="0.5" stop-color="#c9ccd3" />
                <stop offset="1" stop-color="#9ea3ad" />
            </linearGradient>
            <linearGradient id="mb-deck" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#f2f3f6" />
                <stop offset="0.45" stop-color="#c4c8d0" />
                <stop offset="1" stop-color="#83878f" />
            </linearGradient>
            <linearGradient id="mb-screen-off" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#1b2230" />
                <stop offset="1" stop-color="#0e131d" />
            </linearGradient>
            <clipPath id="mb-clip">
                <rect x="204" y="44" width="792" height="494" rx="8" />
            </clipPath>
        </defs>

        <!-- lid / screen assembly -->
        <rect x="168" y="8" width="864" height="566" rx="30" fill="url(#mb-alu)" />
        <rect x="176" y="16" width="848" height="550" rx="24" fill="#0b0d12" />
        <!-- camera -->
        <circle cx="600" cy="30" r="4" fill="#1f2734" />
        <circle cx="600" cy="30" r="1.7" fill="#3b4a57" opacity="0.9" />

        <!-- screen -->
        <rect x="204" y="44" width="792" height="494" rx="8" fill="url(#mb-screen-off)" />
        <foreignObject v-if="$slots.screen" x="204" y="44" width="792" height="494" clip-path="url(#mb-clip)">
            <div xmlns="http://www.w3.org/1999/xhtml" style="width:792px;height:494px;overflow:hidden;border-radius:8px;background:#fff;">
                <slot name="screen" />
            </div>
        </foreignObject>
        <image
            v-else-if="src"
            :href="src"
            x="204" y="44" width="792" height="494"
            preserveAspectRatio="xMidYMid slice"
            clip-path="url(#mb-clip)"
        />
        <g v-else clip-path="url(#mb-clip)">
            <rect x="204" y="44" width="792" height="494" fill="url(#mb-screen-off)" />
            <circle cx="566" cy="291" r="9" fill="#6b7686">
                <animate attributeName="opacity" values="0.25;1;0.25" dur="1.2s" repeatCount="indefinite" begin="0s" />
            </circle>
            <circle cx="600" cy="291" r="9" fill="#6b7686">
                <animate attributeName="opacity" values="0.25;1;0.25" dur="1.2s" repeatCount="indefinite" begin="0.2s" />
            </circle>
            <circle cx="634" cy="291" r="9" fill="#6b7686">
                <animate attributeName="opacity" values="0.25;1;0.25" dur="1.2s" repeatCount="indefinite" begin="0.4s" />
            </circle>
        </g>
        <!-- subtle glass reflection -->
        <path d="M204 44h300L204 340z" fill="#ffffff" opacity="0.035" clip-path="url(#mb-clip)" />

        <!-- hinge shadow -->
        <rect x="176" y="560" width="848" height="8" fill="#000000" opacity="0.25" />

        <!-- base / deck -->
        <path d="M56 576h1088c0 0 4 0 4 6v10c0 18-16 30-38 30H90c-22 0-38-12-38-30v-10c0-6 4-6 4-6z" fill="url(#mb-deck)" />
        <!-- thumb scoop -->
        <path d="M528 576h144v6c0 9-10 16-24 16h-96c-14 0-24-7-24-16z" fill="#8d919a" />
        <!-- bottom edge -->
        <rect x="90" y="618" width="1020" height="4" rx="2" fill="#5f636b" opacity="0.55" />
    </svg>
</template>
