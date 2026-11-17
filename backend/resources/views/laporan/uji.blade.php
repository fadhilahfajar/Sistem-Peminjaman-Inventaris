@extends('laporan.dasar')

@section('konten')
    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Catatan</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($baris as $item)
                <tr>
                    <td>{{ $item['nama'] }}</td>
                    <td>{{ $item['catatan'] }}</td>
                    <td>{{ $item['jumlah'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
