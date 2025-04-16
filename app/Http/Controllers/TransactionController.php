<?php
namespace App\Http\Controllers;

use App\Models\Mask;
use App\Models\Pharmacy;
use App\Models\PurchaseHistory;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    public function purchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'     => 'required|integer|exists:members,id',
            'pharmacy_id' => 'required|integer|exists:pharmacies,id',
            'mask_id'     => 'required|integer|exists:masks,id',
            // 'quantity'    => 'required|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $userId     = $request->input('user_id');
        $pharmacyId = $request->input('pharmacy_id');
        $maskId     = $request->input('mask_id');
        // $quantity   = $request->input('quantity');

        DB::beginTransaction();

        try {
            // 防止併發問題
            $user     = Member::lockForUpdate()->findOrFail($userId);
            $pharmacy = Pharmacy::lockForUpdate()->findOrFail($pharmacyId);
            $mask     = Mask::lockForUpdate()->findOrFail($maskId);

            // 檢查庫存（假設未來有庫存）
            // if ($mask->quantity < $quantity) {
            //     DB::rollBack();
            //     return response()->json([
            //         'error'     => 'Insufficient mask quantity',
            //         'available' => $mask->quantity,
            //     ], 400);
            // }

            // 總金額
            $totalAmount = round($mask->price, 2);

            // 檢查用戶餘額
            if ($user->cash_balance < $totalAmount) {
                DB::rollBack();
                return response()->json([
                    'error'           => 'Insufficient user balance',
                    'required'        => $totalAmount,
                    'current_balance' => $user->cash_balance,
                ], 400);
            }

            // 更新數據
            // $mask->decrement('quantity', $quantity);
            $user->decrement('cash_balance', $totalAmount);
            $pharmacy->increment('cash_balance', $totalAmount);

            // 記錄交易歷史
            $transaction = PurchaseHistory::create([
                'user_id'            => $userId,
                'pharmacy_id'        => $pharmacyId,
                'mask_id'            => $maskId,
                // 'quantity'           => $quantity,
                'unit_price'         => $mask->price,
                'amount' => $totalAmount,
                'transaction_date'   => now(),
            ]);

            DB::commit();

            // 返回詳細交易結果
            return response()->json([
                'message'              => 'Purchase successful',
                'transaction_id'       => $transaction->id,
                'mask_name'            => $mask->name,
                'pharmacy_name'        => $pharmacy->name,
                // 'quantity'             => $quantity,
                'total_amount'         => $totalAmount,
                'user_new_balance'     => $user->cash_balance,
                'pharmacy_new_balance' => $pharmacy->cash_balance,
                // 'mask_new_quantity'    => $mask->quantity,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Purchase failed: ' . $e->getMessage(), [
                'user_id'     => $userId,
                'pharmacy_id' => $pharmacyId,
                'mask_id'     => $maskId,
                // 'quantity'    => $quantity,
                'exception'   => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error'   => 'Purchase processing failed',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
