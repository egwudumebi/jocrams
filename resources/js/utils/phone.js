import { AsYouType, getCountries, getCountryCallingCode, parsePhoneNumberFromString } from 'libphonenumber-js';

const flagLoaders = import.meta.glob('../../../node_modules/country-flag-icons/string/3x2/*.js', {
    import: 'default',
});

const flagCache = new Map();

export const priorityCountries = ['NG', 'GH', 'KE', 'ZA', 'US', 'GB', 'CA', 'IN', 'AE', 'FR', 'DE'];

const countryNames = typeof Intl !== 'undefined'
    ? new Intl.DisplayNames(['en'], { type: 'region' })
    : null;

export function getCountryLabel(iso2) {
    if (!iso2) {
        return '';
    }

    try {
        return countryNames?.of(iso2) || iso2;
    } catch {
        return iso2;
    }
}

export function getCallingCode(iso2) {
    if (!iso2) {
        return '';
    }

    try {
        return `+${getCountryCallingCode(iso2)}`;
    } catch {
        return '';
    }
}

export async function loadFlagSvg(iso2) {
    if (!iso2) {
        return null;
    }

    if (flagCache.has(iso2)) {
        return flagCache.get(iso2);
    }

    const entry = Object.entries(flagLoaders).find(([path]) => path.endsWith(`/${iso2}.js`));
    if (!entry) {
        return null;
    }

    const svg = await entry[1]();
    flagCache.set(iso2, svg);
    return svg;
}

export function formatPhoneInput(value, defaultCountry = undefined) {
    const trimmed = (value || '').trim();
    const useAutoDetect = trimmed.startsWith('+') || trimmed.startsWith('00');
    const formatter = useAutoDetect
        ? new AsYouType()
        : new AsYouType(defaultCountry);

    const formatted = formatter.input(value || '');
    const detectedCountry = formatter.getCountry() || (useAutoDetect ? null : defaultCountry) || null;
    const parsed = formatter.getNumber();

    return {
        formatted,
        country: detectedCountry,
        e164: parsed?.number || null,
    };
}

export function formatPhoneValue(value, fallbackCountry = 'NG') {
    if (!value) {
        return {
            formatted: '',
            country: fallbackCountry,
            e164: '',
        };
    }

    const parsed = parsePhoneNumberFromString(value);
    if (parsed) {
        return {
            formatted: parsed.formatInternational(),
            country: parsed.country || fallbackCountry,
            e164: parsed.number,
        };
    }

    return formatPhoneInput(value, fallbackCountry);
}

export function listCountries() {
    const all = getCountries();

    return [
        ...priorityCountries.filter((code) => all.includes(code)),
        ...all.filter((code) => !priorityCountries.includes(code)),
    ];
}
