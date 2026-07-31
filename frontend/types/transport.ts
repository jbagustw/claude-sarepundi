import type { VillaImage } from '~/types/villa'

export type TransportStatus = 'draft' | 'pending_review' | 'published' | 'rejected' | 'inactive'

export interface Transport {
  id: number
  name: string
  slug: string
  vehicle_type: string
  description: string | null
  capacity: number
  city: string
  province: string | null
  price_per_day_self_drive: number | null
  price_per_day_with_driver: number | null
  status: TransportStatus
  rejection_reason: string | null
  reviewed_at: string | null
  mitra: {
    id: number
    business_name: string
  }
  images: VillaImage[]
  created_at: string
}

export interface TransportFormPayload {
  name: string
  vehicle_type: string
  description: string
  capacity: number
  city: string
  province: string
  price_per_day_self_drive: number | null
  price_per_day_with_driver: number | null
}
