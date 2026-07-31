<script setup lang="ts">
import type { Review } from '~/types/review'

const props = defineProps<{
  bookingId: number
}>()

const emit = defineEmits<{
  submitted: [review: Review]
}>()

const api = useApi()
const rating = ref(0)
const comment = ref('')
const submitting = ref(false)
const errorMessage = ref('')

async function submit() {
  if (rating.value < 1) {
    errorMessage.value = 'Pilih rating bintang terlebih dahulu.'
    return
  }

  errorMessage.value = ''
  submitting.value = true

  try {
    const response = await api<{ data: Review }>(`/api/bookings/${props.bookingId}/review`, {
      method: 'POST',
      body: { rating: rating.value, comment: comment.value || undefined },
    })
    emit('submitted', response.data)
  } catch (error: any) {
    errorMessage.value = error?.data?.message || 'Gagal mengirim ulasan.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="card p-4">
    <p class="font-medium text-gray-900">Beri ulasan untuk booking ini</p>

    <div class="mt-2 flex gap-1 text-2xl text-brand-gold">
      <button
        v-for="n in 5"
        :key="n"
        type="button"
        class="leading-none"
        :aria-label="`${n} bintang`"
        @click="rating = n"
      >
        {{ n <= rating ? '★' : '☆' }}
      </button>
    </div>

    <textarea
      v-model="comment"
      rows="3"
      maxlength="2000"
      placeholder="Ceritakan pengalaman menginapmu (opsional)"
      class="field-input mt-3 rounded-2xl"
    />

    <button
      class="btn-primary mt-3"
      :disabled="submitting"
      @click="submit"
    >
      {{ submitting ? 'Mengirim...' : 'Kirim Ulasan' }}
    </button>

    <p v-if="errorMessage" class="mt-2 text-sm text-red-600">{{ errorMessage }}</p>
  </div>
</template>
