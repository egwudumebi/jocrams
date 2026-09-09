import {
    applySeo,
    buildArticleJsonLd,
    buildEventJsonLd,
    buildOrganizationJsonLd,
} from '../utils/seo';

function brandingDefaults(branding = {}) {
    const siteName = branding.site_name || 'JOCRAMS';
    const tagline = branding.site_tagline || 'Journal of Communication Research and Media Studies';
    const journalName = branding.journal_full_name || tagline;
    const parentOrg = branding.parent_org?.short_name || 'SICAMA';
    const origin = window.location.origin;

    return {
        siteName,
        tagline,
        journalName,
        parentOrg,
        origin,
        image: branding.site_logo_url || '/images/sicama-logo.png',
        description: `${siteName} is the official platform for ${journalName}, published under ${parentOrg}. Explore membership, events, research publications, and manuscript submissions.`,
    };
}

function withSite(title, defaults) {
    return `${title} | ${defaults.siteName}`;
}

function privateAreaSeo(defaults, areaLabel) {
    return {
        title: withSite(areaLabel, defaults),
        description: `${areaLabel} for ${defaults.siteName}.`,
        robots: 'noindex,nofollow',
        type: 'website',
    };
}

/** @type {Record<string, (route: import('vue-router').RouteLocationNormalizedLoaded, branding: object) => object>} */
const routeSeoBuilders = {
    home(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: `${d.siteName} — ${d.tagline}`,
            description: d.description,
            keywords: `${d.siteName}, ${d.parentOrg}, ${d.journalName}, communication research, media studies, academic journal Nigeria`,
            type: 'website',
            jsonLd: buildOrganizationJsonLd(branding, d.origin),
        };
    },
    news(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('News & Updates', d),
            description: `Latest news, announcements, and updates from ${d.siteName} and ${d.parentOrg}.`,
        };
    },
    events(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('Events', d),
            description: `Upcoming conferences, workshops, and professional events hosted by ${d.siteName}.`,
        };
    },
    'public.downloads'(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('Downloads', d),
            description: `Public downloads and resources from ${d.siteName}.`,
        };
    },
    branches(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('Branches', d),
            description: `Find ${d.parentOrg} and ${d.siteName} branches and local chapters.`,
        };
    },
    members(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('Member Directory', d),
            description: `Browse verified members of ${d.siteName} and ${d.parentOrg}.`,
        };
    },
    contact(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('Contact', d),
            description: `Contact ${d.siteName} for membership, submissions, events, and general enquiries.`,
        };
    },
    'public.onboarding'(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('Join Membership', d),
            description: `Apply for membership with ${d.siteName} and ${d.parentOrg}.`,
        };
    },
    verify(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('Verify Credential', d),
            description: `Verify membership credentials and certificates issued by ${d.siteName}.`,
        };
    },
    'verify.token'(route, branding) {
        return routeSeoBuilders.verify(route, branding);
    },
    journal(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite(d.journalName, d),
            description: `Browse published articles and open calls for papers in ${d.journalName}.`,
            type: 'website',
        };
    },
    'journal.editorial-board'(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('Editorial Board', d),
            description: `Meet the editorial board overseeing ${d.journalName}.`,
        };
    },
    sicama(route, branding) {
        const d = brandingDefaults(branding);
        const orgName = branding.parent_org?.full_name || d.parentOrg;

        return {
            title: withSite(orgName, d),
            description: branding.parent_org?.motto || `Learn about ${orgName}, the parent organization behind ${d.siteName}.`,
        };
    },
    'payments.return'(route, branding) {
        const d = brandingDefaults(branding);

        return {
            title: withSite('Payment Confirmation', d),
            description: `Payment confirmation for ${d.siteName}.`,
            robots: 'noindex,nofollow',
        };
    },
};

function memberRouteSeo(route, branding) {
    const d = brandingDefaults(branding);
    const map = {
        'member.login': 'Member Sign In',
        'member.register': 'Member Registration',
        'member.dashboard': 'Member Dashboard',
        'member.applications': 'Membership Applications',
        'member.payments': 'Payments',
        'member.credentials': 'Credentials',
        'member.downloads': 'Downloads',
        'member.events': 'Events',
        'member.profile': 'Profile',
        'member.support': 'Support',
        'member.journal': 'Journal Submissions',
        'member.journal.submit': 'Submit Manuscript',
        'member.journal.review': 'Reviewer Queue',
    };

    return privateAreaSeo(d, map[route.name] || 'Member Portal');
}

