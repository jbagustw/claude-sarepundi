<script setup lang="ts">
import type { MitraProfileDetail } from '~/types/payout'

definePageMeta({ role: 'mitra' })

const api = useApi()
const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const savedMessage = ref('')

const form = reactive({
  business_name: '',
  business_address: '',
  bank_name: '',
  bank_account: '',
})

async function loadProfile() {
  loading.value = true
  const response = await api<{ data: MitraProfileDetail }>('/api/mitra/profile')
  form.business_name = response.data.business_name
  form.business_address = response.data.business_address ?? ''
  form.bank_name = response.data.bank_name ?? ''
  form.bank_account = response.data.bank_account ?? ''
  loading.value = false
}

async function saveProfile() {
  errors.value = {}
  savedMessage.value = ''
  saving.value = true

  try {
    await api('/api/mitra/profile', { method: 'PATCH', body: form })
    savedMessage.value = 'Profil berhasil disimpan.'
  } catch (error: any) {
    if (error?.data?.errors) errors.value = error.data.errors
    else alert('Gagal menyimpan profil.')
  } finally {
    saving.value = false
  }
}

onMounted(loadProfile)
</script>

<template>
  <div class="mx-auto max-w-lg">
    <h1 class="font-display text-2xl font-bold text-gray-900">Profil Bisnis</h1>
    <p class="mt-1 text-sm text-gray-600">
      Data rekening dibutuhkan supaya payout otomatis bisa dicairkan ke akunmu.
    </p>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>

    <form v-else class="mt-6 space-y-4" @submit.prevent="saveProfile">
      <div>
        <label class="block text-sm font-medium text-gray-700" for="business_name">Nama Usaha</label>
        <input
          id="business_name"
          v-model="form.business_name"
          type="text"
          required
          class="field-input mt-1"
        >
        <p v-if="errors.business_name" class="mt-1 text-sm text-red-600">{{ errors.business_name[0] }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700" for="business_address">Alamat Usaha</label>
        <textarea
          id="business_address"
          v-model="form.business_address"
          rows="2"
          class="field-input mt-1"
        />
      </div>

      <div class="border-t border-gray-200 pt-4">
        <h2 class="text-sm font-semibold text-gray-900">Data Rekening (untuk Payout)</h2>

        <div class="mt-3">
          <label class="block text-sm font-medium text-gray-700" for="bank_name">Nama Bank</label>
          <input
            id="bank_name"
            v-model="form.bank_name"
            type="text"
            placeholder="mis. BCA, MANDIRI, BNI"
            class="field-input mt-1"
          >
          <p v-if="errors.bank_name" class="mt-1 text-sm text-red-600">{{ errors.bank_name[0] }}</p>
        </div>

        <div class="mt-3">
          <label class="block text-sm font-medium text-gray-700" for="bank_account">Nomor Rekening</label>
          <input
            id="bank_account"
            v-model="form.bank_account"
            type="text"
            class="field-input mt-1"
          >
          <p v-if="errors.bank_account" class="mt-1 text-sm text-red-600">{{ errors.bank_account[0] }}</p>
        </div>
      </div>

      <p v-if="savedMessage" class="text-sm text-green-700">{{ savedMessage }}</p>

      <button
        type="submit"
        :disabled="saving"
        class="btn-primary"
      >
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </form>
  </div>
</template>
