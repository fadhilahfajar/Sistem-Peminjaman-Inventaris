@extends('laporan.dasar')

@section('konten')
    <p>Dicetak oleh: {{ $dicetakOleh }}</p>

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
                <th>Kode klaim</th>
                <th>Kode pinjam</th>
                <th>Peminjam</th>
                <th>Diklaim pada</th>
                <th>Hari terlambat</th>
                <th>Status</th>
                <th>Hasil per barang</th>
                <th>Catatan peminjam</th>
                <th>Catatan admin</th>
                <th>Diverifikasi oleh</th>
                <th>Diverifikasi pada</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($baris as $b)
                <tr>
                    <td>{{ $b['kode_klaim'] }}</td>
                    <td>{{ $b['kode_pinjam'] }}</td>
                    <td>{{ $b['nama_peminjam'] }}</td>
                    <td>{{ $b['diklaim_pada'] }}</td>
                    <td>{{ $b['hari_terlambat'] }}</td>
                    <td>{{ $b['status'] }}</td>
                    <td>{{ $b['hasil'] }}</td>
                    <td>{{ $b['catatan_peminjam'] }}</td>
                    <td>{{ $b['catatan_admin'] }}</td>
                    <td>{{ $b['diverifikasi_oleh'] }}</td>
                    <td>{{ $b['diverifikasi_pada'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection