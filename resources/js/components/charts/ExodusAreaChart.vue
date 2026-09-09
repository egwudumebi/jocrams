<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    labels: {
        type: Array,
        default: () => [],
    },
    series: {
        type: Array,
        default: () => [],
    },
    target: {
        type: Number,
        default: null,
    },
    height: {
        type: Number,
        default: 240,
    },
});

const chartId = `exodus-${Math.random().toString(36).slice(2, 9)}`;
const hoverIndex = ref(null);

const padding = { top: 20, right: 16, bottom: 32, left: 8 };
const width = 640;

const maxValue = computed(() => {
    const values = props.series.flatMap((item) => item.values ?? []);
    const peak = values.length ? Math.max(...values) : 0;
    const target = props.target ?? 0;

    return Math.max(peak, target, 1);
});

const chartHeight = computed(() => props.height - padding.top - padding.bottom);

function pointCoords(values) {
    const count = Math.max(values.length, 1);
    const innerWidth = width - padding.left - padding.right;

    return values.map((value, index) => {
        const x = padding.left + (index / Math.max(count - 1, 1)) * innerWidth;
        const y = padding.top + chartHeight.value - (value / maxValue.value) * chartHeight.value;

        return { x, y, value };
    });
}

function smoothPath(points) {
    if (!points.length) {
        return '';
    }

    if (points.length === 1) {
        return `M ${points[0].x},${points[0].y}`;
    }

    let path = `M ${points[0].x},${points[0].y}`;

    for (let index = 0; index < points.length - 1; index += 1) {
        const previous = points[index - 1] || points[index];
        const current = points[index];
        const next = points[index + 1];
        const following = points[index + 2] || next;

        const control1x = current.x + (next.x - previous.x) / 6;
        const control1y = current.y + (next.y - previous.y) / 6;
        const control2x = next.x - (following.x - current.x) / 6;
        const control2y = next.y - (following.y - current.y) / 6;

        path += ` C ${control1x},${control1y} ${control2x},${control2y} ${next.x},${next.y}`;
    }

    return path;
}

function areaPath(points) {
    if (!points.length) {
        return '';
    }

    const baseline = padding.top + chartHeight.value;
    const line = smoothPath(points);

    return `${line} L ${points[points.length - 1].x},${baseline} L ${points[0].x},${baseline} Z`;
}

const renderedSeries = computed(() =>
    props.series.map((item, index) => {
        const points = pointCoords(item.values ?? []);

        return {
            ...item,
            index,
            points,
            linePath: smoothPath(points),
            areaPath: areaPath(points),
            fillId: `${chartId}-fill-${index}`,
            glowId: `${chartId}-glow-${index}`,
        };
    }),
);

const targetY = computed(() => {
    if (props.target == null) {
        return null;
    }

    return padding.top + chartHeight.value - (props.target / maxValue.value) * chartHeight.value;
});

const activeIndex = computed(() => {
    if (hoverIndex.value == null || !props.labels.length) {
        return null;
    }

    return Math.min(Math.max(hoverIndex.value, 0), props.labels.length - 1);
});

const tooltip = computed(() => {
    const index = activeIndex.value;

    if (index == null) {
        return null;
    }

    return {
        label: props.labels[index],
        items: props.series.map((item) => ({
            name: item.name,
            value: item.values?.[index] ?? 0,
            stroke: item.stroke,
        })),
        x: renderedSeries.value[0]?.points[index]?.x ?? padding.left,
    };
});

function handlePointerMove(event) {
    const svg = event.currentTarget;
    const rect = svg.getBoundingClientRect();
    const clientX = event.touches?.[0]?.clientX ?? event.clientX;
    const relativeX = ((clientX - rect.left) / rect.width) * width;
    const innerWidth = width - padding.left - padding.right;
    const normalized = (relativeX - padding.left) / Math.max(innerWidth, 1);
    const index = Math.round(normalized * Math.max(props.labels.length - 1, 0));

    hoverIndex.value = index;
}

function clearHover() {
    hoverIndex.value = null;
}
</script>

