<script setup lang="ts">
import type { CouponFormPayload } from '~/types/coupon'

const props = defineProps<{
  modelValue: CouponFormPayload
  submitting: boolean
  errors: Record<string, string[]>
  submitLabel: string
}>()

const emit = defineEmits<{
  'update:modelValue': [CouponFormPayload]
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
      <label class="field-label" for="coupon-code">Kode Kupon</label>
      <input
        id="coupon-code"
        v-model="form.code"
        type="text"
        required
        placeholder="mis. HEMAT10"
        class="field-input mt-1 uppercase"
      >
      <p v-if="errors.code" class="mt-1 text-sm text-red-600">{{ errors.code[0] }}</p>
    </div>

    <div>
      <label class="field-label" for="coupon-title">Judul</label>
      <input
        id="coupon-title"
        v-model="form.title"
        type="text"
        required
        placeholder="mis. Diskon 10% Booking Pertama"
        class="field-input mt-1"
      >
      <p v-if="errors.title" class="mt-1 text-sm text-red-600">{{ errors.title[0] }}</p>
    </div>

    <div>
      <label class="field-label" for="coupon-description">Keterangan</label>
      <input
        id="coupon-description"
        v-model="form.description"
        type="text"
        placeholder="mis. Berlaku untuk transaksi pertama di aplikasi"
        class="field-input mt-1"
      >
      <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description[0] }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="field-label" for="coupon-discount-type">Tipe Diskon</label>
        <select id="coupon-discount-type" v-model="form.discount_type" class="field-input mt-1">
          <option value="percentage">Persentase (%)</option>
          <option value="fixed">Nominal Tetap (Rp)</option>
        </select>
      </div>
      <div>
        <label class="field-label" for="coupon-discount-value">
          {{ form.discount_type === 'percentage' ? 'Nilai Diskon (%)' : 'Nilai Diskon (Rp)' }}
        </label>
        <input
          id="coupon-discount-value"
          v-model.number="form.discount_value"
          type="number"
          min="1"
          required
          class="field-input mt-1"
        >
        <p v-if="errors.discount_value" class="mt-1 text-sm text-red-600">{{ errors.discount_value[0] }}</p>
      </div>
    </div>

    <div>
      <label class="field-label" for="coupon-valid-until">Berlaku Sampai (opsional)</label>
      <input
        id="coupon-valid-until"
        v-model="form.valid_until"
        type="date"
        class="field-input mt-1"
      >
      <p v-if="errors.valid_until" class="mt-1 text-sm text-red-600">{{ errors.valid_until[0] }}</p>
    </div>

    <button type="submit" :disabled="submitting" class="btn-primary">
      {{ submitting ? 'Menyimpan...' : submitLabel }}
    </button>
  </form>
</template>
