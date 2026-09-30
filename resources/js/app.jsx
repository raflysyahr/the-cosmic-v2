import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { PopupProvider } from './contexts/PopupContext';
import { AuthProvider } from './contexts/AuthContext';
import { CultivationToastProvider } from './contexts/CultivationToastContext';
import { ModalProvider } from './contexts/ModalContext';
import eruda from 'eruda';

if (import.meta.env.DEV) {
  eruda.init()
}

const appName = import.meta.env.VITE_APP_NAME || 'The Cosmic';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <AuthProvider>
                <PopupProvider>
                    <CultivationToastProvider>
                        <ModalProvider>
                            <App {...props} />
                        </ModalProvider>
                    </CultivationToastProvider>
                </PopupProvider>
            </AuthProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});
