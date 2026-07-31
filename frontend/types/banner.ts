export interface Banner {
  id: number
  title: string
  image: string | null
  link_url: string | null
  is_active: boolean
  sort_order: number
  created_at: string
}

export interface BannerFormPayload {
  title: string
  link_url: string
  sort_order: number
}
