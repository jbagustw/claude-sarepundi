<script setup lang="ts">
import type { Facility, VillaFormPayload } from '~/types/villa'

const props = defineProps<{
  modelValue: VillaFormPayload
  facilities: Facility[]
  submitting: boolean
  errors: Record<string, string[]>
  submitLabel: string
}>()

const emit = defineEmits<{
  'update:modelValue': [VillaFormPayload]
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
      <label class="block text-sm font-medium text-gray-700" for="villa-name">Nama Villa</label>
      <input
        id="villa-name"
        v-model="form.name"
        type="text"
        required
        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
      >
      <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="villa-description">Deskripsi</label>
      <textarea
        id="villa-description"
        v-model="form.description"
        rows="4"
        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
      />
      <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description[0] }}</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="villa-address">Alamat</label>
      <input
        id="villa-address"
        v-model="form.address"
        type="text"
        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
      >
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700" for="villa-city">Kota</label>
        <input
          id="villa-city"
          v-model="form.city"
          type="text"
          required
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
        <p v-if="errors.city" class="mt-1 text-sm text-red-600">{{ errors.city[0] }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700" for="villa-province">Provinsi</label>
        <input
          id="villa-province"
          v-model="form.province"
          type="text"
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
      </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700" for="villa-capacity">Kapasitas Tamu</label>
        <input
          id="villa-capacity"
          v-model.number="form.capacity_guest"
          type="number"
          min="1"
          required
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
        <p v-if="errors.capacity_guest" class="mt-1 text-sm text-red-600">{{ errors.capacity_guest[0] }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700" for="villa-bedroom">Jumlah Kamar</label>
        <input
          id="villa-bedroom"
          v-model.number="form.bedroom_count"
          type="number"
          min="0"
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700" for="villa-bathroom">Jumlah Kamar Mandi</label>
        <input
          id="villa-bathroom"
          v-model.number="form.bathroom_count"
          type="number"
          min="0"
          class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
        >
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="villa-price">Harga Dasar / Malam (Rp)</label>
      <input
        id="villa-price"
        v-model.number="form.base_price"
        type="number"
        min="0"
        required
        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-gray-900 focus:outline-none"
      >
      <p v-if="errors.base_price" class="mt-1 text-sm text-red-600">{{ errors.base_price[0] }}</p>
    </div>

    <div>
      <span class="block text-sm font-medium text-gray-700">Fasilitas</span>
      <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
        <label
          v-for="facility in facilities"
          :key="facility.id"
          class="flex items-center gap-2 text-sm text-gray-700"
        >
          <input
            type="checkbox"
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
      class="rounded bg-gray-900 px-4 py-2 text-white hover:bg-gray-700 disabled:opacity-50"
    >
      {{ submitting ? 'Menyimpan...' : submitLabel }}
    </button>
  </form>
</template>
