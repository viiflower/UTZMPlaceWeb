import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'

export default defineConfig({
  root: __dirname,
  base: '/app/',
  plugins: [react()],
  server: {
    proxy: {
      '/backend': {
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
      },
      '/public': {
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  build: {
    outDir: path.resolve(__dirname, '../app'),
    emptyOutDir: true,
  },
})
