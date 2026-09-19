import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import '../css/app.css';

function bootstrapAppSeo() {
    const el = document.getElementById('app-seo-data');

    if (! el?.textContent) {
        window.__APP_SEO__ = window.__APP_SEO__ || { branding: {}, defaults: {} };

        return;
    }

    try {
        window.__APP_SEO__ = JSON.parse(el.textContent);
    } catch {
        window.__APP_SEO__ = { branding: {}, defaults: {} };
    }
}

bootstrapAppSeo();

createApp(App).use(router).mount('#app');
