export const memberRouteTitles = {
    'member.dashboard': 'Dashboard',
    'member.applications': 'SICAMA Application',
    'member.payments': 'Membership Payments',
    'member.credentials': 'SICAMA Credentials',
    'member.downloads': 'Downloads',
    'member.events': 'Events',
    'member.events.show': 'Event Details',
    'member.journal': 'My Journal',
    'member.journal.submit': 'Submit Article',
    'member.journal.review': 'Review Queue',
    'member.journal.review.show': 'Review Submission',
    'member.journal.show': 'Submission Details',
    'member.profile': 'Profile',
    'member.support': 'Support',
};

/**
 * Grouped member nav: Journal is open to all signed-in users;
 * SICAMA membership covers dues, credentials, and applications.
 */
export const memberNavSections = [
    {
        id: 'overview',
        label: null,
        links: [
            { to: '/member/dashboard', name: 'member.dashboard', label: 'Dashboard' },
        ],
    },
    {
        id: 'journal',
        label: 'Journal',
        hint: 'Submit and track articles — membership not required',
        links: [
            { to: '/member/journal', name: 'member.journal', label: 'My submissions' },
            { to: '/member/journal/submit', name: 'member.journal.submit', label: 'Submit article' },
        ],
    },
    {
        id: 'sicama',
        label: 'SICAMA membership',
        hint: 'Pay dues to receive ID, certificate, and welcome letter',
        links: [
            { to: '/member/applications', name: 'member.applications', label: 'Application' },
            { to: '/member/payments', name: 'member.payments', label: 'Payments' },
            { to: '/member/credentials', name: 'member.credentials', label: 'Credentials' },
        ],
    },
    {
        id: 'account',
        label: 'Account',
        links: [
            { to: '/member/events', name: 'member.events', label: 'Events' },
            { to: '/member/downloads', name: 'member.downloads', label: 'Downloads' },
            { to: '/member/profile', name: 'member.profile', label: 'Profile' },
            { to: '/member/support', name: 'member.support', label: 'Support' },
        ],
    },
];

export const memberNavLinks = memberNavSections.flatMap((section) => section.links);

export const memberQuickLinks = [
    { to: '/member/journal/submit', label: 'Submit article' },
    { to: '/member/credentials', label: 'SICAMA credentials' },
    { to: '/member/payments', label: 'Pay membership' },
    { to: '/member/events', label: 'Browse events' },
];
