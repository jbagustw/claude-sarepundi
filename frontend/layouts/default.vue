<script setup lang="ts">
const authStore = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-cream">
    <header class="border-b border-black/5 bg-white">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <NuxtLink to="/" class="flex items-center gap-2 font-display text-xl font-bold">
          <svg class="h-7 w-7 text-brand-navy" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 2 3 8v13a1 1 0 001 1h5v-7h6v7h5a1 1 0 001-1V8z" fill="currentColor" />
            <path d="M9 4.5 12 2l3 2.5" stroke="#F1CE33" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span class="text-brand-navy">sare<span class="text-brand-gold">pundi</span></span>
        </NuxtLink>

        <nav class="hidden items-center gap-6 sm:flex">
          <SearchMenu />
          <NuxtLink to="/berita" class="text-sm font-medium text-gray-600 hover:text-brand-brown">
            Berita dan Artikel
          </NuxtLink>
          <NuxtLink v-if="!authStore.isAuthenticated" to="/jadi-mitra" class="text-sm font-medium text-gray-600 hover:text-brand-brown">
            Daftar Sebagai Mitra
          </NuxtLink>
        </nav>

        <div class="flex-1" />

        <div v-if="authStore.isAuthenticated" class="flex items-center gap-3 text-sm">
          <NotificationBell />
          <NuxtLink
            :to="ROLE_HOME[authStore.role as keyof typeof ROLE_HOME]"
            class="hidden text-sm font-medium text-gray-600 hover:text-brand-brown sm:block"
          >
            Dashboard
          </NuxtLink>
          <span class="hidden items-center gap-1.5 text-gray-700 sm:flex">
            {{ authStore.user?.name }}
            <span class="badge bg-brand-brown/10 uppercase tracking-wide text-brand-brown">
              {{ authStore.role }}
            </span>
          </span>
          <button class="btn-outline !px-4 !py-1.5" @click="handleLogout">
            Keluar
          </button>
        </div>

        <div v-else class="flex items-center gap-2">
          <NuxtLink to="/login" class="btn-primary !px-5 !py-2">Masuk</NuxtLink>
          <NuxtLink to="/register" class="btn-secondary !px-5 !py-2">Daftar</NuxtLink>
        </div>
      </div>
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
      <slot />
    </main>

    <footer class="mt-auto bg-brand-footer text-gray-200">
      <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <h3 class="font-display text-sm font-semibold text-white">Jelajahi</h3>
          <ul class="mt-3 space-y-2 text-sm text-gray-300">
            <li><NuxtLink to="/" class="hover:text-white">Beranda</NuxtLink></li>
            <li><NuxtLink to="/villas" class="hover:text-white">Cari Villa</NuxtLink></li>
            <li><NuxtLink to="/homestays" class="hover:text-white">Cari Homestay</NuxtLink></li>
            <li><NuxtLink to="/gathering-venues" class="hover:text-white">Cari Lokasi Gathering</NuxtLink></li>
            <li><NuxtLink to="/berita" class="hover:text-white">Berita dan Artikel</NuxtLink></li>
            <li><NuxtLink to="/jadi-mitra" class="hover:text-white">Daftar Sebagai Mitra</NuxtLink></li>
          </ul>
        </div>
        <div>
          <h3 class="font-display text-sm font-semibold text-white">Bantuan</h3>
          <ul class="mt-3 space-y-2 text-sm text-gray-300">
            <li>Kebijakan Privasi</li>
            <li>Syarat &amp; Ketentuan</li>
          </ul>
        </div>
        <div>
          <h3 class="font-display text-sm font-semibold text-white">Hubungi Kami</h3>
          <ul class="mt-3 space-y-2 text-sm text-gray-300">
            <li>Surabaya, Indonesia</li>
            <li>halo@sarepundi.com</li>
          </ul>
        </div>
        <div>
          <h3 class="font-display text-sm font-semibold text-white">Ikuti Kami</h3>
          <div class="mt-3 flex gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-500 text-xs">IG</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-500 text-xs">FB</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-full border border-gray-500 text-xs">TT</span>
          </div>
        </div>
      </div>
      <div class="border-t border-white/10 py-4 text-center text-xs text-gray-400">
        Copyright (c) {{ new Date().getFullYear() }} Sarepundi. All rights reserved.
      </div>
    </footer>
  </div>
</template>
