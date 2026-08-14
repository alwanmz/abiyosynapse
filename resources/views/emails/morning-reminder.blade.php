@php
    $statusLabels = [
        'backlog' => 'Backlog',
        'todo' => 'To Do',
        'pending' => 'Menunggu Approval',
        'inprogress' => 'Sedang Dikerjakan',
        'qa-ready' => 'Siap QA',
        'qa-test' => 'QA Test',
        'review' => 'Review',
        'not-appropriate' => 'Belum Sesuai',
        'done' => 'Selesai',
    ];
    $statusColors = [
        'backlog' => '#64748b',
        'todo' => '#64748b',
        'pending' => '#ca8a04',
        'inprogress' => '#2563eb',
        'qa-ready' => '#9333ea',
        'qa-test' => '#ea580c',
        'review' => '#4f46e5',
        'not-appropriate' => '#e11d48',
        'done' => '#16a34a',
    ];
    $shown = $openTickets->take(5);
    $rest = $openTickets->count() - $shown->count();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Pagi</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f4f5; margin: 0; padding: 24px; }
        .wrapper { max-width: 520px; margin: 0 auto; }
        .card { background: #ffffff; border-radius: 12px; padding: 36px 40px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .icon { font-size: 40px; margin-bottom: 16px; }
        h1 { font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 8px; }
        p { font-size: 15px; color: #4b5563; line-height: 1.6; margin: 0 0 16px; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff !important; text-decoration: none; font-size: 14px; font-weight: 600; padding: 12px 24px; border-radius: 8px; margin-top: 4px; }
        .btn-ghost { display: inline-block; background: #f3f4f6; color: #374151 !important; text-decoration: none; font-size: 14px; font-weight: 600; padding: 12px 24px; border-radius: 8px; margin-top: 4px; }
        .divider { border: none; border-top: 1px solid #e5e7eb; margin: 28px 0; }
        .footer { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 20px; }
        .section-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin: 0 0 10px; }
        .ticket { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 14px; margin-bottom: 8px; }
        .ticket-num { font-family: ui-monospace, monospace; font-size: 12px; color: #6b7280; }
        .ticket-title { font-size: 14px; font-weight: 600; color: #111827; margin: 2px 0 6px; }
        .badge { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; color: #ffffff; }
        .empty { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; border-radius: 8px; padding: 14px 16px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="icon">&#9728;&#65039;</div>
            <h1>Selamat pagi, {{ $user->name }}!</h1>
            <p>
                Semangat memulai hari, <strong>{{ now()->locale('id')->isoFormat('dddd, D MMMM Y') }}</strong>.
                Berikut ringkasan yang perlu kamu cek hari ini.
            </p>

            <p class="section-label">Tiket Aktif Kamu</p>
            @if ($openTickets->isEmpty())
                <div class="empty">Tidak ada tiket aktif untuk kamu saat ini. Mantap! &#127881;</div>
            @else
                @foreach ($shown as $ticket)
                    <div class="ticket">
                        <div class="ticket-num">{{ $ticket->ticket_number }}</div>
                        <div class="ticket-title">{{ $ticket->title }}</div>
                        <span class="badge" style="background: {{ $statusColors[$ticket->status] ?? '#64748b' }}">
                            {{ $statusLabels[$ticket->status] ?? $ticket->status }}
                        </span>
                    </div>
                @endforeach
                @if ($rest > 0)
                    <p style="font-size:13px; color:#6b7280; margin:4px 0 0;">
                        + {{ $rest }} tiket lainnya
                    </p>
                @endif
            @endif

            <p style="margin-top:20px;">
                <a href="{{ config('app.url') }}/tickets" class="btn">Buka Tiket Saya &rarr;</a>
            </p>

            <hr class="divider">

            <p class="section-label">Jangan Lupa</p>
            <p>
                Isi <strong>Catatan Harian</strong> untuk mencatat aktivitas &amp; progres kerjamu hari ini.
                Cukup beberapa menit saja.
            </p>
            <p>
                <a href="{{ config('app.url') }}/daily-logs" class="btn-ghost">Buka Catatan Harian &rarr;</a>
            </p>

            <hr class="divider">
            <p style="margin:0; font-size:13px; color:#6b7280;">
                Email ini dikirim otomatis setiap hari kerja (Senin&ndash;Sabtu) pukul 08:00 WIB kepada anggota tim.
            </p>
        </div>
        <div class="footer">
            {{ config('app.name') }} &mdash; {{ now()->year }}
        </div>
    </div>
</body>
</html>
