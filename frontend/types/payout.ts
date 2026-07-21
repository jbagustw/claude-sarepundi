export interface Payout {
  id: number
  amount: number
  period_start: string
  period_end: string
  xendit_disbursement_id: string | null
  status: 'pending' | 'completed' | 'failed'
  failure_reason: string | null
  processed_at: string | null
  booking_count: number
  mitra?: {
    id: number
    business_name: string
  }
  created_at: string
}

export interface MitraProfileDetail {
  id: number
  business_name: string
  business_address: string | null
  legal_document_url: string | null
  bank_name: string | null
  bank_account: string | null
  status: string
  commission_rate: number | null
  effective_commission_rate: number
}
