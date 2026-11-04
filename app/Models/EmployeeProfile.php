<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeProfile extends Model
{
    protected $fillable = [
        'project_id', 'user_id', 'employee_code', 'joining_date', 'department_definition_id',
        'designation_definition_id', 'employee_category_definition_id',
    ];

    protected $casts = ['joining_date' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(EmployeeDefinition::class, 'department_definition_id'); }
    public function designation() { return $this->belongsTo(EmployeeDefinition::class, 'designation_definition_id'); }
    public function category() { return $this->belongsTo(EmployeeDefinition::class, 'employee_category_definition_id'); }
    public function revisions() { return $this->hasMany(EmployeeEmploymentRevision::class)->orderByDesc('effective_from'); }

    public function effectiveRevision(?string $date = null): ?EmployeeEmploymentRevision
    {
        return $this->revisions()->whereDate('effective_from', '<=', $date ?? now()->toDateString())->first();
    }
}
