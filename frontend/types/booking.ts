import type { Review } from '~/types/review'

export type BookingStatus =
  | 'pending_payment'
  | 'menunggu_konfirmasi'
  | 'dikonfirmasi'
  | 'dibatalkan_mitra'
  | 'dibatalkan_user'
  | 'checked_in'
  | 'selesai'

export interface Booking {
  id: number
  booking_code: string
  check_in_date: string
  check_out_date: string
  guest_count: number
  total_price: number
  commission_amount: number
  mitra_payout_amount: number
  status: BookingStatus
  mitra_confirmation_deadline: string | null
  cancellation_reason: string | null
  cancelled_at: string | null
  refund_amount: number | null
  refund_percentage: number | null
  payment: {
    status: 'pending' | 'success' | 'failed' | 'refunded' | 'partial_refunded'
    invoice_url: string | null
    paid_at: string | null
  } | null
  bookable: {
    type: 'villa' | 'homestay'
    id: number
    name: string
    slug: string
    city: string
    primary_image: string | null
  }
  review: Review | null
  created_at: string
}

export interface AvailabilityResult {
  available: boolean
  reason: string | null
  nights: number
  total_price: number
  commission_amount: number
  mitra_payout_amount: number
}

export interface VillaAvailabilityOverride {
  id: number
  date: string
  is_available: boolean
  custom_price: number | null
  min_stay: number | null
}
