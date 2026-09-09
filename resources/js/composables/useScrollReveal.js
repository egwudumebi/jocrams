import { onMounted, onUnmounted, ref } from 'vue';

export function useScrollReveal(options = {}) {
    const element = ref(null);
    const isVisible = ref(false);

    const {
        threshold = 0.15,
        rootMargin = '0px 0px -40px 0px',
        once = true,
    } = options;

    let observer = null;

    onMounted(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            isVisible.value = true;

            return;
        }

        observer = new IntersectionObserver(
            ([entry]) => {
                if (! entry.isIntersecting) {
                    return;
                }

                isVisible.value = true;

                if (once) {
                    observer?.disconnect();
                }
            },
            { threshold, rootMargin },
        );

        if (element.value) {
            observer.observe(element.value);
        }
    });

    onUnmounted(() => observer?.disconnect());

    return { element, isVisible };
}
