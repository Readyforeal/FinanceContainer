<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Reminders for Today</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
        th { background-color: #f4f4f4; }
        .analysis { margin-top: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #4a90e2; }
        .footer { margin-top: 24px; color: #888; font-size: 0.9em; }
    </style>
</head>
<body>
    <h2>Payment Reminders for Today</h2>

    <table>
        <thead>
            <tr>
                <th>Bill</th>
                <th>Suggested Date</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($recommendations as $rec)
            <tr>
                <td>{{ $rec['bill'] }}</td>
                <td>{{ $rec['suggested_pay_date'] }}</td>
                <td>{{ $rec['reason'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="analysis">
        <strong>Analysis:</strong>
        <p>{{ $analysis }}</p>
    </div>

    <p class="footer">— StewardAI</p>
</body>
</html>
