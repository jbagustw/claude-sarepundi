<script setup lang="ts">
import type { Coupon } from '~/types/coupon'

definePageMeta({ role: 'admin' })

const api = useApi()
const coupons = ref<Coupon[]>([])
const loading = ref(true)
const actingOn = ref<number | null>(null)

async function loadCoupons() {
  loading.value = true
  const response = await api<{ data: Coupon[] }>('/api/admin/coupons')
  coupons.value = response.data
  loading.value = false
}

async function toggleActive(coupon: Coupon) {
  actingOn.value = coupon.id
  try {
    await api(`/api/admin/coupons/${coupon.id}`, { method: 'PATCH', body: { is_active: !coupon.is_active } })
    await loadCoupons()
  } catch {
    alert('Gagal mengubah status kupon.')
  } finally {
    actingOn.value = null
  }
}

async function deleteCoupon(coupon: Coupon) {
  if (!confirm(`Hapus kupon "${coupon.code}"?`)) return

  actingOn.value = coupon.id
  try {
    await api(`/api/admin/coupons/${coupon.id}`, { method: 'DELETE' })
    await loadCoupons()
  } catch {
    alert('Gagal menghapus kupon.')
  } finally {
    actingOn.value = null
  }
}

onMounted(loadCoupons)
</script>

<template>
  <div>
    <div class="flex items-center justify-between">
      <h1 class="font-display text-2xl font-bold text-gray-900">Kelola Kupon</h1>
      <NuxtLink to="/admin/coupons/create" class="btn-primary">+ Buat Kupon</NuxtLink>
    </div>
    <p class="mt-1 text-sm text-gray-600">Kupon yang aktif akan tampil di halaman utama untuk disalin pengguna.</p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>
    <p v-else-if="coupons.length === 0" class="mt-6 text-gray-600">Belum ada kupon.</p>

    <div v-else class="mt-6 space-y-3">
      <div v-for="coupon in coupons" :key="coupon.id" class="card flex items-start justify-between p-4">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="font-display font-semibold text-gray-900">{{ coupon.title }}</h2>
            <span class="badge" :class="coupon.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">
              {{ coupon.is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </div>
          <p class="text-sm text-gray-600">
            Kode <span class="font-mono font-semibold">{{ coupon.code }}</span> &middot;
            {{ coupon.discount_type === 'percentage' ? `${coupon.discount_value}%` : formatRupiah(coupon.discount_value) }}
            <span v-if="coupon.valid_until"> &middot; berlaku sampai {{ coupon.valid_until }}</span>
          </p>
        </div>

        <div class="flex shrink-0 gap-2 text-sm">
          <NuxtLink :to="`/admin/coupons/${coupon.id}/edit`" class="btn-outline !px-3 !py-1.5">Edit</NuxtLink>
          <button
            class="rounded-full px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
            :class="coupon.is_active ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-700 hover:bg-green-800'"
            :disabled="actingOn === coupon.id"
            @click="toggleActive(coupon)"
          >
            {{ coupon.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
          </button>
          <button
            class="rounded-full bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            :disabled="actingOn === coupon.id"
            @click="deleteCoupon(coupon)"
          >
            Hapus
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
