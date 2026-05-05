<?php
// app/Models/TransmittalLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransmittalLog extends Model
{
    protected $fillable = [
        'transmittal_id',
        'user_id',
        'action',
        'status_from',
        'status_to',
        'note',
    ];

    public function transmittal()
    {
        return $this->belongsTo(Transmittals::class, 'transmittal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}