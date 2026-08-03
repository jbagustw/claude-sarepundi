<script setup lang="ts">
import type { HomestayFormPayload } from '~/types/homestay'
import type { Facility } from '~/types/villa'

const props = defineProps<{
  modelValue: HomestayFormPayload
  facilities: Facility[]
  submitting: boolean
  errors: Record<string, string[]>
  submitLabel: string
}>()

const emit = defineEmits<{
  'update:modelValue': [HomestayFormPayload]
  submit: []
}>()

const form = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

function toggleFacility(id: number) {
  const set = new Set(form.value.facility_ids)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  form.value = { ...form.value, facility_ids: Array.from(set) }
}
</script>

<template>
  <form class="space-y-4" @submit.prevent="emit('submit')">
    <div>
      <label class="block text-sm font-medium text-gray-700" for="homestay-name">Nama Homestay</label>
      <input
        id="homestay-name"
        v-model="form.name"
        type="text"
        required
        class="field-input mt-1"
      >
      <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="homestay-description">Deskripsi</label>
      <RichTextEditor
        id="homestay-description"
        v-model="form.description"
        placeholder="Ceritakan keunggulan homestay ini..."
        class="mt-1"
      />
      <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description[0] }}</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="homestay-address">Alamat</label>
      <input
        id="homestay-address"
        v-model="form.address"
        type="text"
        class="field-input mt-1"
      >
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700" for="homestay-city">Kota</label>
        <input
          id="homestay-city"
          v-model="form.city"
          type="text"
          required
          class="field-input mt-1"
        >
        <p v-if="errors.city" class="mt-1 text-sm text-red-600">{{ errors.city[0] }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700" for="homestay-province">Provinsi</label>
        <input
          id="homestay-province"
          v-model="form.province"
          type="text"
          class="field-input mt-1"
        >
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700" for="homestay-capacity">Kapasitas Tamu</label>
        <input
          id="homestay-capacity"
          v-model.number="form.capacity_guest"
          type="number"
          min="1"
          required
          class="field-input mt-1"
        >
        <p v-if="errors.capacity_guest" class="mt-1 text-sm text-red-600">{{ errors.capacity_guest[0] }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700" for="homestay-bedroom">Jumlah Kamar</label>
        <input
          id="homestay-bedroom"
          v-model.number="form.bedroom_count"
          type="number"
          min="0"
          class="field-input mt-1"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700" for="homestay-bathroom">Jumlah Kamar Mandi</label>
        <input
          id="homestay-bathroom"
          v-model.number="form.bathroom_count"
          type="number"
          min="0"
          class="field-input mt-1"
        >
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="homestay-price">Harga Dasar / Malam (Rp)</label>
      <input
        id="homestay-price"
        v-model.number="form.base_price"
        type="number"
        min="0"
        required
        class="field-input mt-1"
      >
      <p v-if="errors.base_price" class="mt-1 text-sm text-red-600">{{ errors.base_price[0] }}</p>
    </div>

    <div>
      <span class="block text-sm font-medium text-gray-700">Fasilitas</span>
      <div class="mt-2 flex flex-wrap gap-2">
        <label
          v-for="facility in facilities"
          :key="facility.id"
          class="flex cursor-pointer items-center gap-1.5"
          :class="form.facility_ids.includes(facility.id) ? 'chip-active' : 'chip'"
        >
          <input
            type="checkbox"
            class="hidden"
            :checked="form.facility_ids.includes(facility.id)"
            @change="toggleFacility(facility.id)"
          >
          {{ facility.name }}
        </label>
      </div>
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
