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

    <h2>Ringkasan per status</h2>
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ringkasan as $status => $jumlah)
                <tr>
                    <td>{{ $status }}</td>
                    <td>{{ $jumlah }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Daftar peminjaman ({{ count($baris) }} baris)</h2>
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
                <th>Barang</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($baris as $b)
                <tr>
                    <td>{{ $b['kode_pinjam'] }}</td>
                    <td>{{ $b['nama_peminjam'] }}</td>
                    <td>{{ $b['divisi'] }}</td>
                    <td>{{ $b['tipe_peminjam'] }}</td>
                    <td>{{ $b['tanggal_pinjam'] }}</td>
                    <td>{{ $b['batas_kembali'] }}</td>
                    <td>
                        {{ $b['status'] }}
                        @if ($b['status'] === 'terlambat')
                            ({{ $b['hari_terlambat'] }} hari)
                        @endif
                    </td>
                    <td>{{ $b['barang'] }}</td>
                    <td>{{ $b['ada_catatan'] ? 'Ya' : 'Tidak' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
