<?php
namespace App\Http\Controllers;

use App\Models\Mask;
use App\Models\Pharmacy;
use App\Models\PurchaseHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaskController extends Controller
{
    public function getMasksByPharmacy(Request $request, $pharmacyId)
    {
        $sortBy = $request->query('sort_by', 'price');
        $order = $request->query('order', 'DESC');

        return response()->json(Mask::where('pharmacy_id', $pharmacyId)->orderBy($sortBy, $order)->get());
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $type  = $request->input('type', 'both'); // 'pharmacy', 'mask', or 'both'
        $limit = $request->input('limit', 10);

        $validator = validator($request->all(), [
            'query' => 'required|string|min:2',
            'type'  => 'sometimes|in:pharmacy,mask,both',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $results = collect();

        // 搜尋藥局
        if ($type === 'both' || $type === 'pharmacy') {
            $pharmacies = Pharmacy::select('id', 'name', 'cash_balance', DB::raw("'pharmacy' as type"))
                ->selectRaw("MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance", [$query])
                ->whereRaw("MATCH(name) AGAINST(? IN NATURAL LANGUAGE MODE)", [$query])
                ->orderBy('relevance', 'desc')
                ->limit($limit)
                ->get();

            $results = $results->merge($pharmacies);
        }

        // 搜尋口罩
        if ($type === 'both' || $type === 'mask') {
            $masks = Mask::select('masks.id', 'masks.name', 'masks.price', 'pharmacies.name as pharmacy_name', DB::raw("'mask' as type"))
                ->join('pharmacies', 'pharmacies.id', '=', 'masks.pharmacy_id')
                ->selectRaw("MATCH(masks.name) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance", [$query])
                ->whereRaw("MATCH(masks.name) AGAINST(? IN NATURAL LANGUAGE MODE)", [$query])
                ->orderBy('relevance', 'desc')
                ->limit($limit)
                ->get();

            $results = $results->merge($masks);
        }

        // 合併結果
        $sortedResults = $results->sortByDesc('relevance')->values()->take($limit);

        return response()->json([
            'data' => $sortedResults,
            'meta' => [
                'query'         => $query,
                'type'          => $type,
                'total_results' => $sortedResults->count(),
            ],
        ]);
    }

    public function getMaskSalesSummary(Request $request)
    {
        $startDate  = $request->query('start_date');
        $endDate    = $request->query('end_date');
        $maskId     = $request->query('mask_id');
        $pharmacyId = $request->query('pharmacy_id');

        // 驗證輸入參數
        $validator = Validator::make($request->all(), [
            'start_date'  => 'required_with:end_date|date',
            'end_date'    => 'required_with:start_date|date|after_or_equal:start_date',
            'mask_id'     => 'sometimes|integer|exists:masks,id',
            'pharmacy_id' => 'sometimes|integer|exists:pharmacies,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $query = PurchaseHistory::query()
            ->select(
                'purchase_histories.mask_id',
                'masks.name as mask_name',
                'purchase_histories.pharmacy_id',
                'pharmacies.name as pharmacy_name',
                DB::raw('SUM(purchase_histories.amount) as total_amount')
            )
            ->join('masks', 'masks.id', '=', 'purchase_histories.mask_id')
            ->join('pharmacies', 'pharmacies.id', '=', 'purchase_histories.pharmacy_id');

        if ($startDate && $endDate) {
            $query->whereBetween('purchase_histories.transaction_date', [$startDate, $endDate]);
        }

        if ($maskId) {
            $query->where('purchase_histories.mask_id', $maskId);
        }

        if ($pharmacyId) {
            $query->where('purchase_histories.pharmacy_id', $pharmacyId);
        }

        $details = $query->groupBy(
            'purchase_histories.mask_id',
            'masks.name',
            'purchase_histories.pharmacy_id',
            'pharmacies.name'
        )
            ->get();

        $totalAmount = $details->sum('total_amount');

        return response()->json([
            'summary' => [
                'total_amount' => (string) $totalAmount,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
                'pharmacy_id'  => $pharmacyId,
            ],
            'details' => $details,
        ]);
    }
}
