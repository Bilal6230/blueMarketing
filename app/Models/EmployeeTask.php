<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['project_id','assigned_to','created_by','title','description','priority','status',
        'start_date','due_date','completed_at','completion_note'];
    protected $casts = ['start_date'=>'date','due_date'=>'date','completed_at'=>'datetime'];
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to')->withTrashed(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by')->withTrashed(); }
    public function project() { return $this->belongsTo(Project::class); }
}
