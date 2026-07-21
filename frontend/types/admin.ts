import type { BookingStatus } from '~/types/booking'

export interface AdminStats {
  users: { total: number; total_mitra: number; suspended: number }
  mitras: { pending_approval: number; approved: number }
  villas: { pending_review: number; published: number; total: number }
  bookings: {
    total: number
    awaiting_mitra_confirmation: number
    confirmed: number
    completed: number
    cancelled: number
  }
  commission_earned: number
}

export interface AdminBooking {
  id: number
  booking_code: string
  check_in_date: string
  check_out_date: string
  total_price: number
  commission_amount: number
  mitra_payout_amount: number
  status: BookingStatus
  cancellation_reason: string | null
  refund_amount: number | null
  villa: { id: number; name: string }
  mitra: { business_name: string }
  user: { name: string; email: string }
  payment_status: string | null
  created_at: string
}

export interface AdminUser {
  id: number
  name: string
  email: string
  phone: string | null
  status: 'active' | 'suspended'
  role: 'user' | 'mitra' | 'admin'
  mitra_profile: {
    id: number
    business_name: string
    status: string
    commission_rate: number | null
    effective_commission_rate: number
  } | null
  created_at: string
}
