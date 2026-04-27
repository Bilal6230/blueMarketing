<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Site;
use App\Models\User;

class JournalVoucher extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'journal_vouchers';

    protected $fillable = [
        'voucher_number',
        'type',
        'reference',
        'description',
        'date',
        'total_debit',
        'total_credit',
        'project_id',
        'site_id',
        'created_by',
        'updated_by',
        'status',
    ];

    /**
     * Define the relationship with JournalVoucherDetail.
     */
    public function details()
    {
        return $this->hasMany(JournalVoucherDetail::class, 'journal_voucher_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
