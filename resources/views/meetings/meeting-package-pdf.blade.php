<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meetingTitle }} — Meeting package</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; line-height: 1.45; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #4f46e5; }
        h2 { font-size: 13px; margin: 20px 0 8px; text-transform: uppercase; letter-spacing: 0.05em; color: #374151; }
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 12px; margin-bottom: 20px; }
        .label { font-weight: bold; color: #374151; }
        .body-text { white-space: pre-wrap; margin-top: 8px; }
        .disclaimer { font-size: 10px; color: #6b7280; margin-top: 24px; padding-top: 8px; border-top: 1px solid #d1d5db; }
        .logo { max-height: 56px; max-width: 140px; }
        table.meta-table { width: 100%; border-collapse: collapse; }
        table.meta-table td { vertical-align: top; padding: 4px 8px 4px 0; }
        ul { margin: 6px 0 0; padding-left: 18px; }
        li { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <table width="100%">
            <tr>
                <td>
                    <p style="font-size:10px;color:#6b7280;margin:0 0 6px;text-transform:uppercase;">Meeting package</p>
                    <h1>{{ $meetingTitle }}</h1>
                    <p style="margin:4px 0 0;color:#4b5563;">{{ $teamName }}</p>
                </td>
                @if ($logoUrl)
                    <td align="right" width="150">
                        <img src="{{ $logoUrl }}" alt="" class="logo">
                    </td>
                @endif
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td width="50%"><span class="label">Meeting type:</span> {{ $meetingTypeLabel }}</td>
            <td><span class="label">Status:</span> {{ $meetingStatusLabel }}</td>
        </tr>
        @if ($scheduledDisplay)
            <tr>
                <td colspan="2"><span class="label">Date &amp; time:</span> {{ $scheduledDisplay }}</td>
            </tr>
        @endif
        @if ($location)
            <tr>
                <td colspan="2"><span class="label">Location:</span> {{ $location }}</td>
            </tr>
        @endif
        @if ($virtualLink)
            <tr>
                <td colspan="2"><span class="label">Virtual link:</span> {{ $virtualLink }}</td>
            </tr>
        @endif
    </table>

    @if ($agendaNotes)
        <h2>Agenda notes</h2>
        <div class="body-text">{{ $agendaNotes }}</div>
    @endif

    @if (count($attendanceSummaryLines) > 0 || count($attendanceRecords) > 0)
        <h2>Attendance</h2>
        @if (count($attendanceSummaryLines) > 0)
            <div class="body-text">{{ implode("\n", $attendanceSummaryLines) }}</div>
        @endif
        @if (count($attendanceRecords) > 0)
            <ul>
                @foreach ($attendanceRecords as $record)
                    <li>{{ $record['label'] }} — {{ $record['status'] }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    @if ($agendaSection)
        <h2>Agenda</h2>
        <div class="body-text">{{ $agendaSection }}</div>
    @endif

    @if ($minutesSection)
        <h2>Minutes</h2>
        @if ($minutesFinalizedDisplay)
            <p><span class="label">Finalized:</span> {{ $minutesFinalizedDisplay }}@if ($minutesFinalizedByName) by {{ $minutesFinalizedByName }}@endif</p>
        @else
            <p style="color:#b45309;"><span class="label">Draft minutes</span> (not finalized)</p>
        @endif
        <div class="body-text">{{ $minutesSection }}</div>
    @endif

    @if ($quorumSection)
        <h2>Quorum</h2>
        <div class="body-text">{{ $quorumSection }}</div>
    @endif

    @if ($resolutionsSection)
        <h2>Resolutions</h2>
        <div class="body-text">{{ $resolutionsSection }}</div>
    @endif

    @if ($actionItemsSection)
        <h2>Action items</h2>
        <div class="body-text">{{ $actionItemsSection }}</div>
    @endif

    @if (count($linkedDocuments) > 0)
        <h2>Linked documents</h2>
        <ul>
            @foreach ($linkedDocuments as $linkedDocument)
                <li>{{ $linkedDocument['name'] }} ({{ $linkedDocument['filename'] }})</li>
            @endforeach
        </ul>
    @endif

    <p class="disclaimer">
        Generated {{ $generatedAt ?? '' }}.
        This package summarizes meeting data at the time it was compiled. It is not legal advice.
    </p>
</body>
</html>
