import type { Facility, VillaImage } from '~/types/villa'

export type GatheringVenueStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'inactive'

export interface GatheringVenueSlot {
  id: number
  name: string
  start_time: string
  end_time: string
  price: number
  is_active: boolean
}

export interface GatheringVenue {
  id: number
  name: string
  slug: string
  description: string | null
  address: string | null
  city: string
  province: string | null
  latitude: number | null
  longitude: number | null
  capacity: number
  status: GatheringVenueStatus
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
  slots: GatheringVenueSlot[]
  starting_price: number | null
  created_at: string
}

export interface GatheringVenueFormPayload {
  name: string
  description: string
  address: string
  city: string
  province: string
  capacity: number
  facility_ids: number[]
}

export interface GatheringVenueSlotFormPayload {
  name: string
  start_time: string
  end_time: string
  price: number
}
