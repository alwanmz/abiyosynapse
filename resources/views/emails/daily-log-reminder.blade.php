<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminder Catatan Harian</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f4f5; margin: 0; padding: 24px; }
        .wrapper { max-width: 520px; margin: 0 auto; }
        .card { background: #ffffff; border-radius: 12px; padding: 36px 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .icon { font-size: 40px; margin-bottom: 16px; }
        h1 { font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 8px; }
        p { font-size: 15px; color: #4b5563; line-height: 1.6; margin: 0 0 16px; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; text-decoration: none; font-size: 14px; font-weight: 600; padding: 12px 24px; border-radius: 8px; margin-top: 8px; }
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }
        .footer { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="icon">📝</div>
            <h1>Hai, {{ $user->name }}!</h1>
            <p>
                Kamu belum mengisi <strong>Catatan Harian</strong> hari ini,
                <strong>{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</strong>.
            </p>
            <p>
                Catatan harian membantu tim memantau progress kerja dan memastikan
                semua aktivitas tercatat dengan baik. Hanya butuh beberapa menit!
            </p>
            <a href="{{ config('app.url') }}/daily-logs" class="btn">
                Isi Catatan Harian Sekarang →
            </a>
            <hr class="divider">
            <p style="margin:0; font-size:13px; color:#6b7280;">
                Email ini dikirim otomatis setiap hari kerja (Senin&ndash;Sabtu) pukul 16:00 WIB kepada anggota tim yang belum mengisi catatan harian.
            </p>
        </div>
        <div class="footer">
            {{ config('app.name') }} &mdash; {{ now()->year }}
        </div>
    </div>
</body>
</html>
