<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    protected $fillable = ['name', 'opening_hours', 'cash_balance'];

    public function masks()
    {
        return $this->hasMany(Mask::class);
    }

    public function purchaseHistories()
    {
        return $this->hasMany(PurchaseHistory::class);
    }
}
