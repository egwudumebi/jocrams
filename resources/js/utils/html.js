/** Strip HTML tags for plain-text previews (e.g. event cards). */
export function stripHtml(value) {
    if (!value) {
        return '';
    }

    return String(value)
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** Decode entity-escaped HTML (e.g. &lt;p&gt;) before v-html rendering. */
export function renderRichTextHtml(value) {
    if (!value) {
        return '';
    }

    const html = String(value);

    if (!/&(?:lt|gt|amp|quot|#)/i.test(html)) {
        return html;
    }

    const el = document.createElement('div');
    el.innerHTML = html;

    return el.innerHTML;
}

export const RICH_TEXT_PROSE_CLASSES =
    'prose prose-lg max-w-none text-slate-700 [&_a]:text-brand-600 [&_a]:underline [&_b]:font-semibold [&_h2]:mb-3 [&_h2]:mt-6 [&_h2]:font-semibold [&_h2]:text-slate-900 [&_h3]:mb-2 [&_h3]:mt-5 [&_h3]:font-semibold [&_h3]:text-slate-900 [&_h4]:mb-2 [&_h4]:font-semibold [&_i]:italic [&_li]:my-1 [&_ol]:mb-4 [&_ol]:ml-5 [&_ol]:list-decimal [&_p]:mb-3 [&_strong]:font-semibold [&_u]:underline [&_ul]:mb-4 [&_ul]:ml-5 [&_ul]:list-disc';
