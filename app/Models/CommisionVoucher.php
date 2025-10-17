<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class CommisionVoucher extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'commision_vouchers';

    protected $fillable = [
        'voucher_number',
        'reference',
        'description',
        'date',
        'total_debit',
        'total_credit',
        'project_id',
        'created_by',
        'updated_by',
        'status',
    ];

    /**
     * Define the relationship with JournalVoucherDetail.
     */
    public function details()
    {
        return $this->hasMany(CommisionVoucherDetail::class, 'commision_voucher_id');
    }
}
