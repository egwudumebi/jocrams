import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import { applyRouteSeo } from '../config/seoRoutes';

const PublicLayout = () => import('../layouts/PublicLayout.vue');
const MemberLayout = () => import('../layouts/MemberLayout.vue');
const AdminLayout = () => import('../layouts/AdminLayout.vue');

const router = createRouter({
    history: createWebHistory(),
    scrollBehavior: () => ({ top: 0 }),
    routes: [
        {
            path: '/',
            component: PublicLayout,
            children: [
                { path: '', name: 'home', component: () => import('../pages/public/Home.vue') },
                { path: 'news', name: 'news', component: () => import('../pages/public/News.vue') },
                { path: 'news/:uuid', name: 'news.show', component: () => import('../pages/public/NewsDetail.vue') },
                { path: 'events', name: 'events', component: () => import('../pages/public/Events.vue') },
                { path: 'events/:uuid', name: 'events.show', component: () => import('../pages/public/EventDetail.vue') },
                { path: 'payments/return', name: 'payments.return', component: () => import('../pages/public/PaymentReturn.vue') },
                { path: 'downloads', name: 'public.downloads', component: () => import('../pages/public/Downloads.vue') },
                { path: 'branches', name: 'branches', component: () => import('../pages/public/Branches.vue') },
                { path: 'members', name: 'members', component: () => import('../pages/public/Members.vue') },
                { path: 'members/:uuid', name: 'members.show', component: () => import('../pages/public/MemberDetail.vue') },
                { path: 'contact', name: 'contact', component: () => import('../pages/public/Contact.vue') },
                { path: 'join', name: 'public.onboarding', component: () => import('../pages/public/Onboarding.vue') },
                { path: 'verify', name: 'verify', component: () => import('../pages/public/Verify.vue') },
                { path: 'verify/:token', name: 'verify.token', component: () => import('../pages/public/Verify.vue') },
                { path: 'journal', name: 'journal', component: () => import('../pages/public/Journal.vue') },
                { path: 'journal/editorial-board', name: 'journal.editorial-board', component: () => import('../pages/public/JournalEditorialBoard.vue') },
                { path: 'journal/:slugOrId', name: 'journal.show', component: () => import('../pages/public/JournalArticle.vue') },
                { path: 'sicama', name: 'sicama', component: () => import('../pages/public/Sicama.vue') },
            ],
        },
        {
            path: '/member',
            component: MemberLayout,
            meta: { requiresMember: true },
            children: [
                { path: 'login', name: 'member.login', component: () => import('../pages/member/Login.vue'), meta: { guest: true } },
                { path: 'register', name: 'member.register', component: () => import('../pages/member/Register.vue'), meta: { guest: true } },
                { path: 'forgot-password', name: 'member.forgot-password', component: () => import('../pages/member/ForgotPassword.vue'), meta: { guest: true } },
                { path: 'reset-password', name: 'member.reset-password', component: () => import('../pages/member/ResetPassword.vue'), meta: { guest: true } },
                { path: 'verify-email', name: 'member.verify-email', component: () => import('../pages/member/VerifyEmail.vue') },
                { path: 'dashboard', name: 'member.dashboard', component: () => import('../pages/member/Dashboard.vue') },
                { path: 'applications', name: 'member.applications', component: () => import('../pages/member/Applications.vue') },
                { path: 'payments', name: 'member.payments', component: () => import('../pages/member/Payments.vue') },
                { path: 'credentials', name: 'member.credentials', component: () => import('../pages/member/Credentials.vue') },
                { path: 'downloads', name: 'member.downloads', component: () => import('../pages/member/Downloads.vue') },
                { path: 'events', name: 'member.events', component: () => import('../pages/member/Events.vue') },
                { path: 'events/:uuid', name: 'member.events.show', component: () => import('../pages/member/EventDetail.vue') },
                { path: 'profile', name: 'member.profile', component: () => import('../pages/member/Profile.vue') },
                { path: 'support', name: 'member.support', component: () => import('../pages/member/Support.vue') },
                { path: 'journal', name: 'member.journal', component: () => import('../pages/member/journal/Index.vue') },
                { path: 'journal/submit', name: 'member.journal.submit', component: () => import('../pages/member/journal/Submit.vue') },
                { path: 'journal/review', name: 'member.journal.review', component: () => import('../pages/member/journal/ReviewQueue.vue') },
                { path: 'journal/review/:id', name: 'member.journal.review.show', component: () => import('../pages/member/journal/ReviewDetail.vue') },
                { path: 'journal/:id', name: 'member.journal.show', component: () => import('../pages/member/journal/SubmissionDetail.vue') },
            ],
        },
        {
            path: '/admin',
            component: AdminLayout,
            meta: { requiresAdmin: true },
            children: [
                { path: 'login', name: 'admin.login', component: () => import('../pages/admin/Login.vue'), meta: { guest: true } },
                { path: 'forgot-password', name: 'admin.forgot-password', component: () => import('../pages/admin/ForgotPassword.vue'), meta: { guest: true } },
                { path: 'reset-password', name: 'admin.reset-password', component: () => import('../pages/admin/ResetPassword.vue'), meta: { guest: true } },
                { path: 'verify-email', name: 'admin.verify-email', component: () => import('../pages/admin/VerifyEmail.vue') },
                { path: 'dashboard', name: 'admin.dashboard', component: () => import('../pages/admin/Dashboard.vue') },
                { path: 'members', name: 'admin.members', component: () => import('../pages/admin/Members.vue') },
                { path: 'approvals', name: 'admin.approvals', component: () => import('../pages/admin/Approvals.vue') },
                { path: 'renewals', name: 'admin.renewals', component: () => import('../pages/admin/Renewals.vue') },
                { path: 'events', name: 'admin.events', component: () => import('../pages/admin/Events.vue') },
                { path: 'event-registrations', name: 'admin.event-registrations', component: () => import('../pages/admin/EventRegistrations.vue') },
                { path: 'event-registrations/:uuid', name: 'admin.event-registrations.show', component: () => import('../pages/admin/EventRegistrationDetail.vue') },
                { path: 'content', name: 'admin.content', component: () => import('../pages/admin/ContentDashboard.vue') },
                { path: 'news', name: 'admin.news', component: () => import('../pages/admin/News.vue') },
                { path: 'pages', name: 'admin.pages', component: () => import('../pages/admin/Pages.vue') },
                { path: 'publications', name: 'admin.publications', component: () => import('../pages/admin/Publications.vue') },
                { path: 'journal', name: 'admin.journal', component: () => import('../pages/admin/journal/Submissions.vue') },
                { path: 'journal/reviewers', name: 'admin.journal.reviewers', component: () => import('../pages/admin/journal/Reviewers.vue') },
                { path: 'journal/calls-for-papers', name: 'admin.journal.calls', component: () => import('../pages/admin/journal/CallsForPapers.vue') },
                { path: 'journal/volumes-issues', name: 'admin.journal.volumes', component: () => import('../pages/admin/journal/PublicationStructure.vue') },
                { path: 'journal/editorial-board', name: 'admin.journal.editorial-board', component: () => import('../pages/admin/journal/EditorialBoard.vue') },
                { path: 'journal/categories', name: 'admin.journal.categories', component: () => import('../pages/admin/journal/Categories.vue') },
                { path: 'journal/fees', name: 'admin.journal.fees', component: () => import('../pages/admin/journal/FeesCatalog.vue') },
                { path: 'journal/:id', name: 'admin.journal.show', component: () => import('../pages/admin/journal/SubmissionDetail.vue') },
                { path: 'assets', name: 'admin.assets', component: () => import('../pages/admin/MediaLibrary.vue') },
                { path: 'payments', name: 'admin.payments', component: () => import('../pages/admin/Payments.vue') },
                { path: 'reports', name: 'admin.reports', component: () => import('../pages/admin/Reports.vue') },
                { path: 'settings', name: 'admin.settings', component: () => import('../pages/admin/Settings.vue') },
                { path: 'help', name: 'admin.help', component: () => import('../pages/admin/Help.vue') },
                { path: 'campaigns', name: 'admin.campaigns', component: () => import('../pages/admin/Campaigns.vue') },
            ],
        },
    ],
});

router.beforeEach((to, from, next) => {
    const { isMemberAuthenticated, isAdminAuthenticated } = useAuth();

    if (to.name === 'admin.login' && isAdminAuthenticated.value) {
        return next(typeof to.query.redirect === 'string' ? to.query.redirect : { name: 'admin.dashboard' });
    }

    if (to.name === 'member.login' && isMemberAuthenticated.value) {
        return next(typeof to.query.redirect === 'string' ? to.query.redirect : { name: 'member.dashboard' });
    }

    if (to.meta.requiresMember && !to.meta.guest && !isMemberAuthenticated.value) {
        return next({ name: 'member.login', query: { redirect: to.fullPath } });
    }

    if (to.meta.requiresAdmin && !to.meta.guest && !isAdminAuthenticated.value) {
        return next({ name: 'admin.login', query: { redirect: to.fullPath } });
    }

    next();
});

router.afterEach((to) => {
    applyRouteSeo(to, window.__APP_SEO__?.branding || {});
});

export default router;
