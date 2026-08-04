import type { BookingStatus } from '~/types/booking'

export interface MitraBooking {
  id: number
  booking_code: string
  check_in_date: string
  check_out_date: string
  guest_count: number
  subtotal: number
  discount_amount: number
  total_price: number
  mitra_payout_amount: number
  status: BookingStatus
  mitra_confirmation_deadline: string | null
  cancellation_reason: string | null
  bookable: {
    type: 'villa' | 'glamping' | 'homestay' | 'apartment' | 'gathering_venue' | 'transport'
    id: number
    name: string
  }
  slot: {
    name: string
    start_time: string
    end_time: string
  } | null
  transport_with_driver: boolean | null
  guest: {
    name: string
    email: string
    phone: string | null
  }
  created_at: string
}
