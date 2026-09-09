import { onMounted, onUnmounted, ref } from 'vue';

function easeOutQuart(progress) {
    return 1 - Math.pow(1 - progress, 4);
}

export function useCountUp(targetValue, options = {}) {
    const displayValue = ref(0);
    const hasStarted = ref(false);

    const {
        duration = 2000,
        decimals = 0,
        formatter = null,
    } = options;

    let frameId = null;
    let observer = null;

    function format(value) {
        if (formatter) {
            return formatter(value);
        }

        return decimals > 0
            ? value.toFixed(decimals)
            : Math.round(value).toLocaleString();
    }

    function animate() {
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            displayValue.value = targetValue * easeOutQuart(progress);

            if (progress < 1) {
                frameId = requestAnimationFrame(tick);
            } else {
                displayValue.value = targetValue;
            }
        };

        frameId = requestAnimationFrame(tick);
    }

    onMounted(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            displayValue.value = targetValue;
            hasStarted.value = true;

            return;
        }

        const element = options.element?.value;

        if (! element) {
            displayValue.value = targetValue;

            return;
        }

        observer = new IntersectionObserver(
            ([entry]) => {
                if (! entry.isIntersecting || hasStarted.value) {
                    return;
                }

                hasStarted.value = true;
                animate();
                observer?.disconnect();
            },
            { threshold: 0.3 },
        );

        observer.observe(element);
    });

    onUnmounted(() => {
        if (frameId) {
            cancelAnimationFrame(frameId);
        }

        observer?.disconnect();
    });

    return { displayValue, format, hasStarted };
}
