import { defineStore } from 'pinia'
import type { AuthUser, LoginPayload, RegisterPayload } from '~/types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const role = computed(() => user.value?.role ?? null)

  async function ensureCsrfCookie() {
    const api = useApi()
    await api('/sanctum/csrf-cookie')
  }

  async function fetchUser() {
    const api = useApi()

    try {
      const response = await api<{ data: AuthUser }>('/api/me')
      user.value = response.data
    } catch {
      user.value = null
    } finally {
      initialized.value = true
    }
  }

  async function login(payload: LoginPayload) {
    const api = useApi()
    await ensureCsrfCookie()

    const response = await api<{ data: AuthUser }>('/api/login', {
      method: 'POST',
      body: payload,
    })

    user.value = response.data
  }

  async function register(payload: RegisterPayload) {
    const api = useApi()
    await ensureCsrfCookie()

    const response = await api<{ data: AuthUser }>('/api/register', {
      method: 'POST',
      body: payload,
    })

    user.value = response.data
  }

  async function logout() {
    const api = useApi()

    try {
      await api('/api/logout', { method: 'POST' })
    } finally {
      user.value = null
    }
  }

  return {
    user,
    initialized,
    isAuthenticated,
    role,
    fetchUser,
    login,
    register,
    logout,
  }
})
