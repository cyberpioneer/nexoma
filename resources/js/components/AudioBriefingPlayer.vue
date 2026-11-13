<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import { useTranslate } from '@/lib/translate';
import { stream } from '@/routes/audio-briefings';
import type { AudioBriefingSummary } from '@/types';

const SPEEDS = [1, 1.25, 1.5, 2] as const;

const props = defineProps<{ briefing: AudioBriefingSummary }>();

const { t } = useTranslate();

const audio = ref<HTMLAudioElement | null>(null);
const playing = ref(false);
const failed = ref(false);
const speed = ref<(typeof SPEEDS)[number]>(1);
const currentMs = ref(0);
const loadedDurationMs = ref(0);

const BAR_COUNT = 56;

/**
 * Dekorative Wellenform (keine echten Audiodaten): feste, vom Briefing abhaengige Balkenhoehen in Prozent,
 * damit jedes Briefing seine eigene, stabile Form hat.
 */
const bars = computed(() => {
    let seed = (props.briefing.id * 2654435761) % 2147483647 || 1;

    return Array.from({ length: BAR_COUNT }, () => {
        seed = (seed * 48271) % 2147483647;

        return 18 + Math.round((seed / 2147483647) * 82);
    });
});

const source = computed(() => stream(props.briefing.id).url);
const durationMs = computed(() =>
    Math.max(loadedDurationMs.value, props.briefing.durationMs, 1),
);
const progressPercent = computed(() =>
    Math.min(100, Math.round((currentMs.value / durationMs.value) * 100)),
);

/** Sekunden als m:ss (z. B. 83000 ms -> 1:23). */
function formatTime(ms: number): string {
    const seconds = Math.max(0, Math.floor(ms / 1000));

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

function applySpeed(): void {
    if (audio.value !== null) {
        audio.value.defaultPlaybackRate = speed.value;
        audio.value.playbackRate = speed.value;
    }
}

async function togglePlayback(): Promise<void> {
    const element = audio.value;

    if (element === null) {
        return;
    }

    if (playing.value) {
        element.pause();
        element.currentTime = 0;
        currentMs.value = 0;
        playing.value = false;

        return;
    }

    failed.value = false;
    applySpeed();

    try {
        // Es soll immer nur ein Briefing gleichzeitig laufen (die anderen melden `pause` und setzen ihren Zustand zurueck).
        document.querySelectorAll('audio').forEach((other) => {
            if (other !== element) {
                other.pause();
            }
        });
        await element.play();
        playing.value = true;
    } catch {
        playing.value = false;
        failed.value = true;
    }
}

/** Schaltet reihum 1x -> 1.25x -> 1.5x -> 2x -> 1x. */
function cycleSpeed(): void {
    speed.value = SPEEDS[(SPEEDS.indexOf(speed.value) + 1) % SPEEDS.length];
    applySpeed();
}

function seek(event: Event): void {
    const value = Number((event.target as HTMLInputElement).value);

    currentMs.value = value;

    if (audio.value !== null) {
        audio.value.currentTime = value / 1000;
    }
}

function onTimeUpdate(): void {
    if (audio.value !== null) {
        currentMs.value = Math.round(audio.value.currentTime * 1000);
    }
}

function onLoadedMetadata(): void {
    if (audio.value !== null && Number.isFinite(audio.value.duration)) {
        loadedDurationMs.value = Math.round(audio.value.duration * 1000);
    }

    applySpeed();
}

function onEnded(): void {
    playing.value = false;
    currentMs.value = 0;
}

function onError(): void {
    playing.value = false;
    failed.value = true;
}

watch(
    () => props.briefing.id,
    () => {
        audio.value?.pause();
        playing.value = false;
        failed.value = false;
        currentMs.value = 0;
        loadedDurationMs.value = 0;
    },
);

onBeforeUnmount(() => audio.value?.pause());
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Das Element bleibt unsichtbar; Bedienung ueber die eigenen, zugaenglichen Steuerelemente. -->
        <audio
            ref="audio"
            :src="source"
            preload="none"
            @timeupdate="onTimeUpdate"
            @loadedmetadata="onLoadedMetadata"
            @pause="playing = false"
            @ended="onEnded"
            @error="onError"
        />

        <div
            class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-lg border border-line bg-white p-3 shadow-card"
        >
            <button
                type="button"
                :aria-label="
                    playing
                        ? t('dashboard.briefing.stop')
                        : t('dashboard.briefing.play')
                "
                :aria-pressed="playing ? 'true' : 'false'"
                class="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-ink text-white transition-colors hover:bg-ink-soft"
                @click="togglePlayback"
            >
                <svg
                    v-if="playing"
                    viewBox="0 0 20 20"
                    class="size-5"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <rect x="4" y="4" width="12" height="12" rx="1.5" />
                </svg>
                <svg
                    v-else
                    viewBox="0 0 20 20"
                    class="size-5"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        d="M6 3.8v12.4a.8.8 0 0 0 1.2.7l10-6.2a.8.8 0 0 0 0-1.4l-10-6.2A.8.8 0 0 0 6 3.8Z"
                    />
                </svg>
            </button>

            <div
                class="relative flex h-12 min-w-40 flex-1 items-center gap-[3px] rounded-md has-[input:focus-visible]:ring-2 has-[input:focus-visible]:ring-focus has-[input:focus-visible]:ring-offset-2 has-[input:focus-visible]:ring-offset-white"
            >
                <span
                    v-for="(height, index) in bars"
                    :key="index"
                    aria-hidden="true"
                    :style="{ height: `${height}%` }"
                    :class="
                        cn(
                            'min-w-[2px] flex-1 rounded-full',
                            ((index + 1) / BAR_COUNT) * 100 <= progressPercent
                                ? 'bg-link'
                                : 'bg-line-strong/35',
                        )
                    "
                />
                <input
                    type="range"
                    min="0"
                    :max="durationMs"
                    step="100"
                    :value="currentMs"
                    :aria-label="t('dashboard.briefing.progress')"
                    :aria-valuetext="`${formatTime(currentMs)} / ${formatTime(durationMs)} (${progressPercent} %)`"
                    class="absolute inset-0 size-full cursor-pointer opacity-0"
                    @input="seek"
                />
            </div>

            <span
                class="font-mono text-sm whitespace-nowrap text-ink-soft tabular-nums"
            >
                {{ formatTime(currentMs) }} / {{ formatTime(durationMs) }}
            </span>

            <button
                type="button"
                :title="t('dashboard.briefing.speed')"
                :aria-label="
                    t('dashboard.briefing.speed_option', {
                        speed: `${speed}x`,
                    })
                "
                class="inline-flex min-h-11 min-w-12 items-center justify-center rounded-md border border-line-strong bg-white px-3 text-sm font-medium text-ink tabular-nums transition-colors hover:bg-surface"
                @click="cycleSpeed"
            >
                {{ speed }}x
            </button>
        </div>

        <p v-if="failed" role="alert" class="text-sm text-danger">
            {{ t('dashboard.briefing.error') }}
        </p>
    </div>
</template>
