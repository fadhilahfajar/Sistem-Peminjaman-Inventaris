@extends('laporan.dasar')

@section('konten')
    <p>Dicetak oleh: {{ $dicetakOleh }}</p>
    <p>Posisi stok per {{ $posisiStok }} WIB</p>

    @if ($filterLabel === [])
        <p>Filter: Semua</p>
    @else
        <p>Filter:
            @foreach ($filterLabel as $f)
                {{ $f['label'] }}: {{ $f['nilai'] }}@if (! $loop->last); @endif
            @endforeach
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Barang</th>
                <th>Total</th>
                <th>Tersedia</th>
                <th>Dipinjam</th>
                <th>Menunggu verifikasi</th>
                <th>Rusak</th>
                <th>Kondisi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($kelompok as $kel)
                <tr>
                    <th colspan="7">{{ $kel['kategori'] }}</th>
                </tr>
                @foreach ($kel['baris'] as $b)
                    <tr>
                        <td>{{ $b['nama'] }}</td>
                        <td>{{ $b['total'] }}</td>
                        <td>{{ $b['tersedia'] }}</td>
                        <td>{{ $b['dipinjam'] }}</td>
                        <td>{{ $b['menunggu_verifikasi'] }}</td>
                        <td>{{ $b['rusak'] }}</td>
                        <td>{{ $b['kondisi'] }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Subtotal {{ $kel['kategori'] }}</strong></td>
                    <td><strong>{{ $kel['subtotal']['total'] }}</strong></td>
                    <td><strong>{{ $kel['subtotal']['tersedia'] }}</strong></td>
                    <td><strong>{{ $kel['subtotal']['dipinjam'] }}</strong></td>
                    <td><strong>{{ $kel['subtotal']['menunggu_verifikasi'] }}</strong></td>
                    <td><strong>{{ $kel['subtotal']['rusak'] }}</strong></td>
                    <td></td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Total</strong></td>
                <td><strong>{{ $total['total'] }}</strong></td>
                <td><strong>{{ $total['tersedia'] }}</strong></td>
                <td><strong>{{ $total['dipinjam'] }}</strong></td>
                <td><strong>{{ $total['menunggu_verifikasi'] }}</strong></td>
                <td><strong>{{ $total['rusak'] }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endsection