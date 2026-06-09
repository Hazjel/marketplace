import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), vueDevTools(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  server: {
    port: 5173,
    open: true,
    allowedHosts: ['192.168.1.29', '192.168.1.10.nip.io', 'localhost'],
    watch: {
      // Diperlukan di Docker/WSL2 — Windows fs events tidak propagate ke container
      usePolling: true,
      interval:   300,
    },
    proxy: {
      '/api': {
        target: 'http://localhost:8000', // Laravel backend
        changeOrigin: true
      },
      '/tariff/api': {
        target: 'https://api-sandbox.collaborator.komerce.id',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/tariff\/api/, '/tariff/api')
      }
    }
  }
})
