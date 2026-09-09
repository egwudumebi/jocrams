export function rateLimitMessage(error, fallback = 'Too many requests. Please wait a moment and try again.') {
    if (error?.response?.status !== 429) {
        return null;
    }

    const retryAfterHeader = error.response.headers?.['retry-after'];
    const retryAfter = Number(retryAfterHeader);
    const serverMessage = error.response.data?.message;

    if (serverMessage && typeof serverMessage === 'string') {
        return serverMessage;
    }

    if (Number.isFinite(retryAfter) && retryAfter > 0) {
        return `Too many attempts. Please try again in ${retryAfter} seconds.`;
    }

    return fallback;
}

export function isRateLimited(error) {
    return error?.response?.status === 429;
}
