<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    //
    protected $fillable = [
        'customer',
        'receiver',
        'type',
        'quantity',
        'cost',
        'pay_method',
        'pay_key',
        'status',
        'msg_id',
    ];
}
