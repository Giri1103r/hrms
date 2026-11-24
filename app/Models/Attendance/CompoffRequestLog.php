<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

/**
 * History of every action taken on a compensatory off request.
 */
class CompoffRequestLog extends Model
{
    protected $table = 'compoff_request_log';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'action',
        'remarks',
        'action_by',
        'created_at',
    ];

    public const LABELS = [
        'created' => 'Request Raised',
        'updated' => 'Request Updated',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'direct_approved' => 'Direct Approval (Manager)',
    ];

    public static function record(int $requestId, string $action, ?string $remarks = null): self
    {
        return static::create([
            'request_id' => $requestId,
            'action' => $action,
            'remarks' => $remarks,
            'action_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
