<script setup lang="ts">
import type { Notification } from '~/types/notification'

const api = useApi()
const notifications = ref<Notification[]>([])
const unreadCount = ref(0)
const open = ref(false)
const loaded = ref(false)

async function loadNotifications() {
  try {
    const response = await api<{ data: Notification[]; meta: { unread_count: number } }>('/api/notifications')
    notifications.value = response.data
    unreadCount.value = response.meta.unread_count
    loaded.value = true
  } catch {
    // Auth state can race with logout (session already cleared server-side
    // by the time this in-flight request resolves) — nothing to show.
  }
}

async function toggle() {
  open.value = !open.value
  if (open.value && !loaded.value) await loadNotifications()
}

async function markRead(notification: Notification) {
  if (notification.is_read) return

  notification.is_read = true
  unreadCount.value = Math.max(0, unreadCount.value - 1)
  await api(`/api/notifications/${notification.id}/read`, { method: 'POST' })
}

async function markAllRead() {
  notifications.value.forEach(n => { n.is_read = true })
  unreadCount.value = 0
  await api('/api/notifications/read-all', { method: 'POST' })
}

onMounted(loadNotifications)
</script>

<template>
  <div class="relative">
    <button
      class="relative rounded p-1.5 text-white/85 hover:bg-white/10"
      aria-label="Notifikasi"
      @click="toggle"
    >
      <span aria-hidden="true">🔔</span>
      <span
        v-if="unreadCount > 0"
        class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-medium text-white"
      >
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <div
      v-if="open"
      class="card absolute right-0 z-10 mt-2 w-80 overflow-hidden"
    >
      <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
        <p class="text-sm font-medium text-gray-900">Notifikasi</p>
        <button
          v-if="unreadCount > 0"
          class="text-xs text-brand-brown hover:underline"
          @click="markAllRead"
        >
          Tandai semua dibaca
        </button>
      </div>

      <div class="max-h-96 overflow-y-auto">
        <p v-if="notifications.length === 0" class="px-3 py-4 text-sm text-gray-600">Belum ada notifikasi.</p>

        <button
          v-for="notification in notifications"
          :key="notification.id"
          class="block w-full border-b border-gray-50 px-3 py-2 text-left hover:bg-gray-50"
          :class="{ 'bg-brand-sage/10': !notification.is_read }"
          @click="markRead(notification)"
        >
          <p class="text-sm font-medium text-gray-900">{{ notification.title }}</p>
          <p class="mt-0.5 text-xs text-gray-600">{{ notification.message }}</p>
          <p class="mt-1 text-[11px] text-gray-400">{{ new Date(notification.created_at).toLocaleString('id-ID') }}</p>
        </button>
      </div>
    </div>
  </div>
</template>
