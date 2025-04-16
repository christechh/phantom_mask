<?php
namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\PurchaseHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MemberController extends Controller
{
    public function topMembers(Request $request)
    {
        $limit     = $request->query('limit', 10);
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');

        $validator = Validator::make($request->all(), [
            'limit'      => 'sometimes|integer|min:1|max:100',
            'start_date' => 'required_with:end_date|date',
            'end_date'   => 'required_with:start_date|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $query = Member::query()
            ->select('members.*')
            ->selectSub(function ($query) use ($startDate, $endDate) {
                $query->from('purchase_histories')
                    ->selectRaw('SUM(amount)')
                    ->whereColumn('purchase_histories.user_id', 'members.id')
                    ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('transaction_date', [$startDate, $endDate]);
                    });
            }, 'total_spent')
            ->has('purchaseHistories')
            ->orderBy('total_spent', 'desc')
            ->limit($limit);

        if ($request->boolean('with_transactions', false)) {
            $query->with(['purchaseHistories' => function ($query) use ($startDate, $endDate) {
                if ($startDate && $endDate) {
                    $query->whereBetween('transaction_date', [$startDate, $endDate]);
                }
                $query->orderBy('transaction_date', 'desc');
            }]);
        }

        $members = $query->get();

        return response()->json([
            'data' => $members,
            'meta' => [
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'limit'         => $limit,
                'total_members' => $members->count(),
            ],
        ]);
    }
}
