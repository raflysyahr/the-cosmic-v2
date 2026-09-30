import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    // loadEnv membaca file .env Laravel di root project (bukan
    // resources/js) — variabel VITE_* di situ jadi bisa dipakai di sini
    // (config Node.js), terpisah dari import.meta.env yang cuma untuk
    // kode client-side yang di-bundle.
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            ...laravel({
                input: 'resources/js/app.jsx',
                refresh: true,
            }),
            react(),
        ],
        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },
        build: {
            rollupOptions: {
                input: 'resources/js/app.jsx',
                output: {
                    manualChunks(id) {
                        if (id.includes('react-fluentui-emoji')) {
                            return 'fluentui-emoji'
                        }
                    },
                },
            },
        },
        server: {
            host: '0.0.0.0',
            port: 5173,
            // 'cors: true' membuat Vite dev server reflect origin PENGIRIM
            // request di header Access-Control-Allow-Origin (bukan hardcode
            // satu nilai tetap). Tanpa ini, browser menolak resource dari
            // :5173 saat halaman utama dibuka dari origin lain (:8000) —
            // errornya persis "Access-Control-Allow-Origin header has a
            // value ... that is not equal to the supplied origin".
            cors: true,
            // 'host' di atas cuma mengatur INTERFACE BIND server (0.0.0.0 =
            // semua interface, wajib supaya bisa diakses dari IP LAN). Tapi
            // laravel-vite-plugin memakai nilai 'host' itu APA ADANYA untuk
            // generate origin di tag <script src="http://HOST:5173/...">
            // yang di-inject ke HTML — makanya browser sempat mencoba fetch
            // dari "http://0.0.0.0:5173/..." (bukan alamat yang valid untuk
            // dituju, cuma valid sebagai alamat "dengarkan semua interface"
            // di sisi server) dan gagal CORS/connection refused.
            //
            // 'origin' menentukan origin untuk asset path (client.js,
            // app.jsx, Pages/*.tsx). 'hmr.host' menentukan origin KHUSUS
            // untuk WebSocket HMR + preamble React Refresh (@react-refresh)
            // — dua hal ini TERPISAH di Vite, origin saja tidak cukup
            // untuk membenahi error "Failed to resolve ... @react-refresh".
            // Set IP LAN kamu lewat .env:
            //   VITE_DEV_SERVER_ORIGIN=http://192.168.1.5:5173
            //   VITE_DEV_SERVER_HOST=192.168.1.5
            // Ganti sesuai IP kamu tiap kali berubah; kalau tidak di-set,
            // fallback ke localhost (aman untuk device yang sama dg server).
            origin: env.VITE_DEV_SERVER_ORIGIN || 'http://localhost:5173',
            hmr: {
                host: env.VITE_DEV_SERVER_HOST || 'localhost',
            },
            // Vite 5+ menolak Host header yang bukan localhost/IP dev secara
            // default (proteksi DNS rebinding). Karena kita akses lewat IP
            // LAN, itu otomatis diizinkan — tapi kalau nanti pakai domain lokal
            // (mis. thecosmic.test), tambahkan ke allowedHosts di sini.
            watch: {
                ignored: ['**/vendor/**', '**/node_modules/**']
            }
        }
    };
});
