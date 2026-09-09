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
    link.click();
    URL.revokeObjectURL(url);
}
