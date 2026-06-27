import { defineConfig } from 'vite'

export default defineConfig({
  root: '.',
  build: {
    outDir: 'public/vendor/ecoute',
    emptyOutDir: false,
    rollupOptions: {
      input: 'assets/src/overlay.js'
    }
  }
})

