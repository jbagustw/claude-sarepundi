<template>
  <AuthCard
    title="Pendaftaran Akun"
    subtitle="Mulai perjalananmu hari ini. Daftar sekarang! Temukan dan sewa villa impianmu"
  >
    <AuthRegisterForm role="user" />

    <p class="mt-5 text-center text-sm text-gray-600">
      Sudah punya akun?
      <NuxtLink to="/login" class="font-semibold text-brand-brown hover:underline">Masuk</NuxtLink>
    </p>
    <p class="mt-2 text-center text-sm text-gray-600">
      Pemilik villa/properti?
      <NuxtLink to="/daftar-mitra" class="font-semibold text-brand-brown hover:underline">Daftar sebagai Mitra</NuxtLink>
    </p>
  </AuthCard>
</template>
