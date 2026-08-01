<script setup lang="ts">
const props = defineProps<{
  provider: 'google' | 'facebook' | 'apple'
}>()

const labels: Record<string, string> = {
  google: 'Google',
  facebook: 'Facebook',
  apple: 'Apple',
}

const label = labels[props.provider]

// Hanya Google yang sudah tersambung ke backend (Laravel Socialite). Facebook
// butuh app review, Apple butuh akun developer berbayar — keduanya tetap
// nonaktif sampai kredensial OAuth-nya disiapkan.
const isEnabled = props.provider === 'google'

const config = useRuntimeConfig()
const redirectUrl = `${config.public.apiBase}/auth/${props.provider}/redirect`
</script>

<template>
  <a
    v-if="isEnabled"
    :href="redirectUrl"
    class="inline-flex items-center gap-2 rounded-full bg-gray-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-gray-800"
  >
    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs font-bold text-blue-600">G</span>
    {{ label }}
  </a>
  <button
    v-else
    type="button"
    disabled
    title="Login sosial segera hadir"
    class="inline-flex items-center gap-2 rounded-full bg-gray-900 px-4 py-2.5 text-sm font-medium text-white opacity-90 cursor-not-allowed"
  >
    <span
      v-if="provider === 'facebook'"
      class="flex h-5 w-5 items-center justify-center rounded-full bg-[#1877F2] text-xs font-bold text-white"
    >f</span>
    <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="white" aria-hidden="true">
      <path d="M16.365 1.43c0 1.14-.468 2.207-1.204 2.99-.813.85-2.11 1.51-3.19 1.42-.132-1.1.44-2.24 1.16-2.98.8-.83 2.17-1.45 3.234-1.43zM20.6 17.24c-.55 1.27-.81 1.84-1.52 2.96-.98 1.55-2.36 3.47-4.08 3.49-1.53.02-1.92-1-4-1-2.07 0-2.5.98-4.02 1-1.68.02-2.96-1.75-3.94-3.3-2.7-4.2-2.98-9.13-1.31-11.75 1.18-1.86 3.04-2.95 4.79-2.95 1.78 0 2.9 1.02 4.37 1.02 1.42 0 2.29-1.03 4.37-1.03 1.55 0 3.19.85 4.36 2.32-3.83 2.1-3.21 7.57 1 9.24z" />
    </svg>
    {{ label }}
  </button>
</template>
