<script setup lang="ts">
import type { CouponFormPayload } from '~/types/coupon'

definePageMeta({ role: 'admin' })

const api = useApi()
const router = useRouter()

const submitting = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref<CouponFormPayload>({
  code: '',
  title: '',
  description: '',
  discount_type: 'percentage',
  discount_value: 10,
  valid_until: '',
  sort_order: 0,
})

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    await api('/api/admin/coupons', { method: 'POST', body: form.value })
    router.push('/admin/coupons')
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal membuat kupon.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="font-display text-2xl font-bold text-gray-900">Buat Kupon</h1>
    <p class="mt-1 text-sm text-gray-600">
      Kupon baru langsung aktif dan akan tampil di halaman utama.
    </p>

    <div class="mt-6">
      <CouponForm
        v-model="form"
        :submitting="submitting"
        :errors="errors"
        submit-label="Simpan Kupon"
        @submit="handleSubmit"
      />
    </div>
  </div>
</template>
