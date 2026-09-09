import axios from 'axios';
import { rateLimitMessage } from '../utils/rateLimit';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
});

function attachRateLimitInterceptor(instance) {
    instance.interceptors.response.use(
        (response) => response,
        (error) => {
            const message = rateLimitMessage(error);

            if (message) {
                error.rateLimited = true;
                error.userMessage = message;
            }

            return Promise.reject(error);
        },
    );
}

attachRateLimitInterceptor(api);
attachRateLimitInterceptor(axios);

export function setAuthToken(token) {
    if (token) {
        api.defaults.headers.common.Authorization = `Bearer ${token}`;
    } else {
        delete api.defaults.headers.common.Authorization;
    }
}

export function memberApi(token) {
    const instance = axios.create({
        baseURL: '/api/v1/member',
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
        },
    });
    attachRateLimitInterceptor(instance);

    return instance;
}

export function adminApi(token) {
    const instance = axios.create({
        baseURL: '/api/v1/admin',
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
        },
    });
    attachRateLimitInterceptor(instance);

    return instance;
}

export function publicApi(token) {
    const headers = { Accept: 'application/json' };
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const instance = axios.create({
        baseURL: '/api/v1/public',
        headers,
    });
    attachRateLimitInterceptor(instance);

    return instance;
}

export function journalApi(token) {
    const headers = { Accept: 'application/json' };
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const instance = axios.create({
        baseURL: '/api/v1/journal',
        headers,
    });
    attachRateLimitInterceptor(instance);

    return instance;
}

export default api;
