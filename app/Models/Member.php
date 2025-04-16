<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = ['name', 'cash_balance'];

    public function purchaseHistories()
    {
        return $this->hasMany(PurchaseHistory::class, 'user_id');
    }
}
