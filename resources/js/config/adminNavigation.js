export const adminRouteTitles = {
    'admin.dashboard': 'Dashboard',
    'admin.content': 'Content Dashboard',
    'admin.pages': 'Static Pages',
    'admin.members': 'Active Members',
    'admin.approvals': 'Applications',
    'admin.renewals': 'Renewal Center',
    'admin.events': 'Event Calendar',
    'admin.event-registrations': 'Event Registrations',
    'admin.event-registrations.show': 'Event Details',
    'admin.news': 'News & Blog',
    'admin.publications': 'Publications',
    'admin.journal': 'Journal Submissions',
    'admin.journal.reviewers': 'Journal Reviewers',
    'admin.journal.calls': 'Calls for Papers',
    'admin.journal.volumes': 'Volumes & Issues',
    'admin.journal.editorial-board': 'Editorial Board',
    'admin.journal.categories': 'Journal Categories',
    'admin.journal.fees': 'Fees Catalog',
    'admin.journal.show': 'Journal Submission',
    'admin.assets': 'Media Library',
    'admin.payments': 'Payments',
    'admin.payment-proofs': 'Payment Receipts',
    'admin.reports': 'Reports & Analytics',
    'admin.settings': 'System Settings',
    'admin.help': 'Help & Support',
    'admin.campaigns': 'Campaigns',
};

export const adminNavSections = [
    {
        id: 'membership',
        label: 'Membership Management',
        items: [
            { to: '/admin/members', name: 'admin.members', label: 'Active Members' },
            { to: '/admin/approvals', name: 'admin.approvals', label: 'Applications' },
            { to: '/admin/renewals', name: 'admin.renewals', label: 'Renewal Center' },
        ],
    },
    {
        id: 'events',
        label: 'Event Management',
        items: [
            { to: '/admin/events', name: 'admin.events', label: 'Calendar' },
            { to: '/admin/event-registrations', name: 'admin.event-registrations', label: 'Registrations' },
        ],
    },
    {
        id: 'content',
        label: 'Content & Library',
        items: [
            { to: '/admin/content', name: 'admin.content', label: 'Content Dashboard' },
            { to: '/admin/news', name: 'admin.news', label: 'News & Blog' },
            { to: '/admin/pages', name: 'admin.pages', label: 'Static Pages' },
            { to: '/admin/journal', name: 'admin.journal', label: 'Journal Submissions' },
            { to: '/admin/journal/volumes-issues', name: 'admin.journal.volumes', label: 'Volumes & Issues' },
            { to: '/admin/journal/calls-for-papers', name: 'admin.journal.calls', label: 'Calls for Papers' },
            { to: '/admin/journal/editorial-board', name: 'admin.journal.editorial-board', label: 'Editorial Board' },
            { to: '/admin/journal/categories', name: 'admin.journal.categories', label: 'Journal Categories' },
            { to: '/admin/journal/fees', name: 'admin.journal.fees', label: 'Fees Catalog' },
            { to: '/admin/publications', name: 'admin.publications', label: 'Publications Library' },
            { to: '/admin/assets', name: 'admin.assets', label: 'Media Library' },
        ],
    },
];

export const adminStandaloneLinks = [
    { to: '/admin/payment-proofs', name: 'admin.payment-proofs', label: 'Payment Receipts' },
    { to: '/admin/payments', name: 'admin.payments', label: 'Payments' },
    { to: '/admin/reports', name: 'admin.reports', label: 'Reports & Analytics' },
    { to: '/admin/settings', name: 'admin.settings', label: 'System Settings' },
];

export const adminQuickLinks = [
    { to: '/admin/approvals', label: 'Pending Applications' },
    { to: '/admin/payment-proofs', label: 'Payment Receipts' },
    { to: '/admin/members', label: 'Member Directory' },
    { to: '/admin/renewals', label: 'Renewal Center' },
    { to: '/admin/events', label: 'Event Calendar' },
    { to: '/admin/news', label: 'Publish Content' },
];

export function sectionForRoute(routeName) {
    return adminNavSections.find((section) => section.items.some((item) => item.name === routeName))?.id;
}
