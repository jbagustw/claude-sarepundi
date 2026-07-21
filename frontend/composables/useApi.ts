function readCookie(name: string): string | null {
  if (import.meta.server) return null

  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`))

  return match ? decodeURIComponent(match[1]) : null
}

/**
 * $fetch wrapper for talking to the Laravel API using Sanctum's SPA
 * (cookie + XSRF-TOKEN) authentication instead of bearer tokens.
 */
export function useApi() {
  const config = useRuntimeConfig()

  return $fetch.create({
    baseURL: config.public.apiBase,
    credentials: 'include',
    // Server-to-server SSR requests don't carry an Origin header the way a
    // browser request does, so Laravel Sanctum's EnsureFrontendRequestsAreStateful
    // can't recognize them as "from the frontend" and skips starting the
    // session — meaning the forwarded cookie below never gets decoded and
    // SSR silently renders as a guest. Setting Origin explicitly fixes that.
    headers: import.meta.server
      ? { ...useRequestHeaders(['cookie']), Origin: config.public.frontendOrigin }
      : undefined,
    onRequest({ options }) {
      const method = (options.method || 'GET').toString().toUpperCase()

      if (import.meta.client && method !== 'GET' && method !== 'HEAD') {
        const xsrfToken = readCookie('XSRF-TOKEN')

        if (xsrfToken) {
          options.headers = new Headers(options.headers)
          options.headers.set('X-XSRF-TOKEN', xsrfToken)
        }
      }
    },
  })
}
