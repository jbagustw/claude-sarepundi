<script setup lang="ts">
import type { UserRole } from '~/types/auth'

const authStore = useAuthStore()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  role: 'user' as Extract<UserRole, 'user' | 'mitra'>,
  business_name: '',
  business_address: '',
})

const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const submitting = ref(false)

async function handleSubmit() {
  errors.value = {}
  generalError.value = ''
  submitting.value = true

  try {
    await authStore.register({ ...form })
    router.push(ROLE_HOME[form.role])
  } catch (error: any) {
    if (error?.data?.errors) {
      errors.value = error.data.errors
    } else {
      generalError.value = 'Terjadi kesalahan. Silakan coba lagi.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-md">
    <h1 class="text-2xl font-bold text-gray-900">Daftar Akun</h1>
    <p class="mt-1 text-sm text-gray-600">Daftar sebagai pencari villa atau mitra pemilik properti.</p>

    <form class="mt-6 space-y-4" @submit.prevent="handleSubmit">
      <div>
        <span class="block text-sm font-medium text-gray-700">Daftar sebagai</span>
        <div class="mt-1 grid grid-cols-2 gap-2">
          <button
            type="button"
            class="rounded border px-3 py-2 text-sm"
            :class="form.role === 'user' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-700'"
            @click="form.role = 'user'"
          >
            Pencari Villa
          </button>
          <button
            type="button"
            class="rounded border px-3 py-2 text-sm"
            :class="form.role === 'mitra' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 text-gray-700'"
            @click="form.role = 'mitra'"
          >
            Mitra Pemilik Villa
          </button>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700" for="name">Nama Lengkap</label>
        <input
          id="name"
          v-model="form.name"
          type="text"
          required
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
        <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700" for="email">Email</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          required
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
        <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700" for="phone">No. HP</label>
        <input
          id="phone"
          v-model="form.phone"
          type="tel"
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
        <p v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone[0] }}</p>
      </div>

      <template v-if="form.role === 'mitra'">
        <div>
          <label class="block text-sm font-medium text-gray-700" for="business_name">Nama Usaha/Villa</label>
          <input
            id="business_name"
            v-model="form.business_name"
            type="text"
            required
            class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
          >
          <p v-if="errors.business_name" class="mt-1 text-sm text-red-600">{{ errors.business_name[0] }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700" for="business_address">Alamat Usaha</label>
          <textarea
            id="business_address"
            v-model="form.business_address"
            rows="2"
            class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
          />
          <p v-if="errors.business_address" class="mt-1 text-sm text-red-600">{{ errors.business_address[0] }}</p>
        </div>
      </template>

      <div>
        <label class="block text-sm font-medium text-gray-700" for="password">Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          required
          minlength="8"
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
        <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700" for="password_confirmation">Konfirmasi Password</label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          required
          minlength="8"
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
      </div>

      <p v-if="generalError" class="text-sm text-red-600">{{ generalError }}</p>

      <button
        type="submit"
        :disabled="submitting"
        class="w-full rounded bg-gray-900 px-4 py-2 text-white hover:bg-gray-700 disabled:opacity-50"
      >
        {{ submitting ? 'Memproses...' : 'Daftar' }}
      </button>
    </form>

    <p class="mt-4 text-sm text-gray-600">
      Sudah punya akun?
      <NuxtLink to="/login" class="font-medium text-gray-900 underline">Masuk</NuxtLink>
    </p>
  </div>
</template>
