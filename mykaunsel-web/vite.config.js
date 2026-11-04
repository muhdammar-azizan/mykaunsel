import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // schedule-calendar.js is only loaded by the counselor's Schedule page (it carries the calendar library).
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/schedule-calendar.js'],
            refresh: true,
        }),
    ],
});
