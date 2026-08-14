@extends('errors.layout', [
    'badge' => 'Sedang Pemeliharaan',
    'title' => 'Lagi',
    'accent' => 'Naik Level',
    'message' => 'Sistem sedang dalam pemeliharaan untuk peningkatan performa & fitur. Tenang, logonya lagi jalan-jalan dulu — sebentar lagi kami kembali.',
    'showWalker' => true,
])

{{-- Maintenance is the one page that should keep retrying on its own. --}}
@section('head')
    <meta http-equiv="refresh" content="30">
@endsection

@section('title_suffix', 'Dulu')

@section('extra')
    <div class="dots"><span></span><span></span><span></span></div>
@endsection
