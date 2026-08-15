<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, 'Segoe UI', Arial, sans-serif; background: #F6F7F8; margin: 0; padding: 32px 16px;">
    <table role="presentation" width="100%" style="max-width: 420px; margin: 0 auto; background: #FFFFFF; border-radius: 8px; overflow: hidden; border: 1px solid #E3E7EB;">
        <tr>
            <td style="padding: 32px 32px 24px;">
                <p style="margin: 0 0 8px; font-size: 14px; color: #6B7280;">
                    {{ config('app.name') }}
                </p>
                <h1 style="margin: 0 0 16px; font-size: 20px; color: #1F2937;">
                    Kode verifikasi email Anda
                </h1>
                <p style="margin: 0 0 24px; font-size: 14px; line-height: 22px; color: #6B7280;">
                    Masukkan kode berikut untuk memverifikasi alamat email Anda. Kode ini
                    berlaku selama {{ $expiresInMinutes }} menit.
                </p>
                <div style="text-align: center; margin: 0 0 24px;">
                    <span style="display: inline-block; padding: 12px 24px; font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #0F3D5E; background: #EFF5F9; border-radius: 6px;">
                        {{ $code }}
                    </span>
                </div>
                <p style="margin: 0; font-size: 12px; line-height: 18px; color: #9BA5AF;">
                    Jika Anda tidak meminta kode ini, abaikan email ini.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
