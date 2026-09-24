<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WasteBankMember extends Model
{
    protected $fillable = [
        'waste_bank_id',
        'user_id',
        'joined_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wasteBank()
    {
        return $this->belongsTo(WasteBank::class);
    }
}
