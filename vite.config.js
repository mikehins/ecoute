import { defineConfig } from 'vite'

export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: 'resources/js/overlay.js',
      output: {
        entryFileNames: 'overlay.js',
      },
    },
  },
})

