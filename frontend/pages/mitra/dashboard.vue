<script setup lang="ts">
definePageMeta({ role: 'mitra' })

const authStore = useAuthStore()
const mitraStatus = computed(() => authStore.user?.mitra_profile?.status ?? 'pending')

const statusLabel: Record<string, string> = {
  pending: 'Menunggu persetujuan admin',
  approved: 'Disetujui',
  rejected: 'Ditolak',
}

const statusColor: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900">
      Dashboard Mitra — {{ authStore.user?.mitra_profile?.business_name }}
    </h1>

    <span
      class="mt-3 inline-block rounded px-3 py-1 text-sm font-medium"
      :class="statusColor[mitraStatus]"
    >
      {{ statusLabel[mitraStatus] }}
    </span>

    <p v-if="mitraStatus === 'pending'" class="mt-4 text-gray-600">
      Akun mitra kamu masih menunggu persetujuan admin. Kamu tetap bisa menyiapkan
      draft villa, tapi belum bisa dikirim untuk direview sampai akun disetujui.
    </p>

    <NuxtLink
      to="/mitra/villas"
      class="mt-6 inline-block rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700"
    >
      Kelola Villa
    </NuxtLink>
  </div>
</template>
