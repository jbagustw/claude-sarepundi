export interface DashboardNavItem {
  label: string
  to: string
}

export const DASHBOARD_NAV: Record<'admin' | 'mitra' | 'user', DashboardNavItem[]> = {
  admin: [
    { label: 'Dashboard', to: '/admin/dashboard' },
    { label: 'Approval Mitra', to: '/admin/mitras' },
    { label: 'Moderasi Villa', to: '/admin/villas' },
    { label: 'Moderasi Glamping', to: '/admin/glampings' },
    { label: 'Moderasi Homestay', to: '/admin/homestays' },
    { label: 'Moderasi Lokasi Gathering', to: '/admin/gathering-venues' },
    { label: 'Moderasi Transport', to: '/admin/transports' },
    { label: 'Monitoring Transaksi', to: '/admin/transactions' },
    { label: 'Kelola User & Mitra', to: '/admin/users' },
    { label: 'Payout Mitra', to: '/admin/payouts' },
    { label: 'Kelola Artikel', to: '/admin/articles' },
    { label: 'Kelola Kupon', to: '/admin/coupons' },
    { label: 'Kelola Banner Iklan', to: '/admin/banners' },
    { label: 'Pengaturan Website', to: '/admin/settings' },
  ],
  mitra: [
    { label: 'Dashboard', to: '/mitra/dashboard' },
    { label: 'Profil Bisnis', to: '/mitra/profile' },
    { label: 'Villa', to: '/mitra/villas' },
    { label: 'Glamping', to: '/mitra/glampings' },
    { label: 'Homestay', to: '/mitra/homestays' },
    { label: 'Lokasi Gathering', to: '/mitra/gathering-venues' },
    { label: 'Transport', to: '/mitra/transports' },
    { label: 'Kelola Booking', to: '/mitra/bookings' },
    { label: 'Ulasan', to: '/mitra/reviews' },
    { label: 'Payout', to: '/mitra/payouts' },
  ],
  user: [
    { label: 'Dashboard', to: '/user/dashboard' },
    { label: 'Booking Saya', to: '/user/bookings' },
  ],
}
