<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseHistory extends Model
{
    protected $fillable = ['user_id', 'pharmacy_id', 'mask_id', 'amount', 'transaction_date'];
}
