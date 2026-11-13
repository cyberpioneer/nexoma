<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Button from '@/components/Button.vue';
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

function setSpeed(value: (typeof SPEEDS)[number]): void {
    speed.value = value;
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

        <div class="flex flex-wrap items-center gap-4">
            <Button
                :aria-label="
                    playing
                        ? t('dashboard.briefing.stop')
                        : t('dashboard.briefing.play')
                "
                :aria-pressed="playing ? 'true' : 'false'"
                @click="togglePlayback"
            >
                {{
                    playing
                        ? t('dashboard.briefing.stop')
                        : t('dashboard.briefing.play')
                }}
            </Button>

            <div class="flex min-w-48 flex-1 items-center gap-3">
                <span class="w-12 text-sm text-muted tabular-nums">{{
                    formatTime(currentMs)
                }}</span>
                <input
                    type="range"
                    min="0"
                    :max="durationMs"
                    step="100"
                    :value="currentMs"
                    :aria-label="t('dashboard.briefing.progress')"
                    :aria-valuetext="`${formatTime(currentMs)} / ${formatTime(durationMs)} (${progressPercent} %)`"
                    class="h-2 min-h-6 w-full cursor-pointer accent-ink"
                    @input="seek"
                />
                <span class="w-12 text-sm text-muted tabular-nums">{{
                    formatTime(durationMs)
                }}</span>
            </div>
        </div>

        <div
            role="group"
            :aria-label="t('dashboard.briefing.speed')"
            class="flex flex-wrap items-center gap-2"
        >
            <span class="text-sm font-medium">{{
                t('dashboard.briefing.speed')
            }}</span>
            <button
                v-for="option in SPEEDS"
                :key="option"
                type="button"
                :aria-pressed="speed === option ? 'true' : 'false'"
                :aria-label="
                    t('dashboard.briefing.speed_option', {
                        speed: `${option}x`,
                    })
                "
                :class="
                    cn(
                        'min-h-11 min-w-11 rounded-md border px-3 text-sm font-medium transition-colors',
                        speed === option
                            ? 'border-ink bg-ink text-white'
                            : 'border-line-strong bg-white text-ink hover:bg-surface',
                    )
                "
                @click="setSpeed(option)"
            >
                {{ option }}x
            </button>
        </div>

        <p v-if="failed" role="alert" class="text-sm text-danger">
            {{ t('dashboard.briefing.error') }}
        </p>
    </div>
</template>
