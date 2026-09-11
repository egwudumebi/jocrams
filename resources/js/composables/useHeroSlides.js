const IMAGES = {
    welcome: '/images/hero-welcome.png',
    journal: '/images/hero-journal.png',
    conference: '/images/hero-conference.png',
    membership: '/images/hero-membership.png',
};

function formatCfpDeadline(dateString) {
    if (!dateString) {
        return 'Open for submissions';
    }

    return `Closes ${new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })}`;
}

/**
 * @param {object} branding
 * @returns {Array<object>}
 */
export function buildDefaultHeroSlides(branding = {}) {
    const siteName = branding.site_name || 'JOCRAMS';
    const journalName = branding.journal_full_name || branding.site_tagline || 'Journal of Communication Research and Media Studies';
    const parentOrg = branding.parent_org?.short_name || 'SICAMA';
    return [
        {
            id: 'welcome',
            theme: 'light',
            eyebrow: 'Enterprise Association Platform',
            title: 'Building Stronger',
            highlight: 'Connections',
            suffix: '. Advancing Our Profession.',
            description: `The official digital home of ${siteName} and ${parentOrg}. Membership, events, and scholarly publishing in one place.`,
            ctaLabel: 'Become a Member',
            ctaTo: '/member/register',
            secondaryCtaLabel: 'Explore Publications',
            secondaryCtaTo: '/downloads',
            imageUrl: IMAGES.welcome,
            imagePosition: 'right center',
            accent: 'gold',
        },
        {
            id: 'journal',
            theme: 'dark',
            eyebrow: 'Research Journal',
            title: journalName,
            highlight: null,
            suffix: null,
            compactTitle: true,
            description: `Browse published articles, meet the editorial board, and discover open calls for papers from ${siteName}.`,
            ctaLabel: 'Browse Journal',
            ctaTo: '/journal',
            secondaryCtaLabel: 'Editorial Board',
            secondaryCtaTo: '/journal/editorial-board',
            imageUrl: IMAGES.journal,
            imagePosition: 'center',
            accent: 'gold',
        },
        {
            id: 'conference',
            theme: 'dark',
            eyebrow: 'Conferences & Events',
            title: 'Where Ideas',
            highlight: 'Take the Stage',
            suffix: null,
            description: 'Conferences, workshops, and member gatherings advancing communication and media scholarship across Nigeria and beyond.',
            ctaLabel: 'View Events',
            ctaTo: '/events',
            secondaryCtaLabel: 'Register Now',
            secondaryCtaTo: '/events',
            imageUrl: IMAGES.conference,
            imagePosition: 'center',
            accent: 'gold',
        },
        {
            id: 'membership',
            theme: 'dark',
            eyebrow: 'Membership',
            title: 'Join',
            highlight: parentOrg,
            suffix: ' today',
            description: 'Access digital credentials, exclusive resources, event privileges, and the full member portal.',
            ctaLabel: 'Join Us Today',
            ctaTo: '/member/register',
            secondaryCtaLabel: 'Learn About SICAMA',
            secondaryCtaTo: '/sicama',
            imageUrl: IMAGES.membership,
            imagePosition: 'center',
            accent: 'gold',
        },
    ];
}

/**
 * @param {Array<object>} calls
 * @returns {Array<object>}
 */
export function buildCfpHeroSlides(calls = []) {
    return calls.slice(0, 2).map((call) => ({
        id: `cfp-${call.uuid}`,
        theme: 'dark',
        eyebrow: 'Call for Papers',
        title: call.title,
        highlight: null,
        suffix: null,
        compactTitle: true,
        description: call.excerpt || formatCfpDeadline(call.closes_at),
        ctaLabel: 'Browse Journal',
        ctaTo: '/journal',
        secondaryCtaLabel: 'Submit Manuscript',
        secondaryCtaTo: '/member/journal/submit',
        imageUrl: IMAGES.conference,
        imagePosition: 'center',
        accent: 'gold',
    }));
}

/**
 * @param {object} options
 * @param {Array<object>} [options.events]
 * @param {Array<object>} [options.calls]
 * @param {object} [options.branding]
 * @returns {Array<object>}
 */
export function resolveHeroSlides({ calls = [], branding = {} } = {}) {
    const defaults = buildDefaultHeroSlides(branding);
    const dynamic = buildCfpHeroSlides(calls);

    if (dynamic.length === 0) {
        return defaults;
    }

    // Welcome first, open CFP promos, then the remaining branded slides.
    return [defaults[0], ...dynamic, ...defaults.slice(1)].slice(0, 6);
}
