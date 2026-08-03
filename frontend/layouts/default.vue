<script setup lang="ts">
const authStore = useAuthStore()
const route = useRoute()

// The homepage hero has its own full-bleed background image, so the header
// floats transparently on top of it there instead of showing a solid bar —
// until the user scrolls past the hero, at which point it turns solid like
// on every other page. The header is always `fixed` so it stays visible
// while scrolling on every page (not just home).
const isHome = computed(() => route.path === '/')

const scrolled = ref(false)
function onScroll() {
  scrolled.value = window.scrollY > 80
}
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }))
onUnmounted(() => window.removeEventListener('scroll', onScroll))

// Any page under /admin, /mitra, or /user gets the sidebar shell (menu
// always on the left, content on the right) instead of the public
// marketing layout — no need to opt in per-page.
const isDashboard = computed(() =>
  ['/admin', '/mitra', '/user'].some(prefix => route.path.startsWith(prefix))
)

const navItems = computed(() => DASHBOARD_NAV[authStore.role as keyof typeof DASHBOARD_NAV] ?? [])

function isActiveNav(to: string) {
  return route.path === to || route.path.startsWith(`${to}/`)
}
</script>

<template>
  <div class="relative flex min-h-screen flex-col overflow-x-hidden bg-cream">
    <AppHeader :transparent="isHome && !scrolled" :show-category-nav="!isDashboard" />

    <template v-if="isDashboard">
      <div class="mx-auto w-full max-w-7xl flex-1 px-4 pb-8 pt-24">
        <nav class="mb-4 flex gap-2 overflow-x-auto lg:hidden">
          <NuxtLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="shrink-0"
            :class="isActiveNav(item.to) ? 'chip-active' : 'chip'"
          >
            {{ item.label }}
          </NuxtLink>
        </nav>

        <div class="flex gap-6">
          <aside class="hidden w-60 shrink-0 lg:block">
            <nav class="card sticky top-24 space-y-1 p-2">
              <NuxtLink
                v-for="item in navItems"
                :key="item.to"
                :to="item.to"
                class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                :class="isActiveNav(item.to) ? 'bg-brand-brown text-white' : 'text-gray-700 hover:bg-gray-100'"
              >
                {{ item.label }}
              </NuxtLink>
            </nav>
          </aside>

          <main class="min-w-0 flex-1">
            <slot />
          </main>
        </div>
      </div>
    </template>

    <main v-else class="mx-auto w-full max-w-6xl flex-1 px-4 pb-8" :class="isHome ? 'pt-8' : 'pt-32'">
      <slot />
    </main>

    <AppFooter />
  </div>
</template>
