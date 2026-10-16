function filenameFromDisposition(disposition) {
    if (!disposition) {
        return null;
    }

    const match = disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
    if (!match) {
        return null;
    }

    return match[1].replace(/['"]/g, '');
}

export async function messageFromBlobError(error, fallback = 'Download failed.') {
    if (error?.userMessage) {
        return error.userMessage;
    }

    if (error?.response?.data instanceof Blob) {
        try {
            const payload = JSON.parse(await error.response.data.text());
            return payload.message || fallback;
        } catch {
            // Fall through.
        }
    }

    return error?.response?.data?.message || error?.message || fallback;
}

export async function downloadBlobResponse(response, fallbackFilename = 'download.csv') {
    const contentType = response.headers['content-type'] || '';

    if (contentType.includes('application/json')) {
        const text = await response.data.text();
        const payload = JSON.parse(text);
        throw new Error(payload.message || 'Download failed.');
    }

    const filename = filenameFromDisposition(response.headers['content-disposition']) || fallbackFilename;
    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

/**
 * Authenticated CSV/XLSX download that never navigates away from the SPA.
 * @param {import('axios').AxiosInstance} client
 * @param {string} path admin-relative path e.g. /exports/members?format=csv
 * @param {string} fallbackFilename
 */
export async function downloadAdminExport(client, path, fallbackFilename = 'export.csv') {
    const response = await client.get(path, { responseType: 'blob' });
    await downloadBlobResponse(response, fallbackFilename);
}
