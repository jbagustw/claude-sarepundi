<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Voucher {{ $booking->booking_code }}</title>
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #2b2118; margin: 0; padding: 32px; }
    .brand { font-size: 20px; font-weight: bold; color: #6b4226; }
    .brand span { color: #d99a2b; }
    .doc-title { font-size: 22px; font-weight: bold; margin: 18px 0 4px; }
    .muted { color: #6b7280; }
    .box { border: 1px solid #e5e0d8; border-radius: 6px; padding: 14px 16px; margin-top: 16px; }
    .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: .04em; color: #6b4226; margin-bottom: 8px; }
    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 3px 0; vertical-align: top; }
    table.kv td.label { width: 38%; color: #6b7280; }
    .divider { border-top: 1px solid #e5e0d8; margin: 16px 0; }
    .badge { display: inline-block; background: #eef3e9; color: #4a6741; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; }
    .footer { margin-top: 28px; font-size: 10px; color: #9ca3af; }
    ul.policy { margin: 6px 0 0; padding-left: 16px; }
    ul.policy li { margin-bottom: 3px; }
</style>
</head>
<body>
    <div class="brand">sare<span>pundi</span></div>
    <div class="doc-title">Voucher {{ $typeLabel }}</div>
    <p class="muted">No. Booking Sarepundi: <strong>{{ $booking->booking_code }}</strong></p>

    <div class="box">
        <div class="section-title">{{ $type === 'transport' ? 'Detail Kendaraan' : 'Detail Properti' }}</div>
        <p style="margin: 0; font-weight: bold; font-size: 13px;">{{ $booking->bookable->name }}</p>
        @if($type !== 'transport' && !empty($booking->bookable->address))
            <p class="muted" style="margin: 2px 0 0;">{{ $booking->bookable->address }}, {{ $booking->bookable->city }}@if(!empty($booking->bookable->province)), {{ $booking->bookable->province }}@endif</p>
        @else
            <p class="muted" style="margin: 2px 0 0;">{{ $booking->bookable->city }}@if(!empty($booking->bookable->province)), {{ $booking->bookable->province }}@endif</p>
        @endif
        @if($booking->bookable->mitraProfile?->user?->phone)
            <p class="muted" style="margin: 2px 0 0;">Telepon Mitra: {{ $booking->bookable->mitraProfile->user->phone }}</p>
        @endif
        <p class="muted" style="margin: 2px 0 0;">Dikelola oleh {{ $booking->bookable->mitraProfile?->business_name }}</p>
    </div>

    <div class="box">
        <div class="section-title">Detail Tamu</div>
        <table class="kv">
            <tr><td class="label">Nama Tamu</td><td>{{ $booking->user->name }}</td></tr>
            <tr><td class="label">Email</td><td>{{ $booking->user->email }}</td></tr>
            @if($booking->user->phone)
                <tr><td class="label">No. Kontak</td><td>{{ $booking->user->phone }}</td></tr>
            @endif
            <tr><td class="label">Jumlah Tamu</td><td>{{ $booking->guest_count }} orang</td></tr>
        </table>
    </div>

    <div class="box">
        <div class="section-title">Detail Pesanan</div>
        <table class="kv">
            @if($type === 'gathering_venue')
                <tr><td class="label">Tanggal Acara</td><td>{{ \Illuminate\Support\Carbon::parse($booking->check_in_date)->locale('id')->translatedFormat('d F Y') }}</td></tr>
                @if($booking->slot)
                    <tr><td class="label">Sesi</td><td>{{ $booking->slot->name }} ({{ substr($booking->slot->start_time, 0, 5) }}&ndash;{{ substr($booking->slot->end_time, 0, 5) }})</td></tr>
                @endif
            @elseif($type === 'transport')
                <tr><td class="label">Tanggal Mulai</td><td>{{ \Illuminate\Support\Carbon::parse($booking->check_in_date)->locale('id')->translatedFormat('d F Y') }}</td></tr>
                <tr><td class="label">Tanggal Selesai</td><td>{{ \Illuminate\Support\Carbon::parse($booking->check_out_date)->locale('id')->translatedFormat('d F Y') }}</td></tr>
                <tr><td class="label">Opsi</td><td>{{ $booking->transport_with_driver ? 'Dengan Sopir' : 'Lepas Kunci (Self-Drive)' }}</td></tr>
            @else
                <tr><td class="label">Check-in</td><td>{{ \Illuminate\Support\Carbon::parse($booking->check_in_date)->locale('id')->translatedFormat('d F Y') }}</td></tr>
                <tr><td class="label">Check-out</td><td>{{ \Illuminate\Support\Carbon::parse($booking->check_out_date)->locale('id')->translatedFormat('d F Y') }}</td></tr>
            @endif
        </table>
        <div class="divider"></div>
        <span class="badge">{{ match($booking->status) {
            'dikonfirmasi' => 'Dikonfirmasi',
            'checked_in' => 'Sedang Berlangsung',
            'selesai' => 'Selesai',
            'dibatalkan_user' => 'Dibatalkan',
            default => ucfirst($booking->status),
        } }}</span>
    </div>

    <div class="box">
        <div class="section-title">Kebijakan Pembatalan</div>
        <ul class="policy">
            <li>Pembatalan oleh pengguna: refund 85% jika dilakukan minimal H-2 sebelum {{ $type === 'gathering_venue' ? 'tanggal acara' : ($type === 'transport' ? 'tanggal mulai sewa' : 'check-in') }}, tidak ada refund jika kurang dari H-2.</li>
            <li>Tunjukkan voucher ini (cetak atau digital) kepada mitra saat {{ $type === 'transport' ? 'serah terima kendaraan' : ($type === 'gathering_venue' ? 'penggunaan lokasi' : 'check-in') }}.</li>
        </ul>
    </div>

    <p class="footer">
        Butuh bantuan? Hubungi kami di halo@sarepundi.com dan sertakan No. Booking {{ $booking->booking_code }}.<br>
        Dokumen ini diterbitkan otomatis oleh sistem Sarepundi dan sah tanpa tanda tangan basah.
    </p>
</body>
</html>
