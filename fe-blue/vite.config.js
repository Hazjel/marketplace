import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), vueDevTools()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    port: 5173,  
    open: true,
    proxy: {
      '/api': {
        target: 'http://localhost:8000', // Laravel backend
        changeOrigin: true,
      },
      '/tariff/api': {
        target: 'https://api-sandbox.collaborator.komerce.id', 
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/tariff\/api/, '/tariff/api'),
      },
    }
  }
})
