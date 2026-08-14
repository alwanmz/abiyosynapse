@extends('errors.layout', [
    'code' => 403,
    'badge' => 'Akses Ditolak',
    'title' => 'Akses',
    'accent' => 'Tidak Diizinkan',
    'message' => 'Akun Anda tidak memiliki hak akses ke halaman ini. Hubungi administrator apabila Anda merasa seharusnya memiliki akses.',
    'showWalker' => true,
])

@section('actions')
    <button type="button" class="btn btn-ghost" onclick="history.back()">Kembali</button>
    <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
