export function formatJournalDate(value) {
    if (! value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export function journalDocumentUrl(item) {
    return item.document_url || `/api/v1/journal/submissions/${item.slug || item.id}/document`;
}

export async function downloadJournalDocument(url, token = null, variant = 'manuscript') {
    const headers = { Accept: 'application/octet-stream' };
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const downloadUrl = variant === 'production'
        ? `${url}${url.includes('?') ? '&' : '?'}variant=production`
        : url;

    const response = await fetch(downloadUrl, { headers });
    if (! response.ok) {
        throw new Error('Unable to download document.');
    }

    const blob = await response.blob();
    const disposition = response.headers.get('content-disposition') || '';
    const match = disposition.match(/filename="([^"]+)"/i);
    const filename = match?.[1] || 'manuscript.docx';
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename.includes('.') ? filename : `${filename}.docx`;
    link.click();
    URL.revokeObjectURL(link.href);
}
