<?php
namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PharmacyController extends Controller
{
    public function getOpenPharmacies(Request $request)
    {
        $request->validate([
            'day'  => 'nullable|string|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'time' => 'nullable|date_format:H:i',
        ]);

        $day  = $request->query('day');
        $time = $request->query('time');

        // 如果沒有提供時間，使用當前時間
        if (! $time) {
            $now  = now();
            $day  = $day ?: $now->format('D');
            $time = $now->format('H:i');
        }

        $pharmacies = Pharmacy::all()->filter(function ($pharmacy) use ($day, $time) {
            return $this->isPharmacyOpen($pharmacy, $day, $time);
        });

        // 轉換 opening_hours 為 JSON
        $formattedPharmacies = $pharmacies->map(function ($pharmacy) {
            return [
                'id'            => $pharmacy->id,
                'name'          => $pharmacy->name,
                'opening_hours' => json_decode($pharmacy->opening_hours),
                'cash_balance'  => $pharmacy->cash_balance,
                'created_at'    => $pharmacy->created_at,
                'updated_at'    => $pharmacy->updated_at,
            ];
        });

        return response()->json($formattedPharmacies);
    }

    protected function isPharmacyOpen(Pharmacy $pharmacy, string $day, string $time): bool
    {
        $openingHours = $this->normalizeOpeningHours($pharmacy->opening_hours);

        if (! is_array($openingHours)) {
            return false;
        }

        if (! isset($openingHours[$day])) {
            return false;
        }

        foreach ($openingHours[$day] as $timeSlot) {
            if (! isset($timeSlot['open']) || ! isset($timeSlot['close'])) {
                continue;
            }

            if ($this->isTimeInRange($time, $timeSlot['open'], $timeSlot['close'])) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeOpeningHours($openingHours): ?array
    {
        if (empty($openingHours)) {
            return [];
        }

        if (is_array($openingHours)) {
            return $openingHours;
        }

        if (is_string($openingHours)) {
            try {
                $decoded = json_decode($openingHours, true, 512, JSON_THROW_ON_ERROR);
                return is_array($decoded) ? $decoded : [];
            } catch (\JsonException $e) {
                \Log::error('open hours failed: ' . $e->getMessage());

                return false;
            }
        }

        return [];
    }
    protected function isTimeInRange(string $time, string $open, string $close): bool
    {
        $time  = Carbon::createFromFormat('H:i', $time);
        $open  = Carbon::createFromFormat('H:i', $open);
        $close = Carbon::createFromFormat('H:i', $close);

        // 處理跨午夜的情況 (如 22:00 - 03:00)
        if ($close->lessThan($open)) {
            return $time->greaterThanOrEqualTo($open) || $time->lessThanOrEqualTo($close);
        }

        return $time->between($open, $close);
    }
    public function getPharmaciesByMaskCount(Request $request)
    {
        $minPrice  = $request->query('min_price');
        $maxPrice  = $request->query('max_price');
        $maskCount = $request->query('mask_count');
        $operator  = $request->query('operator', '>=');

        $validator = Validator::make($request->all(), [
            'min_price'  => 'required|numeric|min:0',
            'max_price'  => 'required|numeric|gt:min_price',
            'mask_count' => 'required|integer|min:1',
            'operator'   => 'sometimes|in:>,>=,<,<=,=',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $query = Pharmacy::query();

        $query->whereHas('masks', function ($query) use ($minPrice, $maxPrice) {
            $query->whereBetween('price', [$minPrice, $maxPrice]);
        }, $operator, $maskCount);

        $query->with(['masks' => function ($query) use ($minPrice, $maxPrice) {
            $query->whereBetween('price', [$minPrice, $maxPrice])
                ->select('id', 'name', 'price', 'pharmacy_id');
        }])->withCount(['masks' => function ($query) use ($minPrice, $maxPrice) {
            $query->whereBetween('price', [$minPrice, $maxPrice]);
        }]);

        $pharmacies = $query->get();

        return response()->json([
            'data' => $pharmacies,
            'meta' => [
                'min_price'        => $minPrice,
                'max_price'        => $maxPrice,
                'mask_count'       => $maskCount,
                'operator'         => $operator,
                'total_pharmacies' => $pharmacies->count(),
            ],
        ]);
    }
}
