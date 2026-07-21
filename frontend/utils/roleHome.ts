import type { UserRole } from '~/types/auth'

export const ROLE_HOME: Record<UserRole, string> = {
  user: '/user/dashboard',
  mitra: '/mitra/dashboard',
  admin: '/admin/dashboard',
}
