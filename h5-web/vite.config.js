import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// 移动端 H5（部署于 public/h5-web，hash 路由；与 PC 前台共用 /front API）
export default defineConfig({
  plugins: [vue()],
  base: '/h5-web/',
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: { port: 5174 },
  build: { outDir: 'dist', chunkSizeWarningLimit: 2000 },
});
