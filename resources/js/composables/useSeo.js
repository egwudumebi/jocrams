import { applySeo } from '../utils/seo';

export { applySeo } from '../utils/seo';
export {
    applyRouteSeo,
    applyDynamicArticleSeo,
    applyDynamicEventSeo,
    applyDynamicMemberSeo,
    applyDynamicJournalArticleSeo,
} from '../config/seoRoutes';

/**
 * Apply page-level SEO overrides (title, description, image, robots, jsonLd).
 *
 * @param {object} options
 */
export function useSeo(options) {
    applySeo({
        siteName: window.__APP_SEO__?.branding?.site_name || 'JOCRAMS',
        ...options,
    });
}
