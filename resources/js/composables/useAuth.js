import { ref, computed } from 'vue';
import { memberApi, adminApi, journalApi, setAuthToken } from '../api/client';

const memberUser = ref(null);
const adminUser = ref(null);
const memberPermissions = ref([]);
const adminPermissions = ref([]);
const memberToken = ref(localStorage.getItem('member_token') || '');
const adminToken = ref(localStorage.getItem('admin_token') || '');

export function useAuth() {
    const isMemberAuthenticated = computed(() => !!memberToken.value);
    const isAdminAuthenticated = computed(() => !!adminToken.value);

    async function loginMember(email, password) {
        const { data } = await memberApi('').post('/auth/login', { email, password });
        memberToken.value = data.token;
        memberUser.value = data.user;
        memberPermissions.value = data.permissions || [];
        localStorage.setItem('member_token', data.token);
        setAuthToken(data.token);
        return data;
    }

    async function registerMember(form) {
        const { data } = await memberApi('').post('/auth/register', form);
        memberToken.value = data.token;
        memberUser.value = data.user;
        memberPermissions.value = data.permissions || [];
        localStorage.setItem('member_token', data.token);
        setAuthToken(data.token);
        return data;
    }

    async function loginAdmin(email, password) {
        const { data } = await adminApi('').post('/auth/login', { email, password });
        adminToken.value = data.token;
        adminUser.value = data.user;
        adminPermissions.value = data.permissions || [];
        localStorage.setItem('admin_token', data.token);
        return data;
    }

    async function fetchMemberProfile() {
        if (!memberToken.value) return null;
        const { data } = await memberApi(memberToken.value).get('/auth/me');
        memberUser.value = data.user;
        memberPermissions.value = data.permissions || [];
        return data.user;
    }

    async function fetchAdminProfile() {
        if (!adminToken.value) return null;
        const { data } = await adminApi(adminToken.value).get('/auth/me');
        adminUser.value = data.user;
        adminPermissions.value = data.permissions || [];
        return data.user;
    }

    function logoutMember() {
        memberToken.value = '';
        memberUser.value = null;
        memberPermissions.value = [];
        localStorage.removeItem('member_token');
    }

    function logoutAdmin() {
        adminToken.value = '';
        adminUser.value = null;
        adminPermissions.value = [];
        localStorage.removeItem('admin_token');
    }

    function getMemberClient() {
        return memberApi(memberToken.value);
    }

    function getAdminClient() {
        return adminApi(adminToken.value);
    }

    function getJournalClient() {
        return journalApi(memberToken.value || adminToken.value);
    }

    function canJournalSubmit() {
        return !!memberUser.value?.member?.status && memberUser.value.member.status === 'active';
    }

    function canJournalReview() {
        return memberPermissions.value.includes('journal.review')
            || adminPermissions.value.includes('journal.review')
            || adminPermissions.value.includes('journal.assign');
    }

    function canJournalAssign() {
        return adminPermissions.value.includes('journal.assign');
    }

    function canJournalPublish() {
        return adminPermissions.value.includes('journal.publish')
            || adminPermissions.value.includes('journal.assign')
            || memberPermissions.value.includes('journal.publish');
    }

    function isMemberEmailVerified() {
        return !!memberUser.value?.email_verified_at;
    }

    function isAdminEmailVerified() {
        return !!adminUser.value?.email_verified_at;
    }

    async function resendMemberVerificationEmail() {
        const { data } = await getMemberClient().post('/auth/email/verification-notification');
        return data;
    }

    async function resendAdminVerificationEmail() {
        const { data } = await getAdminClient().post('/auth/email/verification-notification');
        return data;
    }

    return {
        memberUser,
        adminUser,
        memberPermissions,
        adminPermissions,
        memberToken,
        adminToken,
        isMemberAuthenticated,
        isAdminAuthenticated,
        loginMember,
        registerMember,
        loginAdmin,
        fetchMemberProfile,
        fetchAdminProfile,
        logoutMember,
        logoutAdmin,
        getMemberClient,
        getAdminClient,
        getJournalClient,
        canJournalSubmit,
        canJournalReview,
        canJournalAssign,
        canJournalPublish,
        isMemberEmailVerified,
        isAdminEmailVerified,
        resendMemberVerificationEmail,
        resendAdminVerificationEmail,
    };
}
