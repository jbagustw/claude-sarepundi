export type VillaStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'inactive'

export interface Facility {
  id: number
  name: string
  icon: string | null
  category: string | null
}

export interface VillaImage {
  id: number
  url: string
  is_primary: boolean
  sort_order: number
}

export interface Villa {
  id: number
  name: string
  slug: string
  description: string | null
  address: string | null
  city: string
  province: string | null
  latitude: number | null
  longitude: number | null
  capacity_guest: number
  bedroom_count: number
  bathroom_count: number
  base_price: number
  status: VillaStatus
  rejection_reason: string | null
  reviewed_at: string | null
  mitra: {
    id: number
    business_name: string
  }
  images: VillaImage[]
  facilities: Facility[]
  reviews_avg_rating: number | null
  reviews_count: number
  created_at: string
}

export interface VillaFormPayload {
  name: string
  description: string
  address: string
  city: string
  province: string
  capacity_guest: number
  bedroom_count: number
  bathroom_count: number
  base_price: number
  facility_ids: number[]
}
