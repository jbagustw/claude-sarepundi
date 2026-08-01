<script setup lang="ts">
const props = defineProps<{
  images: { id: number; url: string }[]
}>()

const activeIndex = ref<number | null>(null)

function open(index: number) {
  activeIndex.value = index
}

function close() {
  activeIndex.value = null
}

function prev() {
  if (activeIndex.value === null) return
  activeIndex.value = (activeIndex.value - 1 + props.images.length) % props.images.length
}

function next() {
  if (activeIndex.value === null) return
  activeIndex.value = (activeIndex.value + 1) % props.images.length
}

function onKeydown(event: KeyboardEvent) {
  if (activeIndex.value === null) return
  if (event.key === 'Escape') close()
  else if (event.key === 'ArrowLeft') prev()
  else if (event.key === 'ArrowRight') next()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div v-if="images.length" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
    <button
      v-for="(image, index) in images"
      :key="image.id"
      type="button"
      class="group relative overflow-hidden rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-terracotta"
      @click="open(index)"
    >
      <img
        :src="image.url"
        class="h-32 w-full object-cover transition group-hover:scale-105 group-hover:brightness-90"
        alt=""
      >
      <span class="absolute inset-0 flex items-center justify-center opacity-0 transition group-hover:opacity-100">
        <span class="rounded-full bg-black/50 px-2.5 py-1 text-xs font-medium text-white">Perbesar</span>
      </span>
    </button>
  </div>

  <Teleport to="body">
    <div
      v-if="activeIndex !== null"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
      @click.self="close"
    >
      <button
        type="button"
        class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20"
        aria-label="Tutup"
        @click="close"
      >
        &times;
      </button>

      <button
        v-if="images.length > 1"
        type="button"
        class="absolute left-2 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20 sm:left-4"
        aria-label="Sebelumnya"
        @click.stop="prev"
      >
        &lsaquo;
      </button>

      <img
        v-if="activeIndex !== null"
        :src="images[activeIndex].url"
        class="max-h-[85vh] max-w-full rounded-lg object-contain"
        alt=""
      >

      <button
        v-if="images.length > 1"
        type="button"
        class="absolute right-2 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-2xl text-white hover:bg-white/20 sm:right-4"
        aria-label="Berikutnya"
        @click.stop="next"
      >
        &rsaquo;
      </button>

      <p v-if="images.length > 1" class="absolute bottom-4 text-sm text-white/80">
        {{ activeIndex + 1 }} / {{ images.length }}
      </p>
    </div>
  </Teleport>
</template>
