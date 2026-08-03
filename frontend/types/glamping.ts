import type { Facility, VillaImage } from '~/types/villa'

export type GlampingStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'inactive'

export interface Glamping {
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
  status: GlampingStatus
  rejection_reason: string | null
  reviewed_at: string | null
  reviews_avg_rating: number | null
  reviews_count: number
  mitra: {
    id: number
    business_name: string
  }
  images: VillaImage[]
  facilities: Facility[]
  created_at: string
}

export interface GlampingFormPayload {
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
