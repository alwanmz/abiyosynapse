@extends('errors.layout', [
    'code' => 429,
    'badge' => 'Terlalu Banyak Permintaan',
    'title' => 'Mohon',
    'accent' => 'Tunggu Sebentar',
    'message' => 'Permintaan dari perangkat Anda terlalu sering dalam waktu singkat. Tunggu beberapa saat, lalu coba lagi.',
    'showWalker' => true,
])

@section('actions')
    <button type="button" class="btn btn-primary" onclick="location.reload()">Coba Lagi</button>
@endsection
