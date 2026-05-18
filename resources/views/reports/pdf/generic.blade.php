<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; color: #0f172a; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #0f172a; padding-bottom: 10px; }
        .header h1 { margin: 0; text-transform: uppercase; font-size: 18px; }
        .meta { margin-bottom: 20px; font-size: 10px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #0f172a; color: white; text-align: left; padding: 10px; text-transform: uppercase; font-size: 10px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="meta">{{ $subtitle }} | Generated: {{ now()->format('Y-m-d H:i:s') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($visibleColumns as $key => $label)
                <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr>
                @foreach($visibleColumns as $key => $label)
                <td>{{ is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? '') }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 10px; text-align: center; font-size: 9px; color: #94a3b8;">
        This is an automatically generated operational audit report. Confidential - Restricted Access.
    </div>
</body>
</html>
