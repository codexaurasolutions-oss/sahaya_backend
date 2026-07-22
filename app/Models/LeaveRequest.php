<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    protected $fillable = [
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'reason',
        'job_id',
        'status',
        'supporting_document',
        'created_by',
        'houseowner_id'
    ];

    protected $appends = ['supporting_document_url'];

    

  public function getSupportingDocumentUrlAttribute()
{
    $value = $this->supporting_document;

    if ($value) {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return env('APP_URL') . '/public/' . $value;
    }

    return null;
}

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
