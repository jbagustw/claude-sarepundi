<script setup lang="ts">
import type { AdminUser } from '~/types/admin'

definePageMeta({ role: 'admin' })

const api = useApi()
const users = ref<AdminUser[]>([])
const loading = ref(true)
const roleFilter = ref('')
const search = ref('')
const actingOn = ref<number | null>(null)
const commissionDraft = ref<Record<number, string>>({})

async function loadUsers() {
  loading.value = true
  const query: Record<string, string> = {}
  if (roleFilter.value) query.role = roleFilter.value
  if (search.value) query.search = search.value

  const response = await api<{ data: AdminUser[] }>('/api/admin/users', { query })
  users.value = response.data
  loading.value = false
}

async function toggleSuspend(user: AdminUser) {
  const action = user.status === 'active' ? 'suspend' : 'activate'
  if (action === 'suspend' && !confirm(`Suspend akun ${user.name}?`)) return

  actingOn.value = user.id
  try {
    await api(`/api/admin/users/${user.id}/${action}`, { method: 'POST' })
    await loadUsers()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal mengubah status akun.')
  } finally {
    actingOn.value = null
  }
}

async function saveCommission(user: AdminUser) {
  if (!user.mitra_profile) return

  const raw = commissionDraft.value[user.id]
  const value = raw === '' || raw === undefined ? null : Number(raw)

  actingOn.value = user.id
  try {
    await api(`/api/admin/mitras/${user.mitra_profile.id}/commission`, {
      method: 'PATCH',
      body: { commission_rate: value },
    })
    await loadUsers()
  } catch (error: any) {
    alert(error?.data?.message || 'Gagal menyimpan komisi.')
  } finally {
    actingOn.value = null
  }
}

onMounted(loadUsers)
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-gray-900">Kelola User & Mitra</h1>

    <form class="mt-4 flex flex-wrap gap-3" @submit.prevent="loadUsers">
      <input
        v-model="search"
        type="text"
        placeholder="Cari nama atau email"
        class="field-input min-w-[240px] flex-1"
      >
      <select v-model="roleFilter" class="field-input">
        <option value="">Semua Role</option>
        <option value="user">User</option>
        <option value="mitra">Mitra</option>
        <option value="admin">Admin</option>
      </select>
      <button type="submit" class="btn-primary">
        Filter
      </button>
    </form>

    <p v-if="loading" class="mt-6 text-gray-600">Memuat...</p>

    <div v-else class="mt-6 space-y-3">
      <div v-for="user in users" :key="user.id" class="card p-4">
        <div class="flex items-start justify-between">
          <div>
            <p class="font-semibold text-gray-900">
              {{ user.name }}
              <span class="ml-1 rounded bg-gray-100 px-2 py-0.5 text-xs uppercase text-gray-500">{{ user.role }}</span>
              <span
                class="ml-1 rounded px-2 py-0.5 text-xs"
                :class="user.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
              >
                {{ user.status === 'active' ? 'Aktif' : 'Disuspend' }}
              </span>
            </p>
            <p class="text-sm text-gray-600">{{ user.email }}</p>
            <p v-if="user.mitra_profile" class="text-sm text-gray-500">
              {{ user.mitra_profile.business_name }} &middot; approval: {{ user.mitra_profile.status }}
            </p>
          </div>

          <button
            v-if="user.role !== 'admin'"
            class="rounded-full px-3 py-1.5 text-sm text-white disabled:opacity-50"
            :class="user.status === 'active' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-700 hover:bg-green-800'"
            :disabled="actingOn === user.id"
            @click="toggleSuspend(user)"
          >
            {{ user.status === 'active' ? 'Suspend' : 'Aktifkan' }}
          </button>
        </div>

        <div v-if="user.mitra_profile" class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3 text-sm">
          <label class="text-gray-600">Komisi platform (%)</label>
          <input
            :value="commissionDraft[user.id] ?? user.mitra_profile.commission_rate ?? ''"
            type="number"
            min="0"
            max="100"
            :placeholder="`Default ${user.mitra_profile.effective_commission_rate}%`"
            class="field-input w-24"
            @input="commissionDraft[user.id] = ($event.target as HTMLInputElement).value"
          >
          <button
            class="btn-primary !px-3 !py-1"
            :disabled="actingOn === user.id"
            @click="saveCommission(user)"
          >
            Simpan
          </button>
          <span class="text-xs text-gray-400">berlaku: {{ user.mitra_profile.effective_commission_rate }}%</span>
        </div>
      </div>
    </div>
  </div>
</template>
