<?php

namespace Afterburner\Meetings\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarWeekLayout
{
    /**
     * @param  Collection<int, array{date: string, day: int, inMonth: bool, isToday: bool}>  $weekDays
     * @param  Collection<int, CalendarEntry>  $entries
     * @return array{
     *     bars: array<int, array<string, mixed>>,
     *     days: array<int, array<string, mixed>>,
     *     laneCount: int,
     *     maxTimedLaneCount: int
     * }
     */
    public function layout(Collection $weekDays, Collection $entries): array
    {
        $weekStart = Carbon::parse($weekDays->first()['date'])->startOfDay();
        $weekEnd = Carbon::parse($weekDays->last()['date'])->startOfDay();

        $barEntries = $entries
            ->filter(fn (CalendarEntry $entry) => $entry->showsInAllDayBar())
            ->sortBy(fn (CalendarEntry $entry) => [$entry->startDate(), -$entry->daySpan(), $entry->startsAt])
            ->values();

        $bars = [];
        $laneEnds = [];

        foreach ($barEntries as $entry) {
            $segmentStart = $entry->startDate()->max($weekStart);
            $segmentEnd = $entry->endDate()->min($weekEnd);

            if ($segmentStart->gt($segmentEnd)) {
                continue;
            }

            $startCol = (int) $weekStart->diffInDays($segmentStart);
            $endCol = (int) $weekStart->diffInDays($segmentEnd);
            $lane = $this->firstAvailableLane($laneEnds, $startCol, $endCol);
            $laneEnds[$lane] = $endCol;

            $bars[] = [
                'entry' => $entry,
                'startCol' => $startCol,
                'endCol' => $endCol,
                'lane' => $lane,
                'showLabel' => $segmentStart->eq($entry->startDate()),
                'segmentStart' => $segmentStart->eq($entry->startDate()),
                'segmentEnd' => $segmentEnd->eq($entry->endDate()),
            ];
        }

        $laneCount = $bars === [] ? 0 : max(array_column($bars, 'lane')) + 1;
        $maxTimedLaneCount = 0;

        $days = $weekDays->map(function (array $day) use ($entries, &$maxTimedLaneCount) {
            $date = Carbon::parse($day['date'])->startOfDay();
            $timedEntries = $this->layoutTimedEntries(
                $entries->filter(fn (CalendarEntry $entry) => $entry->showsAsTimedBlock() && $entry->occursOn($date))->values(),
                $date,
            );

            $timedLaneCount = $timedEntries === [] ? 0 : max(array_column($timedEntries, 'lane')) + 1;
            $maxTimedLaneCount = max($maxTimedLaneCount, $timedLaneCount);

            return array_merge($day, [
                'timedEntries' => $timedEntries,
                'timedLaneCount' => $timedLaneCount,
            ]);
        })->all();

        return [
            'bars' => $bars,
            'days' => $days,
            'laneCount' => $laneCount,
            'maxTimedLaneCount' => $maxTimedLaneCount,
        ];
    }

    /**
     * @param  Collection<int, CalendarEntry>  $entries
     * @return array<int, array{entry: CalendarEntry, lane: int, column: int, columnCount: int, timeLabel: string, timeRangeLabel: string}>
     */
    protected function layoutTimedEntries(Collection $entries, Carbon $day): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        $sorted = $entries
            ->sortBy(fn (CalendarEntry $entry) => [$entry->startsAt, $entry->endsAt, $entry->title])
            ->values();

        $clusters = $this->buildOverlapClusters($sorted, $day);
        $placed = [];

        foreach ($clusters as $row => $cluster) {
            foreach ($this->layoutOverlapCluster($cluster, $day) as $item) {
                $item['lane'] = $row;
                $placed[] = $item;
            }
        }

        return $placed;
    }

    /**
     * @param  Collection<int, CalendarEntry>  $entries
     * @return array<int, Collection<int, CalendarEntry>>
     */
    protected function buildOverlapClusters(Collection $entries, Carbon $day): array
    {
        $clusters = [];
        $currentCluster = collect();
        $currentClusterEnd = null;

        foreach ($entries as $entry) {
            $startsAt = $entry->effectiveStartsAtOn($day);
            $endsAt = $entry->effectiveEndsAtOn($day);

            if ($currentCluster->isEmpty() || ($currentClusterEnd !== null && $startsAt->gte($currentClusterEnd))) {
                if ($currentCluster->isNotEmpty()) {
                    $clusters[] = $currentCluster;
                }

                $currentCluster = collect([$entry]);
                $currentClusterEnd = $endsAt;
                continue;
            }

            $currentCluster->push($entry);

            if ($endsAt->gt($currentClusterEnd)) {
                $currentClusterEnd = $endsAt;
            }
        }

        if ($currentCluster->isNotEmpty()) {
            $clusters[] = $currentCluster;
        }

        return $clusters;
    }

    /**
     * @param  Collection<int, CalendarEntry>  $cluster
     * @return array<int, array{entry: CalendarEntry, lane: int, column: int, columnCount: int, timeLabel: string, timeRangeLabel: string}>
     */
    protected function layoutOverlapCluster(Collection $cluster, Carbon $day): array
    {
        $columns = [];
        $placed = [];

        foreach ($cluster as $entry) {
            $startsAt = $entry->effectiveStartsAtOn($day);
            $endsAt = $entry->effectiveEndsAtOn($day);
            $column = 0;

            while (isset($columns[$column]) && $columns[$column]->gt($startsAt)) {
                $column++;
            }

            $columns[$column] = $endsAt;

            $placed[] = [
                'entry' => $entry,
                'lane' => 0,
                'column' => $column,
                'columnCount' => 1,
                'timeLabel' => $entry->timeLabel(),
                'timeRangeLabel' => $entry->timeRangeLabel(),
            ];
        }

        $columnCount = max(1, count($columns));

        return array_map(function (array $item) use ($columnCount) {
            $item['columnCount'] = $columnCount;

            return $item;
        }, $placed);
    }

    /**
     * @param  array<int, int>  $laneEnds
     */
    protected function firstAvailableLane(array &$laneEnds, int $startCol, int $endCol): int
    {
        $lane = 0;

        while (isset($laneEnds[$lane]) && $laneEnds[$lane] >= $startCol) {
            $lane++;
        }

        return $lane;
    }
}
