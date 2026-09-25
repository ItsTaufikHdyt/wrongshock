<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WasteBankAccount extends Model
{
    protected $table = 'waste_bank_accounts';

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::saving(function (self $account): void {
            if ((int) $account->balance < 0) {
                throw new \LogicException('Waste Bank account balance cannot be negative.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
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
