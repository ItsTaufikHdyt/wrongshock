<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class WasteBank extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'district_id',
        'sub_district_id',
        'address',
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $bank): void {
            if (filled($bank->code)) {
                $bank->code = strtoupper(trim($bank->code));
            }

            if (
                $bank->district_id !== null
                && $bank->sub_district_id !== null
                && ! SubDistrict::query()
                    ->whereKey($bank->sub_district_id)
                    ->where('district_id', $bank->district_id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'sub_district_id' => 'The sub-district must belong to the selected district.',
                ]);
            }
        });
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function subDistrict()
    {
        return $this->belongsTo(SubDistrict::class);
    }

    public function staff()
    {
        return $this->belongsToMany(User::class, 'waste_bank_staff')->withTimestamps();
    }

    public function staffAssignments()
    {
        return $this->hasMany(WasteBankStaff::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'waste_bank_members')
            ->withPivot(['joined_at', 'status'])
            ->withTimestamps();
    }

    public function memberMemberships()
    {
        return $this->hasMany(WasteBankMember::class);
    }

    public function deposits()
    {
        return $this->hasMany(WasteDeposit::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }
}
