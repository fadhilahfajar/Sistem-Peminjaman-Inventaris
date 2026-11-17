<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #222; }
        h1 { font-size: 16pt; margin: 0 0 6mm; }
        p { margin: 0 0 3mm; }
        table { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #999; padding: 2mm; text-align: left; vertical-align: top; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>{{ $judul }}</h1>
    <p>Periode: {{ $periode }}</p>
    <p>Waktu cetak: {{ $waktuCetak }} WIB</p>

    @yield('konten')
</body>
</html>
