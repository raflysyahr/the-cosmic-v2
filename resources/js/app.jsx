import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { PopupProvider } from './contexts/PopupContext';
import { AuthProvider } from './contexts/AuthContext';
import { CultivationToastProvider } from './contexts/CultivationToastContext';
import { ModalProvider } from './contexts/ModalContext';
import { registerServiceWorker, initInstallPrompt } from './lib/pwa';
import eruda from 'eruda';

if (import.meta.env.DEV) {
  eruda.init()
}

// Facebook menambahkan fragmen "#_=_" ke URL callback setelah login, dan browser membawanya
// lewat redirect server -> user mendarat di "/#_=_". Bersihkan sebelum React mount.
if (window.location.hash === '#_=_') {
  window.history.replaceState(window.history.state, '', window.location.pathname + window.location.search);
}

// PWA: tangkap 'beforeinstallprompt' sebelum React mount, lalu daftarkan service worker.
initInstallPrompt();
registerServiceWorker();

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
