@extends('errors.layout', [
    'code' => 404,
    'badge' => 'Halaman Tidak Ditemukan',
    'title' => 'Halaman',
    'accent' => 'Tidak Ditemukan',
    'message' => 'Alamat yang Anda tuju tidak tersedia atau sudah dipindahkan. Periksa kembali tautannya, atau kembali ke halaman sebelumnya.',
    'showWalker' => true,
])

@section('actions')
    <button type="button" class="btn btn-ghost" onclick="history.back()">Kembali</button>
    <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
