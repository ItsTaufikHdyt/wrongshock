<?php

namespace Tests;

use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankMember;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function assignDefaultWasteBank(User $user): WasteBank
    {
        $bank = WasteBank::query()->firstOrCreate(
            ['code' => 'BS001'],
            ['name' => 'Test Bank Sampah', 'status' => true]
        );

        $user->wasteBanksAsStaff()->syncWithoutDetaching([$bank->id]);

        if ($user->hasRole('user')) {
            WasteBankMember::query()->firstOrCreate(
                ['waste_bank_id' => $bank->id, 'user_id' => $user->id],
                ['joined_at' => now(), 'status' => 'active']
            );
        }

        return $bank;
    }
}
