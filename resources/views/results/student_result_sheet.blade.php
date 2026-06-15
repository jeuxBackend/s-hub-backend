<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Result Sheet</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 24px;
        }
        h1, h2, h3, p {
            margin: 0;
        }
        .header {
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 20px;
            margin-bottom: 4px;
        }
        .muted {
            color: #6b7280;
        }
        .meta-table,
        .marks-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 4px 8px 4px 0;
            vertical-align: top;
        }
        .term-block {
            margin-top: 22px;
        }
        .term-title {
            margin-bottom: 8px;
            font-size: 15px;
        }
        .marks-table th,
        .marks-table td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: center;
        }
        .marks-table th:first-child,
        .marks-table td:first-child {
            text-align: left;
        }
        .summary {
            margin-top: 12px;
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $institution?->name ?? 'School Result Sheet' }}</h1>
        <p class="muted">Published term: {{ ucfirst($submission->term->value) }} | Issued at: {{ $submission->published_at?->format('Y-m-d H:i') }}</p>
    </div>

    <table class="meta-table">
        <tr>
            <td><strong>Student:</strong> {{ $student->full_name }}</td>
            <td><strong>Registration #:</strong> {{ $student->registration_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Parent:</strong> {{ $guardian?->full_name ?? 'N/A' }}</td>
            <td><strong>Class:</strong> {{ $classroom?->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Term Coverage:</strong> Up to {{ ucfirst($submission->term->value) }}</td>
            <td><strong>Overall Percentage:</strong> {{ $overallPercentage !== null ? number_format($overallPercentage, 2) . '%' : 'N/A' }}</td>
        </tr>
    </table>

    @foreach ($terms as $term)
        <div class="term-block">
            <h2 class="term-title">{{ $term['label'] }}</h2>
            <table class="marks-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        @foreach ($gradeTypes as $type)
                            <th>{{ ucwords(str_replace('_', ' ', $type)) }}</th>
                        @endforeach
                        <th>Total</th>
                        <th>%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($term['subjects'] as $subject)
                        <tr>
                            <td>{{ $subject['subject_name'] }}</td>
                            @foreach ($gradeTypes as $type)
                                @php($cell = $subject['grades'][$type])
                                <td>
                                    @if ($cell)
                                        {{ rtrim(rtrim(number_format($cell['score'], 2, '.', ''), '0'), '.') }}
                                        @if ($cell['total'] !== null)
                                            / {{ rtrim(rtrim(number_format($cell['total'], 2, '.', ''), '0'), '.') }}
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                {{ rtrim(rtrim(number_format($subject['total_score'], 2, '.', ''), '0'), '.') }}
                                @if ($subject['total_max'] > 0)
                                    / {{ rtrim(rtrim(number_format($subject['total_max'], 2, '.', ''), '0'), '.') }}
                                @endif
                            </td>
                            <td>{{ $subject['percentage'] !== null ? number_format($subject['percentage'], 2) . '%' : 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="summary">
                Term Total:
                {{ rtrim(rtrim(number_format($term['total_score'], 2, '.', ''), '0'), '.') }}
                @if ($term['total_max'] > 0)
                    / {{ rtrim(rtrim(number_format($term['total_max'], 2, '.', ''), '0'), '.') }}
                @endif
                | {{ $term['percentage'] !== null ? number_format($term['percentage'], 2) . '%' : 'N/A' }}
            </p>
        </div>
    @endforeach
</body>
</html>
