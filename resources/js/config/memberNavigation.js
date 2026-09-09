export const memberRouteTitles = {
    'member.dashboard': 'Dashboard',
    'member.applications': 'Applications',
    'member.payments': 'Payments',
    'member.credentials': 'Credentials',
    'member.downloads': 'Downloads',
    'member.events': 'Events',
    'member.events.show': 'Event Details',
    'member.journal': 'Journal',
    'member.journal.submit': 'Submit Article',
    'member.journal.review': 'Review Queue',
    'member.journal.review.show': 'Review Submission',
    'member.journal.show': 'Submission Details',
    'member.profile': 'Profile',
    'member.support': 'Support',
};

export const memberNavLinks = [
    { to: '/member/dashboard', name: 'member.dashboard', label: 'Dashboard' },
    { to: '/member/applications', name: 'member.applications', label: 'Applications' },
    { to: '/member/events', name: 'member.events', label: 'Events' },
    { to: '/member/payments', name: 'member.payments', label: 'Payments' },
    { to: '/member/credentials', name: 'member.credentials', label: 'Credentials' },
    { to: '/member/downloads', name: 'member.downloads', label: 'Downloads' },
    { to: '/member/journal', name: 'member.journal', label: 'Journal' },
    { to: '/member/profile', name: 'member.profile', label: 'Profile' },
    { to: '/member/support', name: 'member.support', label: 'Support' },
];

export const memberQuickLinks = [
    { to: '/member/events', label: 'Browse events' },
    { to: '/member/journal/submit', label: 'Submit article' },
    { to: '/member/credentials', label: 'View credentials' },
    { to: '/member/payments', label: 'Payment history' },
];
