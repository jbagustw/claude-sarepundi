import type { UserRole } from '~/types/auth'

const PUBLIC_ROUTES = ['/', '/login', '/register', '/daftar-mitra', '/jadi-mitra']
const PUBLIC_PREFIXES = ['/villas', '/glampings', '/homestays', '/gathering-venues', '/transports', '/berita']

export default defineNuxtRouteMiddleware((to) => {
  const authStore = useAuthStore()
  const isPublicRoute = PUBLIC_ROUTES.includes(to.path)
    || PUBLIC_PREFIXES.some((prefix) => to.path.startsWith(prefix))

  if (!authStore.isAuthenticated) {
    if (!isPublicRoute) {
      return navigateTo(`/login?redirect=${encodeURIComponent(to.fullPath)}`)
    }
    return
  }

  const homePath = ROLE_HOME[authStore.role as UserRole]

  if (to.path === '/login' || to.path === '/register' || to.path === '/daftar-mitra') {
    return navigateTo(homePath)
  }

  const requiredRole = to.meta.role as UserRole | undefined

  if (requiredRole && authStore.role !== requiredRole) {
    return navigateTo(homePath)
  }
})
