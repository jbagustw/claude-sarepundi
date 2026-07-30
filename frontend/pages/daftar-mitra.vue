<template>
  <AuthCard
    title="Daftar Sebagai Mitra"
    subtitle="Daftarkan properti Anda dan mulai hasilkan pendapatan dari villa yang Anda kelola"
  >
    <AuthRegisterForm role="mitra" />

    <p class="mt-5 text-center text-sm text-gray-600">
      Sudah punya akun?
      <NuxtLink to="/login" class="font-semibold text-brand-brown hover:underline">Masuk</NuxtLink>
    </p>
    <p class="mt-2 text-center text-sm text-gray-600">
      Ingin mencari &amp; booking villa?
      <NuxtLink to="/register" class="font-semibold text-brand-brown hover:underline">Daftar sebagai Pencari Villa</NuxtLink>
    </p>
  </AuthCard>
</template>
