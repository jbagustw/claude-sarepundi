export type UserRole = 'user' | 'mitra' | 'admin'

export interface MitraProfileSummary {
  business_name: string
  status: 'pending' | 'approved' | 'rejected'
}

export interface AuthUser {
  id: number
  name: string
  email: string
  phone: string | null
  avatar: string | null
  status: 'active' | 'suspended'
  role: UserRole
  mitra_profile: MitraProfileSummary | null
}

export interface RegisterPayload {
  name: string
  email: string
  phone?: string
  password: string
  password_confirmation: string
  role: 'user' | 'mitra'
  business_name?: string
  business_address?: string
}

export interface LoginPayload {
  email: string
  password: string
}
