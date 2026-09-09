<script setup>
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    formatPhoneInput,
    formatPhoneValue,
    getCallingCode,
    getCountryLabel,
    listCountries,
    loadFlagSvg,
} from '../../utils/phone';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    defaultCountry: {
        type: String,
        default: 'NG',
    },
    placeholder: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const rootRef = ref(null);
const inputRef = ref(null);
const countries = listCountries();
const selectedCountry = ref(props.defaultCountry);
const displayValue = ref('');
const flagSvg = ref(null);
const flagSvgs = ref({});
const dropdownOpen = ref(false);

const callingCode = computed(() => getCallingCode(selectedCountry.value));
const countryLabel = computed(() => getCountryLabel(selectedCountry.value));
const placeholderText = computed(() => {
    if (props.placeholder) {
        return props.placeholder;
    }

    return `${callingCode.value} 800 000 0000`;
});

async function refreshFlag(iso2) {
    const svg = await loadFlagSvg(iso2);
    flagSvg.value = svg;
    if (svg) {
        flagSvgs.value = { ...flagSvgs.value, [iso2]: svg };
    }
}

async function loadDropdownFlags() {
    await Promise.all(countries.map(async (iso2) => {
        if (flagSvgs.value[iso2]) {
            return;
        }

        const svg = await loadFlagSvg(iso2);
        if (svg) {
            flagSvgs.value = { ...flagSvgs.value, [iso2]: svg };
        }
    }));
}

function emitFormattedValue(result) {
    const country = result.country || selectedCountry.value;

    if (result.country) {
        selectedCountry.value = result.country;
        refreshFlag(result.country);
    }

    displayValue.value = result.formatted;

    const digits = result.formatted.replace(/\D/g, '');
    const codeDigits = getCallingCode(country).replace(/\D/g, '');

    if (!digits || digits.length <= codeDigits.length) {
        emit('update:modelValue', '');
        return;
    }

    emit('update:modelValue', result.e164 || result.formatted);
}

function applyExternalValue(value) {
    if (!value) {
        selectedCountry.value = props.defaultCountry;
        displayValue.value = '';
        refreshFlag(selectedCountry.value);
        return;
    }

    const result = formatPhoneValue(value, props.defaultCountry);
    selectedCountry.value = result.country || props.defaultCountry;
    displayValue.value = result.formatted;
    refreshFlag(selectedCountry.value);
}

function onInput(event) {
    emitFormattedValue(formatPhoneInput(event.target.value, selectedCountry.value));
}

function selectCountry(iso2) {
    selectedCountry.value = iso2;
    dropdownOpen.value = false;
    refreshFlag(iso2);

    if (displayValue.value.trim()) {
        emitFormattedValue(formatPhoneInput(displayValue.value, iso2));
    }

    inputRef.value?.focus();
}

function toggleDropdown(event) {
    event.stopPropagation();
    dropdownOpen.value = !dropdownOpen.value;
}

function onDocumentClick(event) {
    if (!rootRef.value?.contains(event.target)) {
        dropdownOpen.value = false;
    }
}

watch(() => props.modelValue, (value) => {
    const current = formatPhoneValue(displayValue.value, selectedCountry.value);
    if ((value || '') !== (current.e164 || '')) {
        applyExternalValue(value);
    }
}, { immediate: true });

watch(dropdownOpen, (open) => {
    if (open) {
        loadDropdownFlags();
    }
});

onMounted(() => {
    refreshFlag(selectedCountry.value);
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="rootRef" class="phone-input">
        <button
            type="button"
            class="phone-input-country"
            :aria-label="`Country: ${countryLabel}`"
            :aria-expanded="dropdownOpen"
            @click="toggleDropdown"
        >
            <span
                v-if="flagSvg"
                class="phone-input-flag"
                aria-hidden="true"
                v-html="flagSvg"
            />
            <span v-else class="phone-input-flag-fallback">{{ selectedCountry }}</span>
            <ChevronDownIcon class="size-3.5 shrink-0 text-slate-400" />
        </button>

        <input
            ref="inputRef"
            :value="displayValue"
            type="tel"
            class="phone-input-field"
            :placeholder="placeholderText"
            autocomplete="tel"
            inputmode="tel"
            @input="onInput"
        />

        <div v-if="dropdownOpen" class="phone-input-dropdown">
            <button
                v-for="iso2 in countries"
                :key="iso2"
                type="button"
                class="phone-input-option"
                :class="{ 'phone-input-option-active': iso2 === selectedCountry }"
                @click="selectCountry(iso2)"
            >
                <span
                    v-if="flagSvgs[iso2]"
                    class="phone-input-flag"
                    aria-hidden="true"
                    v-html="flagSvgs[iso2]"
                />
                <span v-else class="phone-input-flag-fallback">{{ iso2 }}</span>
                <span class="min-w-0 flex-1 truncate text-left">{{ getCountryLabel(iso2) }}</span>
                <span class="shrink-0 font-mono text-xs text-slate-500">{{ getCallingCode(iso2) }}</span>
            </button>
        </div>
    </div>
</template>
