<script setup lang="ts">
import type { BannerFormPayload } from '~/types/banner'

const props = defineProps<{
  modelValue: BannerFormPayload
  submitting: boolean
  errors: Record<string, string[]>
  submitLabel: string
}>()

const emit = defineEmits<{
  'update:modelValue': [BannerFormPayload]
  submit: []
}>()

const form = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
</script>

<template>
  <form class="space-y-4" @submit.prevent="emit('submit')">
    <div>
      <label class="field-label" for="banner-title">Judul (internal, untuk teks alt gambar)</label>
      <input
        id="banner-title"
        v-model="form.title"
        type="text"
        required
        placeholder="mis. Promo Gathering Akhir Tahun"
        class="field-input mt-1"
      >
      <p v-if="errors.title" class="mt-1 text-sm text-red-600">{{ errors.title[0] }}</p>
    </div>

    <div>
      <label class="field-label" for="banner-link">Tautan Tujuan (opsional)</label>
      <input
        id="banner-link"
        v-model="form.link_url"
        type="text"
        placeholder="mis. /gathering-venues"
        class="field-input mt-1"
      >
      <p v-if="errors.link_url" class="mt-1 text-sm text-red-600">{{ errors.link_url[0] }}</p>
    </div>

    <button type="submit" :disabled="submitting" class="btn-primary">
      {{ submitting ? 'Menyimpan...' : submitLabel }}
    </button>
  </form>
</template>
