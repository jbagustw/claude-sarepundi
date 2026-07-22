// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: ['@nuxtjs/tailwindcss', '@pinia/nuxt'],

  tailwindcss: {
    cssPath: '~/assets/css/main.css',
  },

  app: {
    head: {
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700&display=swap' },
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
      frontendOrigin: process.env.NUXT_PUBLIC_FRONTEND_ORIGIN || 'http://localhost:3000',
    },
  },
})
