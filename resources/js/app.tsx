import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import PwaProvider from '@/components/pwa/PwaProvider';

const appName = import.meta.env.VITE_APP_NAME || 'CLBK TOKE';
const pages = import.meta.glob('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, pages),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <PwaProvider>
                <App {...props} />
            </PwaProvider>,
        );
    },
    progress: {
        color: '#164b37',
    },
});
