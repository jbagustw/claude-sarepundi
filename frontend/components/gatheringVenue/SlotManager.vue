<script setup lang="ts">
import type { GatheringVenueSlot, GatheringVenueSlotFormPayload } from '~/types/gatheringVenue'

const props = defineProps<{
  venueId: number
  slots: GatheringVenueSlot[]
}>()

const emit = defineEmits<{
  changed: []
}>()

const api = useApi()
const saving = ref(false)
const actingOn = ref<number | null>(null)
const errorMessage = ref('')

const form = reactive<GatheringVenueSlotFormPayload>({
  name: '',
  start_time: '',
  end_time: '',
  price: 0,
})

async function addSlot() {
  errorMessage.value = ''
  saving.value = true

  try {
    await api(`/api/mitra/gathering-venues/${props.venueId}/slots`, {
      method: 'POST',
      body: form,
    })
    form.name = ''
    form.start_time = ''
    form.end_time = ''
    form.price = 0
    emit('changed')
  } catch (error: any) {
    errorMessage.value = error?.data?.errors?.end_time?.[0] || error?.data?.message || 'Gagal menambahkan slot.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(slot: GatheringVenueSlot) {
  actingOn.value = slot.id
  try {
    await api(`/api/mitra/gathering-venues/${props.venueId}/slots/${slot.id}`, {
      method: 'PATCH',
      body: { is_active: !slot.is_active },
    })
    emit('changed')
  } finally {
    actingOn.value = null
  }
}

async function deleteSlot(slot: GatheringVenueSlot) {
  if (!confirm(`Hapus slot "${slot.name}"?`)) return

  actingOn.value = slot.id
  try {
    await api(`/api/mitra/gathering-venues/${props.venueId}/slots/${slot.id}`, { method: 'DELETE' })
    emit('changed')
  } finally {
    actingOn.value = null
  }
}
</script>

<template>
  <div>
    <h2 class="font-display text-lg font-semibold text-gray-900">Slot & Harga</h2>
    <p class="mt-1 text-sm text-gray-600">Atur sesi waktu yang bisa dipesan beserta harganya (misal: Sesi Pagi 08.00-12.00).</p>

    <div class="card mt-3 grid grid-cols-2 gap-3 p-4 sm:grid-cols-5">
      <input v-model="form.name" type="text" placeholder="Nama sesi" class="field-input sm:col-span-2">
      <input v-model="form.start_time" type="time" class="field-input">
      <input v-model="form.end_time" type="time" class="field-input">
      <input v-model.number="form.price" type="number" min="0" placeholder="Harga" class="field-input">

      <button class="btn-primary sm:col-span-5" :disabled="saving" @click="addSlot">
        {{ saving ? 'Menyimpan...' : '+ Tambah Slot' }}
      </button>
      <p v-if="errorMessage" class="text-sm text-red-600 sm:col-span-5">{{ errorMessage }}</p>
    </div>

    <p v-if="slots.length === 0" class="mt-4 text-sm text-gray-500">Belum ada slot. Tambahkan minimal satu slot supaya lokasi ini bisa dipesan nanti.</p>

    <ul v-else class="card mt-4 divide-y divide-gray-100 text-sm">
      <li v-for="slot in slots" :key="slot.id" class="flex items-center justify-between px-4 py-3">
        <div>
          <p class="font-medium text-gray-900">{{ slot.name }}</p>
          <p class="text-xs text-gray-500">{{ slot.start_time }} - {{ slot.end_time }} &middot; {{ formatRupiah(slot.price) }}</p>
        </div>
        <div class="flex items-center gap-3">
          <span class="badge" :class="slot.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'">
            {{ slot.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
          <button
            class="text-xs text-brand-brown underline disabled:opacity-50"
            :disabled="actingOn === slot.id"
            @click="toggleActive(slot)"
          >
            {{ slot.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
          </button>
          <button
            class="text-xs text-red-600 underline disabled:opacity-50"
            :disabled="actingOn === slot.id"
            @click="deleteSlot(slot)"
          >
            Hapus
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>
