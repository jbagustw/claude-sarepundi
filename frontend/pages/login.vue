<script setup lang="ts">
const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({
  email: '',
  password: '',
})

const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const submitting = ref(false)

async function handleSubmit() {
  errors.value = {}
  generalError.value = ''
  submitting.value = true

  try {
    await authStore.login({ email: form.email, password: form.password })

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    router.push(redirect || ROLE_HOME[authStore.role as keyof typeof ROLE_HOME])
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
        <h1 class="text-center font-display text-2xl font-bold text-gray-900 sm:text-3xl">Masuk ke Akun Anda</h1>
        <p class="mt-2 text-center text-sm text-gray-500">
          Yuk masuk dan mulai cari villa terbaik untuk liburanmu selanjutnya!
        </p>

        <div class="mt-6 flex flex-wrap justify-center gap-2">
          <SocialLoginButton provider="google" />
          <SocialLoginButton provider="facebook" />
          <SocialLoginButton provider="apple" />
        </div>

        <div class="my-6 flex items-center gap-3 text-xs text-gray-400">
          <span class="h-px flex-1 bg-gray-200" />
          atau
          <span class="h-px flex-1 bg-gray-200" />
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
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
            <label class="field-label" for="password">Password<span class="text-red-500">*</span></label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              required
              placeholder="Masukkan Password Anda..."
              class="auth-input mt-1"
            >
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
          </div>

          <p v-if="generalError" class="text-sm text-red-600">{{ generalError }}</p>

          <button type="submit" :disabled="submitting" class="btn-primary w-full">
            {{ submitting ? 'Memproses...' : 'MASUK' }}
          </button>
        </form>

        <p class="mt-5 text-center text-sm text-gray-600">
          Belum punya akun?
          <NuxtLink to="/register" class="font-semibold text-brand-brown hover:underline">Daftar</NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>
