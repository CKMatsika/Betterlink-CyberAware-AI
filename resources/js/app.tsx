import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import Foundation from './pages/Foundation';

createInertiaApp({
    title: (title) => `${title} · Betterlink CyberAware AI`,
    resolve: (name) => {
        if (name !== 'Foundation') throw new Error(`Unknown page: ${name}`);
        return Foundation;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#14b8a6' },
});
