<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'number',
        'district_id',
        'sub_district_id',
        'email',
        'password',
        'address',
        'image',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'integer',
            'status' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (
                $user->district_id !== null
                && $user->sub_district_id !== null
                && ! SubDistrict::query()
                    ->whereKey($user->sub_district_id)
                    ->where('district_id', $user->district_id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'sub_district_id' => 'The sub-district must belong to the selected district.',
                ]);
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ((int) $this->status !== 1) {
            return false;
        }

        return match ($panel->getId()) {
            'adminPanel' => $this->isPlatformAdmin() || ($this->isBankAdmin() && $this->wasteBanks()->where('status', true)->count() === 1),
            'userPanel' => $this->hasRole('user') && ! $this->hasAnyRole(['admin', 'super_admin']),
            default => false,
        };
    }

    public function isPlatformAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isBankAdmin(): bool
    {
        return $this->hasRole('admin') && ! $this->hasRole('super_admin');
    }

    public function district()
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function subDistrict()
    {
        return $this->belongsTo(SubDistrict::class, 'sub_district_id');
    }

    public function wasteDeposits()
    {
        return $this->hasMany(WasteDeposit::class, 'user_id');
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class, 'user_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class, 'user_id');
    }

    public function wasteBanks()
    {
        return $this->belongsToMany(WasteBank::class, 'waste_bank_staff')->withTimestamps();
    }
}
