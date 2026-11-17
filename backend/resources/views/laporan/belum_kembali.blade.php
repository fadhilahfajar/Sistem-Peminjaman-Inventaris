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

    <h2>Bagian 1: Belum kembali ({{ count($aktif) }} baris)</h2>
    <table>
        <thead>
            <tr>
                <th>Kode pinjam</th>
                <th>Peminjam</th>
                <th>Divisi</th>
                <th>Tipe</th>
                <th>Tanggal pinjam</th>
                <th>Batas kembali</th>
                <th>Status</th>
                <th>Hari terlambat</th>
                <th>Barang belum diklaim</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($aktif as $b)
                <tr>
                    <td>{{ $b['kode_pinjam'] }}</td>
                    <td>{{ $b['nama_peminjam'] }}</td>
                    <td>{{ $b['divisi'] }}</td>
                    <td>{{ $b['tipe_peminjam'] }}</td>
                    <td>{{ $b['tanggal_pinjam'] }}</td>
                    <td>{{ $b['batas_kembali'] }}</td>
                    <td>{{ $b['status'] }}</td>
                    <td>{{ $b['hari_terlambat'] }}</td>
                    <td>{{ $b['barang'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Bagian 2: Pemesanan belum dimulai ({{ count($pemesanan) }} baris)</h2>
    <table>
        <thead>
            <tr>
                <th>Kode pinjam</th>
                <th>Peminjam</th>
                <th>Tanggal pinjam</th>
                <th>Batas kembali</th>
                <th>Barang</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pemesanan as $b)
                <tr>
                    <td>{{ $b['kode_pinjam'] }}</td>
                    <td>{{ $b['nama_peminjam'] }}</td>
                    <td>{{ $b['tanggal_pinjam'] }}</td>
                    <td>{{ $b['batas_kembali'] }}</td>
                    <td>{{ $b['barang'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
