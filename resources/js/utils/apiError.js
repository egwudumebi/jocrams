export function extractApiError(error, fallback = 'Something went wrong. Please try again.') {
    if (error?.userMessage) {
        return error.userMessage;
    }

    const errors = error?.response?.data?.errors;

    if (errors && typeof errors === 'object') {
        const messages = Object.values(errors).flat().filter(Boolean);

        if (messages.length) {
            return messages.join(' ');
        }
    }

    return error?.response?.data?.message || fallback;
}
