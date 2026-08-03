<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Receipt {{ $booking->booking_code }}</title>
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
    table.cols { width: 100%; }
    table.cols td { width: 50%; vertical-align: top; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e0d8; padding: 6px 4px; }
    table.items td { padding: 8px 4px; border-bottom: 1px solid #f1ede6; vertical-align: top; }
    table.items td.num { text-align: right; }
    table.totals { width: 100%; margin-top: 4px; }
    table.totals td { padding: 3px 4px; }
    table.totals td.num { text-align: right; }
    .grand-total td { border-top: 1px solid #2b2118; font-weight: bold; font-size: 13px; padding-top: 8px; }
    .paid-badge { display: inline-block; background: #eef3e9; color: #4a6741; border: 1px solid #bcd3ae; padding: 6px 14px; border-radius: 6px; font-weight: bold; font-size: 13px; }
    .footer { margin-top: 28px; font-size: 10px; color: #9ca3af; }
</style>
</head>
<body>
    <div class="brand">sare<span>pundi</span></div>
    <div class="doc-title">Bukti Pembayaran (Receipt)</div>
    <p class="muted">
        Nomor: <strong>RCP-{{ $booking->booking_code }}</strong><br>
        Tanggal: {{ ($booking->latestPayment?->paid_at ?? $booking->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}
    </p>

    <table class="cols">
        <tr>
            <td>
                <div class="box" style="margin-top: 0;">
                    <div class="section-title">Data Pemesan</div>
                    <table class="kv">
                        <tr><td class="label">Nama</td><td>{{ $booking->user->name }}</td></tr>
                        <tr><td class="label">Email</td><td>{{ $booking->user->email }}</td></tr>
                        @if($booking->user->phone)
                            <tr><td class="label">No. Kontak</td><td>{{ $booking->user->phone }}</td></tr>
                        @endif
                    </table>
                </div>
            </td>
            <td>
                <div class="box" style="margin-top: 0;">
                    <div class="section-title">Detail Pembayaran</div>
                    <table class="kv">
                        <tr><td class="label">No. Booking</td><td>{{ $booking->booking_code }}</td></tr>
                        <tr><td class="label">Metode</td><td>{{ $booking->latestPayment?->payment_method ?? '-' }}</td></tr>
                        <tr><td class="label">Status</td><td>{{ $booking->latestPayment?->status === 'success' ? 'Lunas' : ucfirst($booking->latestPayment?->status ?? '-') }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="box">
        <div class="section-title">Detail Pembelian</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th class="num">Jml.</th>
                    <th class="num">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        {{ $booking->bookable->name }}
                        <br><span class="muted" style="font-size: 10px;">
                            @if($type === 'gathering_venue')
                                {{ \Illuminate\Support\Carbon::parse($booking->check_in_date)->locale('id')->translatedFormat('d M Y') }}@if($booking->slot) &middot; {{ $booking->slot->name }} @endif &middot; {{ $booking->guest_count }} tamu
                            @elseif($type === 'transport')
                                {{ $booking->nights() }} hari &middot; {{ $booking->transport_with_driver ? 'Dengan Sopir' : 'Lepas Kunci' }}
                            @else
                                {{ $booking->nights() }} malam &middot; {{ $booking->guest_count }} tamu
                            @endif
                        </span>
                    </td>
                    <td class="num">1</td>
                    <td class="num">Rp {{ number_format($booking->subtotal, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="muted">Subtotal</td>
                <td class="num">Rp {{ number_format($booking->subtotal, 0, ',', '.') }}</td>
            </tr>
            @if($booking->discount_amount > 0)
                <tr>
                    <td class="muted">Diskon Kupon @if($booking->coupon)({{ $booking->coupon->code }})@endif</td>
                    <td class="num">-Rp {{ number_format($booking->discount_amount, 0, ',', '.') }}</td>
                </tr>
            @endif
            <tr class="grand-total">
                <td>Total Pembayaran</td>
                <td class="num">Rp {{ number_format($booking->total_price, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    @if($booking->latestPayment?->status === 'success')
        <p style="margin-top: 18px;"><span class="paid-badge">&#10003; LUNAS</span></p>
    @endif

    <p class="footer">
        Dokumen ini adalah bukti pembayaran yang sah untuk keperluan verifikasi transaksi. Untuk pertanyaan, hubungi
        halo@sarepundi.com dan sertakan No. Booking {{ $booking->booking_code }}.
    </p>
</body>
</html>
