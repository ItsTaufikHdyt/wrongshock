<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

class WasteBankStaff extends Pivot
{
    protected $table = 'waste_bank_staff';

    public $incrementing = true;

    protected $fillable = [
        'waste_bank_id',
        'user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            $user = User::query()->find($assignment->user_id);
            $bank = WasteBank::query()->find($assignment->waste_bank_id);

            if (! $user?->isBankAdmin() || ! $bank?->status) {
                throw ValidationException::withMessages([
                    'user_id' => 'Pilih Admin Bank Sampah aktif dan bank aktif.',
                ]);
            }

            if ($user->wasteBanks()->where('status', true)->exists()) {
                throw ValidationException::withMessages([
                    'user_id' => 'Admin Bank Sampah hanya boleh memiliki satu bank aktif.',
                ]);
            }
        });
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
