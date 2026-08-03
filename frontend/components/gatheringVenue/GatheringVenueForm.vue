<script setup lang="ts">
import type { GatheringVenueFormPayload } from '~/types/gatheringVenue'
import type { Facility } from '~/types/villa'

const props = defineProps<{
  modelValue: GatheringVenueFormPayload
  facilities: Facility[]
  submitting: boolean
  errors: Record<string, string[]>
  submitLabel: string
}>()

const emit = defineEmits<{
  'update:modelValue': [GatheringVenueFormPayload]
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
      <label class="block text-sm font-medium text-gray-700" for="gathering-name">Nama Lokasi</label>
      <input
        id="gathering-name"
        v-model="form.name"
        type="text"
        required
        class="field-input mt-1"
      >
      <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name[0] }}</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="gathering-description">Deskripsi</label>
      <RichTextEditor
        id="gathering-description"
        v-model="form.description"
        placeholder="Ceritakan keunggulan lokasi ini..."
        class="mt-1"
      />
      <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description[0] }}</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="gathering-address">Alamat</label>
      <input
        id="gathering-address"
        v-model="form.address"
        type="text"
        class="field-input mt-1"
      >
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700" for="gathering-city">Kota</label>
        <input
          id="gathering-city"
          v-model="form.city"
          type="text"
          required
          class="field-input mt-1"
        >
        <p v-if="errors.city" class="mt-1 text-sm text-red-600">{{ errors.city[0] }}</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700" for="gathering-province">Provinsi</label>
        <input
          id="gathering-province"
          v-model="form.province"
          type="text"
          class="field-input mt-1"
        >
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700" for="gathering-capacity">Kapasitas (orang)</label>
      <input
        id="gathering-capacity"
        v-model.number="form.capacity"
        type="number"
        min="1"
        required
        class="field-input mt-1"
      >
      <p v-if="errors.capacity" class="mt-1 text-sm text-red-600">{{ errors.capacity[0] }}</p>
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
