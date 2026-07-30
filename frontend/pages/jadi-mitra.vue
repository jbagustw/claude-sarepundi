<script setup lang="ts">
const benefits = [
  {
    icon: '💰',
    title: 'Pendapatan Tambahan',
    description: 'Dapatkan 90% dari setiap booking — komisi platform hanya 10%, dihitung transparan di setiap transaksi.',
  },
  {
    icon: '🗓️',
    title: 'Kelola Sendiri Ketersediaan',
    description: 'Atur kalender ketersediaan, harga khusus akhir pekan/high season, dan minimum menginap sesuai keinginan Anda.',
  },
  {
    icon: '🔒',
    title: 'Pembayaran Aman & Otomatis',
    description: 'Dana hasil booking dicairkan otomatis ke rekening Anda lewat sistem payout terjadwal, tanpa perlu tagih manual.',
  },
  {
    icon: '📊',
    title: 'Dashboard Lengkap',
    description: 'Pantau pendapatan, occupancy rate, dan seluruh booking properti Anda dalam satu dashboard mitra.',
  },
]

const steps = [
  { title: 'Daftar Akun Mitra', description: 'Isi data diri dan data usaha Anda melalui formulir pendaftaran mitra.' },
  { title: 'Tunggu Persetujuan Admin', description: 'Tim kami akan memverifikasi akun mitra Anda sebelum bisa mengirim properti.' },
  { title: 'Tambahkan Properti', description: 'Lengkapi data villa: foto, fasilitas, harga, dan kirim untuk direview admin.' },
  { title: 'Mulai Terima Booking', description: 'Setelah properti dipublikasikan, calon tamu bisa menemukan dan memesan villa Anda.' },
]
</script>

<template>
  <div class="-mx-4 -mt-8">
    <section class="relative overflow-hidden bg-gradient-to-br from-brand-brown-dark via-brand-brown to-brand-terracotta px-4 py-16 text-center sm:py-20">
      <div class="pointer-events-none absolute inset-0 opacity-30">
        <div class="absolute -left-16 -top-16 h-72 w-72 rounded-full bg-brand-gold blur-3xl" />
        <div class="absolute -right-10 bottom-0 h-72 w-72 rounded-full bg-brand-sage blur-3xl" />
      </div>

      <div class="relative z-10 mx-auto max-w-2xl">
        <h1 class="font-display text-3xl font-bold text-white sm:text-4xl">
          Jadikan Villa Anda Sumber Pendapatan Baru
        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-white/80 sm:text-base">
          Bergabung sebagai mitra Sarepundi dan pasarkan properti Anda ke ribuan calon tamu
          yang mencari villa untuk liburan mereka.
        </p>
        <div class="mt-6 flex justify-center gap-3">
          <NuxtLink to="/daftar-mitra" class="btn-accent">Daftar Sebagai Mitra Sekarang</NuxtLink>
          <NuxtLink to="/login" class="btn-outline border-white/60 text-white hover:bg-white/10">Sudah Punya Akun?</NuxtLink>
        </div>
      </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4">
      <h2 class="text-center font-display text-xl font-bold text-gray-900 sm:text-2xl">Kenapa Bergabung dengan Sarepundi?</h2>
      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="benefit in benefits" :key="benefit.title" class="card p-5 text-center">
          <div class="text-3xl">{{ benefit.icon }}</div>
          <h3 class="mt-3 font-display font-semibold text-gray-900">{{ benefit.title }}</h3>
          <p class="mt-1 text-sm text-gray-600">{{ benefit.description }}</p>
        </div>
      </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4">
      <h2 class="text-center font-display text-xl font-bold text-gray-900 sm:text-2xl">Cara Bergabung</h2>
      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="(step, index) in steps" :key="step.title" class="card p-5">
          <div class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-brown font-display font-bold text-white">
            {{ index + 1 }}
          </div>
          <h3 class="mt-3 font-display font-semibold text-gray-900">{{ step.title }}</h3>
          <p class="mt-1 text-sm text-gray-600">{{ step.description }}</p>
        </div>
      </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4 pb-4">
      <div class="rounded-2xl bg-brand-footer px-6 py-10 text-center text-white sm:px-12">
        <h2 class="font-display text-xl font-bold sm:text-2xl">Siap Menjadi Mitra Sarepundi?</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-white/70">
          Pendaftaran gratis, tanpa biaya tersembunyi. Mulai daftarkan villa Anda hari ini.
        </p>
        <div class="mt-5">
          <NuxtLink to="/daftar-mitra" class="btn-accent">Daftar Sebagai Mitra Sekarang</NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>
