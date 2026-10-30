<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Report Book</title>
    <style>
        @page {
            margin: 28px 34px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #111827;
        }
        h1, h2, p {
            margin: 0;
        }
        .page {
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header td {
            vertical-align: middle;
            text-align: center;
        }
        .header .logo-cell {
            width: 80px;
        }
        .header img {
            max-width: 72px;
            max-height: 72px;
        }
        .school-name {
            font-size: 17px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .report-title {
            margin-top: 4px;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .muted {
            color: #6b7280;
            font-size: 10px;
        }
        .info {
            margin-top: 16px;
        }
        .info td {
            width: 50%;
            padding: 6px 8px 6px 0;
            vertical-align: bottom;
        }
        .label {
            font-weight: bold;
        }
        .value {
            display: inline-block;
            min-width: 90px;
            border-bottom: 1px dotted #111827;
            padding: 0 4px;
        }
        .marks {
            margin-top: 14px;
        }
        .marks th,
        .marks td {
            border: 1px solid #111827;
            padding: 6px 7px;
            text-align: center;
        }
        .marks th {
            background: #f3f4f6;
        }
        .marks td.subject,
        .marks th.subject {
            text-align: left;
            width: 34%;
        }
        .marks tr.summary td {
            font-weight: bold;
        }
        .remarks {
            margin-top: 16px;
        }
        .remarks .line {
            margin-top: 10px;
            min-height: 16px;
            border-bottom: 1px dotted #111827;
            line-height: 1.6;
        }
        .signature-row {
            margin-top: 22px;
        }
        .footer {
            margin-top: 46px;
        }
        .footer td {
            width: 50%;
            vertical-align: bottom;
        }
        .footer .right {
            text-align: right;
        }
        .footer img {
            max-height: 70px;
            max-width: 150px;
        }
        .footer .caption {
            display: inline-block;
            min-width: 150px;
            border-top: 1px solid #111827;
            padding-top: 4px;
            text-align: center;
            font-size: 11px;
        }
    </style>
</head>
@php
    $fmt = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    $ordinal = function (?int $n) {
        if ($n === null) {
            return '';
        }
        $suffix = ($n % 100 >= 11 && $n % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'][$n % 10]);

        return $n . $suffix;
    };
@endphp
<body>
    @foreach ($terms as $term)
        @php($remark = $term['remark'])
        <div class="page">
            <table class="header">
                <tr>
                    <td class="logo-cell">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="Logo">
                        @endif
                    </td>
                    <td>
                        <p class="school-name">{{ $institution?->name ?? 'School' }}</p>
                        @if ($institution?->slogan)
                            <p class="muted">{{ $institution->slogan }}</p>
                        @endif
                        <p class="report-title">STUDENT REPORT BOOK</p>
                    </td>
                    <td class="logo-cell"></td>
                </tr>
            </table>

            <table class="info">
                <tr>
                    <td><span class="label">Pupil's Name:</span> <span class="value">{{ $student->full_name }}</span></td>
                    <td><span class="label">Grade:</span> <span class="value">{{ $classroom?->name }}</span></td>
                </tr>
                <tr>
                    <td><span class="label">Possible Attendance:</span> <span class="value">{{ $remark?->possible_attendance }}</span></td>
                    <td><span class="label">Conduct:</span> <span class="value">{{ $remark?->conduct }}</span></td>
                </tr>
                <tr>
                    <td><span class="label">Time Absent:</span> <span class="value">{{ $remark?->times_absent }}</span></td>
                    <td><span class="label">Position:</span> <span class="value">{{ $ordinal($term['position']) }}</span></td>
                </tr>
                <tr>
                    <td><span class="label">Time Late:</span> <span class="value">{{ $remark?->times_late }}</span></td>
                    <td><span class="label">Term:</span> <span class="value">{{ ucfirst($term['term']) }}</span></td>
                </tr>
                <tr>
                    <td><span class="label">No. in Class:</span> <span class="value">{{ $term['class_size'] }}</span></td>
                    <td><span class="label">Academic Year:</span> <span class="value">{{ $institution?->academic_year }}</span></td>
                </tr>
            </table>

            <table class="marks">
                <thead>
                    <tr>
                        <th class="subject">SUBJECT</th>
                        @foreach ($columns as $label)
                            <th>{{ strtoupper($label) }}</th>
                        @endforeach
                        <th>TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($term['subjects'] as $subject)
                        <tr>
                            <td class="subject">{{ $subject['subject_name'] }}</td>
                            @foreach ($columns as $type => $label)
                                <td>{{ $subject['cells'][$type] ? $fmt($subject['cells'][$type]['score']) : '' }}</td>
                            @endforeach
                            <td>{{ $fmt($subject['total_score']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="summary">
                        <td class="subject">Total</td>
                        @foreach ($columns as $type => $label)
                            <td>{{ $fmt($term['column_totals'][$type]) }}</td>
                        @endforeach
                        <td>{{ $fmt($term['total_score']) }}</td>
                    </tr>
                    <tr class="summary">
                        <td class="subject">Average</td>
                        @foreach ($columns as $type => $label)
                            <td>{{ $fmt($term['column_averages'][$type]) }}</td>
                        @endforeach
                        <td>{{ $fmt($term['total_average']) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="remarks">
                <p><span class="label">Teacher's Remarks:</span></p>
                <div class="line">{{ $remark?->teacher_remarks }}</div>

                <p style="margin-top: 14px;"><span class="label">Head Teacher's Remarks:</span></p>
                <div class="line">{{ $remark?->head_teacher_remarks }}</div>

                <p style="margin-top: 14px;">
                    <span class="label">Next Term Begins:</span>
                    <span class="value">{{ $remark?->next_term_begins?->format('d M Y') }}</span>
                </p>

                <p class="signature-row">
                    <span class="label">Parent's Signature:</span>
                    <span class="value" style="min-width: 220px;">&nbsp;</span>
                </p>
            </div>

            <table class="footer">
                <tr>
                    <td>
                        @if ($signature)
                            <img src="{{ $signature }}" alt="Principal signature"><br>
                        @endif
                        <span class="caption">Principal's Signature</span>
                    </td>
                    <td class="right">
                        @if ($stamp)
                            <img src="{{ $stamp }}" alt="Institute stamp"><br>
                        @endif
                        <span class="caption">Institute Stamp</span>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
