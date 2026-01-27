import './styles/app.css';
import 'flowbite';
import { createApp } from 'vue';
import RedditList from './components/RedditList.vue';

document.addEventListener('DOMContentLoaded', () => {
    const appEl = document.getElementById('app');
    if (appEl) {
        const initialPosts = JSON.parse(appEl.getAttribute('data-posts') || '[]');
        const total = parseInt(appEl.getAttribute('data-total') || '0', 10);
        const totalPages = parseInt(appEl.getAttribute('data-total-pages') || '1', 10);
        const page = parseInt(appEl.getAttribute('data-page') || '1', 10);
        const assetPath = appEl.getAttribute('data-asset-path') || '/';

        createApp(RedditList, {
            initialPosts: initialPosts,
            initialTotal: total,
            initialTotalPages: totalPages,
            initialPage: page,
            assetPath: assetPath
        }).mount('#app');
    }
});