<template>
    <div class="exodus-chart">
        <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-slate-950 via-slate-900 to-[#0a3d91]/90 p-3 sm:p-4">
            <div
                class="pointer-events-none absolute inset-0 opacity-40"
                style="background: radial-gradient(circle at 20% 0%, rgba(56, 189, 248, 0.25), transparent 45%), radial-gradient(circle at 80% 100%, rgba(244, 180, 0, 0.18), transparent 40%);"
            />

            <svg
                :viewBox="`0 0 ${width} ${height}`"
                class="relative z-10 h-auto w-full touch-none select-none"
                preserveAspectRatio="none"
                @mousemove="handlePointerMove"
                @mouseleave="clearHover"
                @touchmove.prevent="handlePointerMove"
                @touchend="clearHover"
            >
                <defs>
                    <filter
                        v-for="item in renderedSeries"
                        :id="item.glowId"
                        :key="item.glowId"
                        x="-20%"
                        y="-20%"
                        width="140%"
                        height="140%"
                    >
                        <feGaussianBlur stdDeviation="2.5" result="blur" />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>

                    <linearGradient
                        v-for="item in renderedSeries"
                        :id="item.fillId"
                        :key="item.fillId"
                        x1="0"
                        x2="0"
                        y1="0"
                        y2="1"
                    >
                        <stop offset="0%" :stop-color="item.fillFrom" stop-opacity="0.45" />
                        <stop offset="100%" :stop-color="item.fillTo" stop-opacity="0" />
                    </linearGradient>
                </defs>

                <!-- horizontal grid -->
                <g opacity="0.15">
                    <line
                        v-for="step in 4"
                        :key="step"
                        :x1="padding.left"
                        :x2="width - padding.right"
                        :y1="padding.top + (chartHeight / 4) * (step - 1)"
                        :y2="padding.top + (chartHeight / 4) * (step - 1)"
                        stroke="white"
                        stroke-width="1"
                    />
                </g>

                <!-- target -->
                <g v-if="targetY != null">
                    <line
                        :x1="padding.left"
                        :x2="width - padding.right"
                        :y1="targetY"
                        :y2="targetY"
                        stroke="#34d399"
                        stroke-width="1.5"
                        stroke-dasharray="6 6"
                        opacity="0.7"
                    />
                    <text
                        :x="width - padding.right"
                        :y="targetY - 6"
                        text-anchor="end"
                        fill="#6ee7b7"
                        font-size="11"
                        font-weight="600"
                    >
                        Target
                    </text>
                </g>

                <!-- areas & lines -->
                <g v-for="item in renderedSeries" :key="item.key || item.name">
                    <path
                        :d="item.areaPath"
                        :fill="`url(#${item.fillId})`"
                    />
                    <path
                        :d="item.linePath"
                        fill="none"
                        :stroke="item.stroke"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        :filter="`url(#${item.glowId})`"
                    />
                </g>

                <!-- hover crosshair -->
                <g v-if="activeIndex != null && renderedSeries[0]?.points[activeIndex]">
                    <line
                        :x1="renderedSeries[0].points[activeIndex].x"
                        :x2="renderedSeries[0].points[activeIndex].x"
                        :y1="padding.top"
                        :y2="padding.top + chartHeight"
                        stroke="rgba(255,255,255,0.25)"
                        stroke-width="1"
                        stroke-dasharray="4 4"
                    />
                    <circle
                        v-for="item in renderedSeries"
                        :key="`${item.name}-dot`"
                        :cx="item.points[activeIndex]?.x"
                        :cy="item.points[activeIndex]?.y"
                        r="5"
                        :fill="item.stroke"
                        stroke="white"
                        stroke-width="2"
                    />
                </g>

                <!-- x labels -->
                <g v-for="(label, index) in labels" :key="label">
                    <text
                        v-if="renderedSeries[0]?.points[index]"
                        :x="renderedSeries[0].points[index].x"
                        :y="height - 8"
                        text-anchor="middle"
                        fill="rgba(255,255,255,0.55)"
                        font-size="11"
                        font-weight="500"
                    >
                        {{ label }}
                    </text>
                </g>
            </svg>

            <div
                v-if="tooltip"
                class="pointer-events-none absolute z-20 min-w-[9rem] rounded-lg border border-white/10 bg-slate-950/90 px-3 py-2 text-xs shadow-xl backdrop-blur-sm"
                :style="{
                    left: `${Math.min(Math.max((tooltip.x / width) * 100, 8), 72)}%`,
                    top: '12px',
                }"
            >
                <p class="mb-1.5 font-semibold text-white">{{ tooltip.label }}</p>
                <div v-for="item in tooltip.items" :key="item.name" class="flex items-center justify-between gap-4 py-0.5">
                    <span class="flex items-center gap-1.5 text-slate-300">
                        <span class="size-2 rounded-full" :style="{ backgroundColor: item.stroke }" />
                        {{ item.name }}
                    </span>
                    <span class="font-semibold tabular-nums text-white">{{ item.value }}</span>
                </div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-500">
            <span
                v-for="item in series"
                :key="item.name"
                class="flex items-center gap-2"
            >
                <span class="size-2.5 rounded-full" :style="{ backgroundColor: item.stroke }" />
                {{ item.name }}
            </span>
            <span v-if="target != null" class="flex items-center gap-2">
                <span class="h-0.5 w-4 border-t-2 border-dashed border-emerald-400" />
                Target
            </span>
        </div>
    </div>
</template>
