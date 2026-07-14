<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    // 'cover' fills the box (good for uniform catalog grids); 'contain' shows the WHOLE
    // image (for generated mockups whose framing/aspect varies — never crop those).
    fit: { type: String, default: 'cover' },
});

const failed = ref(false);
watch(() => props.src, () => (failed.value = false));
</script>

<template>
    <div class="relative h-full w-full overflow-hidden">
        <img
            v-if="src && !failed"
            :src="src"
            :alt="alt"
            loading="lazy"
            class="h-full w-full"
            :class="fit === 'contain' ? 'object-contain' : 'object-cover'"
            @error="failed = true"
        />
        <div
            v-else
            class="flex h-full w-full items-center justify-center bg-gradient-to-br from-paper-200 to-paper-300"
        >
            <svg viewBox="0 0 24 24" class="h-10 w-10 text-brand-700/30" fill="none" stroke="currentColor" stroke-width="1.4">
                <rect x="4" y="3" width="16" height="18" rx="2" />
                <path d="M8 8h8M8 12h8M8 16h5" stroke-linecap="round" />
            </svg>
        </div>
    </div>
</template>
