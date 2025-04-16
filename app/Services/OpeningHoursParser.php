<?php
namespace App\Services;

class OpeningHoursParser
{
    public function parse(string $hoursString): array
    {
        $parsedHours = [];

        // 處理不同分隔符號的情況
        $sections = preg_split('/\s*\/\s*/', $hoursString);

        foreach ($sections as $section) {
            $this->parseSection($section, $parsedHours);
        }

        return $parsedHours;
    }

    protected function parseSection(string $section, array &$parsedHours): void
    {
        // 處理 "Mon, Wed, Fri 08:00 - 12:00" 或 "Mon - Fri 08:00 - 17:00" 格式
        if (preg_match('/^([a-zA-Z,\s-]+)\s+(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})$/', $section, $matches)) {
            $daysPart  = trim($matches[1]);
            $openTime  = $matches[2];
            $closeTime = $matches[3];

            $days = $this->parseDays($daysPart);

            foreach ($days as $day) {
                $parsedHours[$day][] = [
                    'open'  => $openTime,
                    'close' => $closeTime,
                ];
            }
        }
    }

    protected function parseDays(string $daysPart): array
    {
        // 處理 "Mon, Wed, Fri" 格式
        if (strpos($daysPart, ',') !== false) {
            $days = array_map('trim', explode(',', $daysPart));
            return $this->normalizeDayNames($days);
        }

        // 處理 "Mon - Fri" 格式
        if (preg_match('/^([a-zA-Z]{3})\s*-\s*([a-zA-Z]{3})$/', $daysPart, $matches)) {
            $startDay = $matches[1];
            $endDay   = $matches[2];

            $allDays    = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $startIndex = array_search($startDay, $allDays);
            $endIndex   = array_search($endDay, $allDays);

            if ($startIndex !== false && $endIndex !== false) {
                if ($startIndex <= $endIndex) {
                    return array_slice($allDays, $startIndex, $endIndex - $startIndex + 1);
                } else {
                    // 處理跨週末的情況
                    return array_merge(
                        array_slice($allDays, $startIndex),
                        array_slice($allDays, 0, $endIndex + 1)
                    );
                }
            }
        }

        return $this->normalizeDayNames([$daysPart]);
    }

    protected function normalizeDayNames(array $days): array
    {
        $standardDays = [
            'Mon' => 'Mon', 'Monday'    => 'Mon',
            'Tue' => 'Tue', 'Tuesday'   => 'Tue',
            'Wed' => 'Wed', 'Wednesday' => 'Wed',
            'Thu' => 'Thu', 'Thursday'  => 'Thu',
            'Fri' => 'Fri', 'Friday'    => 'Fri',
            'Sat' => 'Sat', 'Saturday'  => 'Sat',
            'Sun' => 'Sun', 'Sunday'    => 'Sun',
        ];

        return array_map(function ($day) use ($standardDays) {
            return $standardDays[$day] ?? $day;
        }, $days);
    }
}
