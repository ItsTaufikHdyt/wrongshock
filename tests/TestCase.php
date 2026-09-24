<?php

namespace Tests;

use App\Models\User;
use App\Models\WasteBank;
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

        return $bank;
    }
}