function adminRouteSeo(route, branding) {
    const d = brandingDefaults(branding);

    return privateAreaSeo(d, 'Admin Portal');
}

export function resolveRouteSeo(route, branding = window.__APP_SEO__?.branding || {}) {
    const d = brandingDefaults(branding);
    const builder = routeSeoBuilders[route.name];
    let config;

    if (builder) {
        config = builder(route, branding);
    } else if (route.path.startsWith('/admin')) {
        config = adminRouteSeo(route, branding);
    } else if (route.path.startsWith('/member')) {
        config = memberRouteSeo(route, branding);
    } else {
        config = {
            title: d.siteName,
            description: d.description,
        };
    }

    const url = `${d.origin}${route.fullPath}`;

    return {
        siteName: d.siteName,
        image: d.image,
        url,
        locale: 'en_NG',
        robots: config.robots || 'index,follow',
        type: config.type || 'website',
        ...config,
    };
}

export function applyRouteSeo(route, branding = window.__APP_SEO__?.branding || {}) {
    applySeo(resolveRouteSeo(route, branding));
}

export function applyDynamicArticleSeo(route, branding, article) {
    const d = brandingDefaults(branding);
    const title = withSite(article.title, d);
    const description = article.excerpt || article.summary || d.description;
    const url = `${d.origin}${route.fullPath}`;

    applySeo({
        title,
        description,
        url,
        image: article.featured_image_url || article.image_url || d.image,
        type: 'article',
        siteName: d.siteName,
        jsonLd: buildArticleJsonLd({
            title: article.title,
            description,
            url,
            image: absoluteUrl(article.featured_image_url || article.image_url || d.image, d.origin),
            datePublished: article.published_at,
            authorName: article.author_name,
            publisherName: d.siteName,
        }),
    });
}

export function applyDynamicEventSeo(route, branding, event) {
    const d = brandingDefaults(branding);
    const title = withSite(event.title, d);
    const description = event.excerpt || event.description || `Join ${event.title} hosted by ${d.siteName}.`;
    const url = `${d.origin}${route.fullPath}`;

    applySeo({
        title,
        description,
        url,
        image: event.banner_url || event.image_url || d.image,
        type: 'website',
        siteName: d.siteName,
        jsonLd: buildEventJsonLd({
            title: event.title,
            description,
            url,
            image: absoluteUrl(event.banner_url || event.image_url || d.image, d.origin),
            startDate: event.starts_at,
            endDate: event.ends_at,
            locationName: event.venue || event.location,
        }),
    });
}

export function applyDynamicMemberSeo(route, branding, member) {
    const d = brandingDefaults(branding);
    const name = member.display_name || member.name;
    const title = withSite(name, d);
    const description = member.bio
        ? member.bio.slice(0, 160)
        : `${name} is a verified member of ${d.siteName}.`;

    applySeo({
        title,
        description,
        url: `${d.origin}${route.fullPath}`,
        image: member.profile_image_url || d.image,
        type: 'profile',
        siteName: d.siteName,
    });
}

export function applyDynamicJournalArticleSeo(route, branding, article) {
    const d = brandingDefaults(branding);
    const title = withSite(article.title, d);
    const description = article.abstract || `Published article in ${d.journalName}.`;
    const url = `${d.origin}${route.fullPath}`;

    applySeo({
        title,
        description,
        url,
        image: d.image,
        type: 'article',
        siteName: d.siteName,
        jsonLd: buildArticleJsonLd({
            title: article.title,
            description,
            url,
            image: absoluteUrl(d.image, d.origin),
            datePublished: article.date_submitted || article.date_reviewed,
            authorName: article.author_name,
            publisherName: d.journalName,
        }),
    });
}

function absoluteUrl(value, origin) {
    if (!value) {
        return null;
    }

    if (value.startsWith('http://') || value.startsWith('https://')) {
        return value;
    }

    return `${origin}${value.startsWith('/') ? value : `/${value}`}`;
}
