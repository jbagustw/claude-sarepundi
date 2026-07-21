import type { UserRole } from '~/types/auth'

const PUBLIC_ROUTES = ['/', '/login', '/register']
const PUBLIC_PREFIXES = ['/villas']

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

  if (to.path === '/login' || to.path === '/register') {
    return navigateTo(homePath)
  }

  const requiredRole = to.meta.role as UserRole | undefined

  if (requiredRole && authStore.role !== requiredRole) {
    return navigateTo(homePath)
  }
})
