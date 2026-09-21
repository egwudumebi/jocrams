const JOURNAL_TITLE = 'Journal of Communication Research and Media Studies';

/**
 * Escape HTML, then wrap the journal full title in <em> for display.
 * @param {string|null|undefined} text
 * @returns {string}
 */
export function italicizeJournalTitleHtml(text) {
    if (! text) {
        return '';
    }

    const escaped = String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    return escaped.replaceAll(
        JOURNAL_TITLE,
        `<em>${JOURNAL_TITLE}</em>`,
    );
}

export { JOURNAL_TITLE };
