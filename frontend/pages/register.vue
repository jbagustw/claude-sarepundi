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
  <div class="mx-auto flex max-w-md items-center justify-center px-2 py-6 sm:py-10">
    <div class="relative w-full">
      <div class="absolute -left-3 -top-3 h-[97%] w-[97%] rounded-3xl bg-brand-brown-dark sm:-left-4 sm:-top-4" />
      <div class="absolute -bottom-3 -right-3 h-[97%] w-[97%] rounded-3xl bg-brand-sage/70 sm:-bottom-4 sm:-right-4" />

      <div class="card relative z-10 rounded-3xl p-6 sm:p-8">
        <h1 class="text-center font-display text-2xl font-bold text-gray-900 sm:text-3xl">Pendaftaran Akun</h1>
        <p class="mt-2 text-center text-sm text-gray-500">
          Mulai perjalananmu hari ini. Daftar sekarang! Temukan dan sewa villa impianmu
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="handleSubmit">
          <div>
            <span class="field-label">Daftar sebagai</span>
            <div class="mt-1 grid grid-cols-2 gap-2">
              <button
                type="button"
                class="rounded-full border px-3 py-2 text-sm font-medium transition"
                :class="form.role === 'user' ? 'border-brand-brown bg-brand-brown text-white' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                @click="form.role = 'user'"
              >
                Pencari Villa
              </button>
              <button
                type="button"
                class="rounded-full border px-3 py-2 text-sm font-medium transition"
                :class="form.role === 'mitra' ? 'border-brand-brown bg-brand-brown text-white' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                @click="form.role = 'mitra'"
              >
                Mitra Pemilik Villa
              </button>
            </div>
          </div>

          <div>
            <label class="field-label" for="name">Nama Lengkap<span class="text-red-500">*</span></label>
            <input
              id="name"
              v-model="form.name"
              type="text"
              required
              placeholder="Masukkan nama Anda..."
              class="auth-input mt-1"
            >
            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
          </div>

          <div>
            <label class="field-label" for="email">E-mail<span class="text-red-500">*</span></label>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              placeholder="Masukkan email Anda..."
              class="auth-input mt-1"
            >
            <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
          </div>

          <div>
            <label class="field-label" for="phone">No HP<span class="text-red-500">*</span></label>
            <input
              id="phone"
              v-model="form.phone"
              type="tel"
              placeholder="89831982"
              class="auth-input mt-1"
            >
            <p v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone[0] }}</p>
          </div>

          <template v-if="form.role === 'mitra'">
            <div>
              <label class="field-label" for="business_name">Nama Usaha/Villa<span class="text-red-500">*</span></label>
              <input
                id="business_name"
                v-model="form.business_name"
                type="text"
                required
                placeholder="Masukkan nama usaha Anda..."
                class="auth-input mt-1"
              >
              <p v-if="errors.business_name" class="mt-1 text-sm text-red-600">{{ errors.business_name[0] }}</p>
            </div>

            <div>
              <label class="field-label" for="business_address">Alamat Usaha</label>
              <textarea
                id="business_address"
                v-model="form.business_address"
                rows="2"
                placeholder="Masukkan alamat usaha Anda..."
                class="field-input mt-1 rounded-2xl"
              />
              <p v-if="errors.business_address" class="mt-1 text-sm text-red-600">{{ errors.business_address[0] }}</p>
            </div>
          </template>

          <div>
            <label class="field-label" for="password">Password<span class="text-red-500">*</span></label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              minlength="8"
              placeholder="Masukkan Password Anda..."
              class="auth-input mt-1"
            >
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
          </div>

          <div>
            <label class="field-label" for="password_confirmation">Konfirmasi Password<span class="text-red-500">*</span></label>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              required
              minlength="8"
              placeholder="Masukkan Password Anda..."
              class="auth-input mt-1"
            >
          </div>

          <div class="flex items-center gap-3 text-xs text-gray-400">
            <span class="h-px flex-1 bg-gray-200" />
            atau
            <span class="h-px flex-1 bg-gray-200" />
          </div>

          <div class="flex flex-wrap justify-center gap-2">
            <SocialLoginButton provider="google" />
            <SocialLoginButton provider="facebook" />
            <SocialLoginButton provider="apple" />
          </div>

          <p v-if="generalError" class="text-sm text-red-600">{{ generalError }}</p>

          <button type="submit" :disabled="submitting" class="btn-primary w-full">
            {{ submitting ? 'Memproses...' : 'DAFTAR' }}
          </button>
        </form>

        <p class="mt-5 text-center text-sm text-gray-600">
          Sudah punya akun?
          <NuxtLink to="/login" class="font-semibold text-brand-brown hover:underline">Masuk</NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>
