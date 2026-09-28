<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, 'Segoe UI', Arial, sans-serif; background: #F6F7F8; margin: 0; padding: 32px 16px;">
    <table role="presentation" width="100%" style="max-width: 480px; margin: 0 auto; background: #FFFFFF; border-radius: 8px; overflow: hidden; border: 1px solid #E3E7EB;">
        <tr>
            <td style="padding: 32px 32px 24px;">
                <p style="margin: 0 0 8px; font-size: 14px; color: #6B7280;">
                    {{ config('app.name') }}
                </p>
                <h1 style="margin: 0 0 16px; font-size: 20px; color: #1F2937;">
                    @if ($kind === 'expired')
                        Masa trial {{ $companyName }} telah berakhir
                    @else
                        Masa trial {{ $companyName }} segera berakhir
                    @endif
                </h1>
                <p style="margin: 0 0 16px; font-size: 14px; line-height: 22px; color: #4B5563;">
                    Halo {{ $recipientName }},
                </p>
                @if ($kind === 'expired')
                    <p style="margin: 0 0 16px; font-size: 14px; line-height: 22px; color: #4B5563;">
                        Trial gratis untuk <strong>{{ $companyName }}</strong> berakhir pada
                        {{ $trialEndsAt->copy()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y H:i') }} WIB. Akses ke perusahaan ini sekarang terkunci.
                    </p>
                    <p style="margin: 0 0 24px; font-size: 14px; line-height: 22px; color: #B91C1C;">
                        Pilih paket sebelum <strong>{{ $purgeAt->copy()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y') }}</strong>.
                        Setelah tanggal itu, seluruh data perusahaan akan dihapus permanen dan akun tidak dapat login lagi.
                    </p>
                @else
                    <p style="margin: 0 0 24px; font-size: 14px; line-height: 22px; color: #4B5563;">
                        Trial gratis untuk <strong>{{ $companyName }}</strong> berakhir pada
                        {{ $trialEndsAt->copy()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y H:i') }} WIB.
                        Pilih paket sekarang supaya pekerjaan Anda tidak terhenti.
                    </p>
                @endif
                <div style="text-align: center; margin: 0 0 24px;">
                    <a href="{{ $billingUrl }}" style="display: inline-block; padding: 12px 24px; font-size: 14px; font-weight: 600; color: #FFFFFF; background: #0F3D5E; border-radius: 6px; text-decoration: none;">
                        Pilih Paket &amp; Bayar
                    </a>
                </div>
                <p style="margin: 0; font-size: 12px; line-height: 18px; color: #9BA5AF;">
                    Email ini dikirim otomatis karena Anda adalah pemilik atau administrator {{ $companyName }}.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
