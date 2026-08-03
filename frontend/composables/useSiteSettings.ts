import type { SiteSettings } from '~/types/siteSettings'

export function useSiteSettings() {
  return useAsyncData('site-settings', () =>
    useApi()<{ data: SiteSettings }>('/api/site-settings').then(res => res.data)
  )
}
