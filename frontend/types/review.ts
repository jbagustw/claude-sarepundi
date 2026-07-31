export interface Review {
  id: number
  rating: number
  comment: string | null
  mitra_reply: string | null
  mitra_replied_at: string | null
  user: {
    name: string
  }
  reviewable: {
    type: 'villa' | 'homestay'
    id: number
    name: string
  }
  created_at: string
}
