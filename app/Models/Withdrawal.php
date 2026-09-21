<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $table = 'withdrawals';

    protected $fillable = [
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'withdrawal_date' => 'date',
            'requested_date' => 'date',
            'processed_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $withdrawal): void {
            throw new \LogicException('Withdrawal history cannot be deleted.');
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
