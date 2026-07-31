<script setup lang="ts">
import type { Coupon, CouponFormPayload } from '~/types/coupon'

definePageMeta({ role: 'admin' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const couponId = route.params.id as string

const coupon = ref<Coupon | null>(null)
const loading = ref(true)
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

async function loadCoupon() {
  loading.value = true
  const response = await api<{ data: Coupon }>(`/api/admin/coupons/${couponId}`)
  coupon.value = response.data
  form.value = {
    code: response.data.code,
    title: response.data.title,
    description: response.data.description ?? '',
    discount_type: response.data.discount_type,
    discount_value: response.data.discount_value,
    valid_until: response.data.valid_until ?? '',
    sort_order: response.data.sort_order,
  }
  loading.value = false
}

async function handleSubmit() {
  errors.value = {}
  submitting.value = true

  try {
    const response = await api<{ data: Coupon }>(`/api/admin/coupons/${couponId}`, { method: 'PATCH', body: form.value })
    coupon.value = response.data
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal menyimpan perubahan.')
  } finally {
    submitting.value = false
  }
}

async function deleteCoupon() {
  if (!coupon.value || !confirm(`Hapus kupon "${coupon.value.code}"?`)) return

  await api(`/api/admin/coupons/${couponId}`, { method: 'DELETE' })
  router.push('/admin/coupons')
}

onMounted(loadCoupon)
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <p v-if="loading">Memuat...</p>

    <template v-else-if="coupon">
      <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-gray-900">Edit Kupon</h1>
        <span class="badge" :class="coupon.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">
          {{ coupon.is_active ? 'Aktif' : 'Nonaktif' }}
        </span>
      </div>

      <div class="mt-4">
        <button class="btn-danger-outline" @click="deleteCoupon">
          Hapus Kupon
        </button>
      </div>

      <div class="mt-6">
        <CouponForm
          v-model="form"
          :submitting="submitting"
          :errors="errors"
          submit-label="Simpan Perubahan"
          @submit="handleSubmit"
        />
      </div>
    </template>
  </div>
</template>
