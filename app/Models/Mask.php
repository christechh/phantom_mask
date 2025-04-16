<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mask extends Model
{
    protected $fillable = ['pharmacy_id', 'name', 'price', 'quantity'];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function purchaseHistories()
    {
        return $this->hasMany(PurchaseHistory::class);
    }
}
