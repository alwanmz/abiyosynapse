{{-- No walker here: a real system failure should not read as playful. --}}
@extends('errors.layout', [
    'code' => 500,
    'badge' => 'Kesalahan Sistem',
    'title' => 'Terjadi',
    'accent' => 'Kesalahan',
    'message' => 'Sistem mengalami gangguan saat memproses permintaan Anda. Tim kami sudah menerima laporannya. Silakan coba beberapa saat lagi atau hubungi administrator.',
    'showWalker' => false,
])

@section('actions')
    <button type="button" class="btn btn-ghost" onclick="location.reload()">Coba Lagi</button>
    <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
