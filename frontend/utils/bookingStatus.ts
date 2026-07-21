import type { BookingStatus } from '~/types/booking'

export const BOOKING_STATUS_LABEL: Record<BookingStatus, string> = {
  pending_payment: 'Menunggu Pembayaran',
  menunggu_konfirmasi: 'Menunggu Konfirmasi Mitra',
  dikonfirmasi: 'Dikonfirmasi',
  dibatalkan_mitra: 'Dibatalkan Mitra',
  dibatalkan_user: 'Dibatalkan',
  checked_in: 'Check-in',
  selesai: 'Selesai',
}

export const BOOKING_STATUS_COLOR: Record<BookingStatus, string> = {
  pending_payment: 'bg-yellow-100 text-yellow-800',
  menunggu_konfirmasi: 'bg-blue-100 text-blue-800',
  dikonfirmasi: 'bg-green-100 text-green-800',
  dibatalkan_mitra: 'bg-red-100 text-red-800',
  dibatalkan_user: 'bg-gray-100 text-gray-600',
  checked_in: 'bg-purple-100 text-purple-800',
  selesai: 'bg-gray-200 text-gray-700',
}
