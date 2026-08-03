<script setup lang="ts">
withDefaults(defineProps<{
  transparent?: boolean
  showCategoryNav?: boolean
}>(), {
  transparent: false,
  showCategoryNav: true,
})

const authStore = useAuthStore()
const router = useRouter()

const { data: siteSettings } = await useSiteSettings()

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <header
    class="fixed inset-x-0 top-0 z-30 w-full transition-colors"
    :class="transparent ? 'bg-transparent' : 'bg-brand-brown-dark shadow-md'"
  >
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
      <NuxtLink to="/" class="flex shrink-0 items-center gap-2 font-display text-xl font-bold">
        <img
          v-if="siteSettings?.logo_url"
          :src="siteSettings.logo_url"
          class="h-[43px] w-[135px] object-contain object-left"
          alt="sarepundi"
        >
        <template v-else>
          <svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 2 3 8v13a1 1 0 001 1h5v-7h6v7h5a1 1 0 001-1V8z" fill="currentColor" />
            <path d="M9 4.5 12 2l3 2.5" stroke="#F1CE33" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span class="text-white">sare<span class="text-brand-gold">pundi</span></span>
        </template>
      </NuxtLink>

      <div class="flex items-center gap-6">
        <nav class="hidden items-center gap-6 sm:flex">
          <NuxtLink to="/berita" class="text-sm font-medium text-white/80 hover:text-white">
            Berita dan Artikel
          </NuxtLink>
          <NuxtLink v-if="!authStore.isAuthenticated" to="/jadi-mitra" class="text-sm font-medium text-white/80 hover:text-white">
            Daftar Sebagai Mitra
          </NuxtLink>
        </nav>

        <div v-if="authStore.isAuthenticated" class="flex items-center gap-3 text-sm">
          <NotificationBell />
          <NuxtLink
            :to="ROLE_HOME[authStore.role as keyof typeof ROLE_HOME]"
            class="hidden text-sm font-medium text-white/80 hover:text-white sm:block"
          >
            Dashboard
          </NuxtLink>
          <span class="hidden items-center gap-1.5 text-white/90 sm:flex">
            {{ authStore.user?.name }}
            <span class="badge bg-white/10 uppercase tracking-wide text-white">
              {{ authStore.role }}
            </span>
          </span>
          <button class="btn-outline !border-white/30 !text-white hover:!bg-white/10 !px-4 !py-1.5" @click="handleLogout">
            Keluar
          </button>
        </div>

        <div v-else class="flex items-center gap-2">
          <NuxtLink to="/login" class="btn-secondary !px-5 !py-2">Masuk</NuxtLink>
          <NuxtLink to="/register" class="btn-accent !px-5 !py-2">Daftar</NuxtLink>
        </div>
      </div>
    </div>

    <div v-if="showCategoryNav" class="border-t border-white/10">
      <div class="mx-auto max-w-6xl px-4 py-2.5">
        <CategoryNav />
      </div>
    </div>
  </header>
</template>
