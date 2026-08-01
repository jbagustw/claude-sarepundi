<script setup lang="ts">
const props = defineProps<{
  role: 'user' | 'mitra'
}>()

const authStore = useAuthStore()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
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
    await authStore.register({ ...form, role: props.role })
    router.push(ROLE_HOME[props.role])
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
  <form class="mt-6 space-y-4" @submit.prevent="handleSubmit">
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

    <template v-if="role === 'mitra'">
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

    <template v-if="role === 'user'">
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
    </template>

    <p v-if="generalError" class="text-sm text-red-600">{{ generalError }}</p>

    <button type="submit" :disabled="submitting" class="btn-primary w-full">
      {{ submitting ? 'Memproses...' : 'DAFTAR' }}
    </button>
  </form>
</template>
