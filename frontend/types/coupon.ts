export type CouponDiscountType = 'percentage' | 'fixed'

export interface Coupon {
  id: number
  code: string
  title: string
  description: string | null
  discount_type: CouponDiscountType
  discount_value: number
  valid_until: string | null
  is_active: boolean
  sort_order: number
  created_at: string
}

export interface CouponFormPayload {
  code: string
  title: string
  description: string
  discount_type: CouponDiscountType
  discount_value: number
  valid_until: string
  sort_order: number
}
