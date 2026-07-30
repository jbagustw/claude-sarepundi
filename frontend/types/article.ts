export type ArticleStatus = 'draft' | 'published'

export interface Article {
  id: number
  title: string
  slug: string
  category: string | null
  excerpt: string | null
  content: string
  cover_image: string | null
  status: ArticleStatus
  published_at: string | null
  author: {
    name: string
  }
  created_at: string
  updated_at: string
}

export interface ArticleFormPayload {
  title: string
  category: string
  excerpt: string
  content: string
}
