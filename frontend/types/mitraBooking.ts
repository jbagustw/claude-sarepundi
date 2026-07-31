import type { BookingStatus } from '~/types/booking'

export interface MitraBooking {
  id: number
  booking_code: string
  check_in_date: string
  check_out_date: string
  guest_count: number
  total_price: number
  mitra_payout_amount: number
  status: BookingStatus
  mitra_confirmation_deadline: string | null
  cancellation_reason: string | null
  bookable: {
    type: 'villa' | 'homestay' | 'gathering_venue'
    id: number
    name: string
  }
  slot: {
    name: string
    start_time: string
    end_time: string
  } | null
  guest: {
    name: string
    email: string
    phone: string | null
  }
  created_at: string
}
