<script setup lang="ts">
import type { TransportFormPayload } from '~/types/transport'

const props = defineProps<{
  modelValue: TransportFormPayload
  submitting: boolean
  errors: Record<string, string[]>
  submitLabel: string
}>()

const emit = defineEmits<{
  'update:modelValue': [TransportFormPayload]
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
      <label class="block text-sm font-medium text-gray-700" for="transport-name">Nama Kendaraan</label>
      <input
        id="transport-name"
        v-model="form.name"
        type="text"
        placeholder="mis. Toyota Avanza Silver"
        required
        class="field-input mt-1"
      >
      <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="transport-type">Tipe Kendaraan</label>
      <input
        id="transport-type"
        v-model="form.vehicle_type"
        type="text"
        placeholder="mis. MPV, Bus, Motor, SUV"
        required
        class="field-input mt-1"
      >
      <p v-if="errors.vehicle_type" class="mt-1 text-sm text-red-600">{{ errors.vehicle_type[0] }}</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="transport-description">Deskripsi</label>
      <textarea
        id="transport-description"
        v-model="form.description"
        rows="4"
        class="field-input mt-1"
      />
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700" for="transport-capacity">Kapasitas (kursi)</label>
        <input
          id="transport-capacity"
          v-model.number="form.capacity"
          type="number"
          min="1"
          required
          class="field-input mt-1"
        >
        <p v-if="errors.capacity" class="mt-1 text-sm text-red-600">{{ errors.capacity[0] }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700" for="transport-city">Kota Basis</label>
        <input
          id="transport-city"
          v-model="form.city"
          type="text"
          required
          class="field-input mt-1"
        >
        <p v-if="errors.city" class="mt-1 text-sm text-red-600">{{ errors.city[0] }}</p>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="transport-province">Provinsi</label>
      <input
        id="transport-province"
        v-model="form.province"
        type="text"
        class="field-input mt-1"
      >
    </div>

    <div class="rounded-xl border border-gray-200 p-4">
      <p class="text-sm font-medium text-gray-700">Harga Sewa per Hari</p>
      <p class="mt-1 text-xs text-gray-500">Isi minimal salah satu — kosongkan yang tidak ditawarkan.</p>

      <div class="mt-3 grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-700" for="transport-price-self">Lepas Kunci (Rp)</label>
          <input
            id="transport-price-self"
            v-model.number="form.price_per_day_self_drive"
            type="number"
            min="0"
            class="field-input mt-1"
          >
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700" for="transport-price-driver">Dengan Supir (Rp)</label>
          <input
            id="transport-price-driver"
            v-model.number="form.price_per_day_with_driver"
            type="number"
            min="0"
            class="field-input mt-1"
          >
        </div>
      </div>
      <p v-if="errors.price_per_day_self_drive" class="mt-2 text-sm text-red-600">{{ errors.price_per_day_self_drive[0] }}</p>
    </div>

    <button
      type="submit"
      :disabled="submitting"
      class="btn-primary"
    >
      {{ submitting ? 'Menyimpan...' : submitLabel }}
    </button>
  </form>
</template>
