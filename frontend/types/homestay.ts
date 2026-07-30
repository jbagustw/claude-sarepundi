import type { Facility, VillaImage } from '~/types/villa'

export type HomestayStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'inactive'

export interface Homestay {
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
  status: HomestayStatus
  rejection_reason: string | null
  reviewed_at: string | null
  mitra: {
    id: number
    business_name: string
  }
  images: VillaImage[]
  facilities: Facility[]
  created_at: string
}

export interface HomestayFormPayload {
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
