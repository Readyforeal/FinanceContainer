<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ ucfirst($summary->type) }} Summary</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .period { color: #555; margin-bottom: 16px; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
        th { background-color: #f4f4f4; }
        .section { margin-top: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #4a90e2; }
        .footer { margin-top: 24px; color: #888; font-size: 0.9em; }
    </style>
</head>
<body>
    <h2>{{ ucfirst($summary->type) }} Financial Summary</h2>

    <p class="period">
        Period: {{ $summary->period_start?->toDateString() }}
        @if ($summary->period_end && $summary->period_end->toDateString() !== $summary->period_start?->toDateString())
            to {{ $summary->period_end->toDateString() }}
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Spent</td>
                <td>${{ number_format($summary->total_spent, 2) }}</td>
            </tr>
            <tr>
                <td>Needs</td>
                <td>${{ number_format($summary->needs_spent, 2) }}</td>
            </tr>
            <tr>
                <td>Wants</td>
                <td>${{ number_format($summary->wants_spent, 2) }}</td>
            </tr>
            <tr>
                <td>Savings</td>
                <td>${{ number_format($summary->savings_spent, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($summary->ai_analysis)
    <div class="section">
        <strong>Analysis:</strong>
        <p>{{ $summary->ai_analysis }}</p>
    </div>
    @endif

    @if ($summary->ai_advice)
    <div class="section">
        <strong>Advice:</strong>
        <p>{{ $summary->ai_advice }}</p>
    </div>
    @endif

    <p class="footer">— StewardAI</p>
</body>
</html>
