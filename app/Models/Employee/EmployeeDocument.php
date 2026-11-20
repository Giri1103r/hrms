<?php

namespace App\Models\Employee;

use App\Scopes\TrashScope;
use App\Models\Employee\Master\Document;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $table = 'masters_employee_document';
    protected $primaryKey = 'id';

    protected $fillable = [
        'employee_id',
        'document_type_id',
        'file_orgname',
        'file_name',
        'file_path',
        'file_size',
        'file_extension',
        'status',
        'trash',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $attributes = [
        'status' => 1,
        'trash' => 'NO',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_type_id', 'id');
    }

    protected static function booted()
    {
        static::addGlobalScope(new TrashScope('masters_employee_document'));
    }
}
