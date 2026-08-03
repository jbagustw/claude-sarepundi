<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Booking Dikonfirmasi</title>
</head>
<body style="font-family: -apple-system, Arial, sans-serif; background: #f5f1ea; margin: 0; padding: 24px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td style="background: #6b4226; padding: 20px 28px;">
                            <span style="font-size: 20px; font-weight: bold; color: #ffffff;">sare<span style="color: #d99a2b;">pundi</span></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 28px;">
                            <h1 style="font-size: 18px; margin: 0 0 12px; color: #2b2118;">Pembayaran berhasil, booking kamu dikonfirmasi!</h1>
                            <p style="font-size: 14px; color: #4b5563; line-height: 1.6;">
                                Halo {{ $booking->user->name }},<br><br>
                                Pembayaran untuk booking <strong>{{ $booking->booking_code }}</strong>
                                ({{ $booking->bookable->name }}) sudah kami terima dan booking kamu otomatis
                                dikonfirmasi.
                            </p>
                            <p style="font-size: 14px; color: #4b5563; line-height: 1.6;">
                                Voucher dan bukti pembayaran (receipt) terlampir di email ini dalam format PDF.
                                Tunjukkan voucher kepada mitra saat check-in / penggunaan layanan.
                            </p>
                            <p style="font-size: 13px; color: #9ca3af; margin-top: 24px;">
                                Kamu juga bisa mengunduh ulang kedua dokumen ini kapan saja melalui halaman
                                "Booking Saya" di akun Sarepundi kamu.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
