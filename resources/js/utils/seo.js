const DEFAULT_KEYWORDS = [
    'communication research',
    'media studies',
    'academic journal',
    'SICAMA',
    'JOCRAMS',
    'scholarship',
    'Nigeria',
].join(', ');

/** @type {Record<string, string>} */
const managedMetaTags = {};

/** @type {HTMLLinkElement | null} */
let canonicalLink = null;

/** @type {HTMLScriptElement | null} */
let jsonLdScript = null;

function absoluteUrl(value, origin) {
    if (!value) {
        return null;
    }

    if (value.startsWith('http://') || value.startsWith('https://')) {
        return value;
    }

    return `${origin}${value.startsWith('/') ? value : `/${value}`}`;
}

function setMeta(attr, key, content) {
    if (content === null || content === undefined || content === '') {
        return;
    }

    const selector = `meta[${attr}="${key}"]`;
    let element = document.head.querySelector(selector);

    if (!element) {
        element = document.createElement('meta');
        element.setAttribute(attr, key);
        document.head.appendChild(element);
    }

    element.setAttribute('content', String(content));
    managedMetaTags[`${attr}:${key}`] = String(content);
}

function setLink(rel, href) {
    if (!href) {
        return;
    }

    if (!canonicalLink) {
        canonicalLink = document.head.querySelector('link[rel="canonical"]');

        if (!canonicalLink) {
            canonicalLink = document.createElement('link');
            canonicalLink.setAttribute('rel', 'canonical');
            document.head.appendChild(canonicalLink);
        }
    }

    canonicalLink.setAttribute('href', href);
}

function setJsonLd(data) {
    if (!data) {
        if (jsonLdScript) {
            jsonLdScript.remove();
            jsonLdScript = null;
        }

        return;
    }

    if (!jsonLdScript) {
        jsonLdScript = document.createElement('script');
        jsonLdScript.setAttribute('type', 'application/ld+json');
        document.head.appendChild(jsonLdScript);
    }

    jsonLdScript.textContent = JSON.stringify(data);
}

/**
 * @param {object} options
 * @param {string} options.title
 * @param {string} [options.description]
 * @param {string} [options.image]
 * @param {string} [options.url]
 * @param {string} [options.type]
 * @param {string} [options.robots]
 * @param {string} [options.keywords]
 * @param {string} [options.siteName]
 * @param {string} [options.locale]
 * @param {object|null} [options.jsonLd]
 */
export function applySeo(options) {
    const origin = window.location.origin;
    const title = options.title || options.siteName || 'JOCRAMS';
    const description = options.description || '';
    const image = absoluteUrl(options.image, origin);
    const url = options.url || `${origin}${window.location.pathname}${window.location.search}`;
    const type = options.type || 'website';
    const robots = options.robots || 'index,follow';
    const keywords = options.keywords || DEFAULT_KEYWORDS;
    const siteName = options.siteName || 'JOCRAMS';
    const locale = options.locale || 'en_NG';

    document.title = title;

    setMeta('name', 'description', description);
    setMeta('name', 'keywords', keywords);
    setMeta('name', 'robots', robots);
    setMeta('name', 'author', siteName);
    setMeta('name', 'application-name', siteName);
    setMeta('name', 'theme-color', '#0f2744');

    setMeta('property', 'og:title', title);
    setMeta('property', 'og:description', description);
    setMeta('property', 'og:type', type);
    setMeta('property', 'og:url', url);
    setMeta('property', 'og:site_name', siteName);
    setMeta('property', 'og:locale', locale);
    if (image) {
        setMeta('property', 'og:image', image);
        setMeta('property', 'og:image:alt', title);
    }

    setMeta('name', 'twitter:card', image ? 'summary_large_image' : 'summary');
    setMeta('name', 'twitter:title', title);
    setMeta('name', 'twitter:description', description);
    if (image) {
        setMeta('name', 'twitter:image', image);
        setMeta('name', 'twitter:image:alt', title);
    }

    setLink('canonical', url);
    setJsonLd(options.jsonLd ?? null);
}

export function buildOrganizationJsonLd(branding, origin) {
    const siteName = branding.site_name || 'JOCRAMS';
    const description = branding.site_tagline || branding.journal_full_name || '';
    const logo = absoluteUrl(branding.site_logo_url || '/images/sicama-logo.png', origin);

    return {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: siteName,
        url: origin,
        logo,
        description,
        parentOrganization: branding.parent_org?.full_name
            ? {
                '@type': 'Organization',
                name: branding.parent_org.full_name,
                alternateName: branding.parent_org.short_name,
            }
            : undefined,
    };
}

export function buildArticleJsonLd({ title, description, url, image, datePublished, authorName, publisherName }) {
    return {
        '@context': 'https://schema.org',
        '@type': 'Article',
        headline: title,
        description,
        url,
        image,
        datePublished,
        author: authorName ? { '@type': 'Person', name: authorName } : undefined,
        publisher: publisherName
            ? {
                '@type': 'Organization',
                name: publisherName,
            }
            : undefined,
    };
}

export function buildEventJsonLd({ title, description, url, image, startDate, endDate, locationName }) {
    return {
        '@context': 'https://schema.org',
        '@type': 'Event',
        name: title,
        description,
        url,
        image,
        startDate,
        endDate,
        eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
        location: locationName
            ? {
                '@type': 'Place',
                name: locationName,
            }
            : undefined,
    };
}
