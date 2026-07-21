<script setup lang="ts">
const authStore = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <header class="border-b bg-white">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
        <div class="flex items-center gap-6">
          <NuxtLink to="/" class="font-semibold text-gray-900">
            Platform Booking Villa
          </NuxtLink>
          <NuxtLink to="/villas" class="text-sm text-gray-600 hover:text-gray-900">
            Cari Villa
          </NuxtLink>
        </div>

        <div v-if="authStore.isAuthenticated" class="flex items-center gap-4 text-sm">
          <span class="text-gray-600">
            {{ authStore.user?.name }}
            <span class="ml-1 rounded bg-gray-100 px-2 py-0.5 text-xs uppercase tracking-wide text-gray-500">
              {{ authStore.role }}
            </span>
          </span>
          <button
            class="rounded bg-gray-900 px-3 py-1.5 text-white hover:bg-gray-700"
            @click="handleLogout"
          >
            Keluar
          </button>
        </div>

        <div v-else class="flex items-center gap-3 text-sm">
          <NuxtLink to="/login" class="text-gray-600 hover:text-gray-900">Masuk</NuxtLink>
          <NuxtLink to="/register" class="rounded bg-gray-900 px-3 py-1.5 text-white hover:bg-gray-700">
            Daftar
          </NuxtLink>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
      <slot />
    </main>
  </div>
</template>
