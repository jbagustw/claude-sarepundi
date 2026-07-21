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
  <div class="mx-auto max-w-sm">
    <h1 class="text-2xl font-bold text-gray-900">Masuk</h1>
    <p class="mt-1 text-sm text-gray-600">Masuk ke akunmu untuk melanjutkan.</p>

    <form class="mt-6 space-y-4" @submit.prevent="handleSubmit">
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
        <label class="block text-sm font-medium text-gray-700" for="password">Password</label>
        <input
          id="password"
          v-model="form.password"
          type="password"
          required
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
        <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
      </div>

      <p v-if="generalError" class="text-sm text-red-600">{{ generalError }}</p>

      <button
        type="submit"
        :disabled="submitting"
        class="w-full rounded bg-gray-900 px-4 py-2 text-white hover:bg-gray-700 disabled:opacity-50"
      >
        {{ submitting ? 'Memproses...' : 'Masuk' }}
      </button>
    </form>

    <p class="mt-4 text-sm text-gray-600">
      Belum punya akun?
      <NuxtLink to="/register" class="font-medium text-gray-900 underline">Daftar</NuxtLink>
    </p>
  </div>
</template>
