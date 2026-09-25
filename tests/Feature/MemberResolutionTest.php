<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WasteBank;
use App\Models\WasteBankMember;
use App\Models\WasteBankStaff;
use App\Services\CitizenIdentityService;
use App\Services\DepositService;
use App\Services\MemberResolutionResult;
use App\Services\MemberResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_search_is_current_bank_scoped_and_prioritizes_exact_number(): void
    {
        [$admin, $bank] = $this->adminAndBanks();
        $otherBank = WasteBank::factory()->create();
        $exact = $this->citizen('Taufik Hidayat', $bank);
        $this->citizen('Taufik Hidayat', $bank);
        $other = $this->citizen('Taufik Hidayat', $otherBank);

        $results = app(MemberResolutionService::class)->search($exact->number, $admin);

        $this->assertSame($exact->id, $results->first()->id);
        $this->assertNotContains($other->id, $results->pluck('id')->all());
        $this->assertLessThanOrEqual(50, $results->count());
    }

    public function test_qr_resolution_returns_eligible_member_for_current_bank(): void
    {
        [$admin, $bank] = $this->adminAndBanks();
        $user = $this->citizen('Eligible Citizen', $bank);
        $payload = app(CitizenIdentityService::class)->qrPayload($user);

        $result = app(MemberResolutionService::class)->resolveQr($payload, $admin);

        $this->assertTrue($result->eligible());
        $this->assertSame($user->id, $result->user?->id);
        $this->assertSame($bank->id, $result->membership?->waste_bank_id);
    }

    public function test_same_qr_works_for_multiple_active_memberships_but_not_another_bank(): void
    {
        [$adminA, $bankA, $bankB, $adminB, $bankC] = $this->adminAndThreeBanks();
        $user = $this->citizen('Multi Bank Citizen', $bankA);
        WasteBankMember::query()->create([
            'waste_bank_id' => $bankB->id,
            'user_id' => $user->id,
            'joined_at' => now(),
            'status' => 'active',
        ]);
        $payload = app(CitizenIdentityService::class)->qrPayload($user);

        $this->assertSame(MemberResolutionResult::ELIGIBLE, app(MemberResolutionService::class)->resolveQr($payload, $adminA)->status);
        $this->assertSame(MemberResolutionResult::ELIGIBLE, app(MemberResolutionService::class)->resolveQr($payload, $adminB)->status);

        $adminC = $this->adminFor($bankC);
        $this->assertSame(MemberResolutionResult::MEMBERSHIP_NOT_FOUND, app(MemberResolutionService::class)->resolveQr($payload, $adminC)->status);
    }

    public function test_qr_resolution_distinguishes_inactive_user_membership_and_role(): void
    {
        [$admin, $bank] = $this->adminAndBanks();
        $inactiveMembership = $this->citizen('Inactive Membership', $bank, 'inactive');
        $inactiveUser = $this->citizen('Inactive User', $bank);
        $inactiveUser->forceFill(['status' => 0])->save();
        $adminToken = $this->citizen('Former Citizen Admin', $bank);
        $adminToken->syncRoles(Role::findOrCreate('admin', 'web'));

        $identity = app(CitizenIdentityService::class);
        $this->assertSame(MemberResolutionResult::MEMBERSHIP_INACTIVE, app(MemberResolutionService::class)->resolveQr($identity->qrPayload($inactiveMembership), $admin)->status);
        $this->assertSame(MemberResolutionResult::USER_INACTIVE, app(MemberResolutionService::class)->resolveQr($identity->qrPayload($inactiveUser), $admin)->status);
        $this->assertSame(MemberResolutionResult::NOT_CITIZEN, app(MemberResolutionService::class)->resolveQr($identity->qrPayload($adminToken), $admin)->status);
    }

    public function test_foreign_rotated_and_malformed_payloads_are_rejected(): void
    {
        [$admin, $bank] = $this->adminAndBanks();
        $user = $this->citizen('Rotating Citizen', $bank);
        $identity = app(CitizenIdentityService::class);
        $oldPayload = $identity->qrPayload($user);
        $before = $this->state();

        $this->assertSame(MemberResolutionResult::ELIGIBLE, app(MemberResolutionService::class)->resolveQr($oldPayload, $admin)->status);
        $identity->rotateQrToken($user);

        $this->assertSame(MemberResolutionResult::MEMBER_NOT_FOUND, app(MemberResolutionService::class)->resolveQr($oldPayload, $admin)->status);
        $this->assertSame(MemberResolutionResult::ELIGIBLE, app(MemberResolutionService::class)->resolveQr($identity->qrPayload($user), $admin)->status);
        $this->assertSame($user->number, $user->refresh()->number);
        $this->assertSame($before, $this->state());
        $this->assertSame(MemberResolutionResult::INVALID_QR, app(MemberResolutionService::class)->resolveQr('https://example.test/member/1', $admin)->status);
        $this->assertSame(MemberResolutionResult::INVALID_QR, app(MemberResolutionService::class)->resolveQr('WRG:M:short', $admin)->status);
    }

    public function test_eligible_qr_converges_on_existing_deposit_service_and_bank_account(): void
    {
        [$admin, $bank] = $this->adminAndBanks();
        $user = $this->citizen('Deposit Seam Citizen', $bank);
        $payload = app(CitizenIdentityService::class)->qrPayload($user);
        $itemId = DB::table('waste_items')->insertGetId([
            'category' => 'Seam Item',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 4000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolved = app(MemberResolutionService::class)->resolveQr($payload, $admin);
        $deposit = app(DepositService::class)->post(
            $resolved->user->id,
            now()->toDateString(),
            [['waste_item_id' => $itemId, 'quantity' => '2.500']],
            $admin->id,
            $bank,
        );

        $this->assertTrue($resolved->eligible());
        $this->assertSame($user->id, $deposit->user_id);
        $this->assertSame($bank->id, $deposit->waste_bank_id);
        $this->assertSame(10000, $deposit->total_amount);
        $this->assertDatabaseHas('waste_bank_accounts', ['user_id' => $user->id, 'waste_bank_id' => $bank->id, 'balance' => 10000]);
        $this->assertDatabaseHas('account_ledger_entries', ['user_id' => $user->id, 'waste_bank_id' => $bank->id, 'amount' => 10000]);
    }

    public function test_wrong_bank_qr_and_forged_user_id_cannot_create_financial_state(): void
    {
        [$adminA, $bankA, $bankB, $adminB] = $this->adminAndThreeBanks();
        $user = $this->citizen('Wrong Bank Citizen', $bankA);
        $payload = app(CitizenIdentityService::class)->qrPayload($user);
        $itemId = DB::table('waste_items')->insertGetId([
            'category' => 'Wrong Bank Item',
            'output' => 'Kriya',
            'unit' => 'Kilogram (Kg)',
            'price' => 4000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = $this->state();

        $this->assertSame(MemberResolutionResult::MEMBERSHIP_NOT_FOUND, app(MemberResolutionService::class)->resolveQr($payload, $adminB)->status);

        $this->expectException(ValidationException::class);
        try {
            app(DepositService::class)->post(
                $user->id,
                now()->toDateString(),
                [['waste_item_id' => $itemId, 'quantity' => '1']],
                $adminB->id,
                $bankB,
            );
        } finally {
            $this->assertSame($before, $this->state());
        }
    }

    public function test_inactive_bank_and_malformed_payloads_are_rejected_without_selection(): void
    {
        [$admin, $bank] = $this->adminAndBanks();
        $user = $this->citizen('Hardened QR Citizen', $bank);
        $identity = app(CitizenIdentityService::class);

        $bank->update(['status' => false]);
        $this->assertSame(MemberResolutionResult::BANK_INACTIVE, app(MemberResolutionService::class)->resolveQr($identity->qrPayload($user), $admin)->status);

        $bank->update(['status' => true]);
        foreach ([
            '',
            ' ',
            'random text',
            'https://example.test/pay',
            'WRG:',
            'WRG:M:',
            'WRG:X:'.str_repeat('A', 32),
            'WRG:M:'.str_repeat('A', 32).':extra',
            str_repeat('A', 129),
        ] as $payload) {
            $this->assertSame(MemberResolutionResult::INVALID_QR, app(MemberResolutionService::class)->resolveQr($payload, $admin)->status);
        }

        $this->assertSame(MemberResolutionResult::MEMBER_NOT_FOUND, app(MemberResolutionService::class)->resolveQr('WRG:M:'.str_repeat('A', 32), $admin)->status);
    }

    public function test_manual_lookup_excludes_ineligible_duplicate_names_but_keeps_member_number_as_string(): void
    {
        [$admin, $bank] = $this->adminAndBanks();
        $eligible = $this->citizen('Duplicate Name', $bank);
        $inactiveMembership = $this->citizen('Duplicate Name', $bank, 'inactive');
        $inactiveUser = $this->citizen('Duplicate Name', $bank);
        $inactiveUser->forceFill(['status' => 0])->save();

        $results = app(MemberResolutionService::class)->search('Duplicate Name', $admin);

        $this->assertSame([$eligible->id], $results->pluck('id')->all());
        $this->assertIsString($eligible->refresh()->number);
        $this->assertStringStartsWith('001', $eligible->number);
        $this->assertNotContains($inactiveMembership->id, $results->pluck('id')->all());
        $this->assertNotContains($inactiveUser->id, $results->pluck('id')->all());
    }

    public function test_http_resolver_returns_minimal_member_data_and_uses_authenticated_bank_context(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        [$admin, $bank] = $this->adminAndBanks();
        $user = $this->citizen('HTTP Citizen', $bank);
        $payload = app(CitizenIdentityService::class)->qrPayload($user);
        $before = $this->state();

        $response = $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->actingAs($admin)->postJson(route('admin.member.resolve-qr'), [
                'payload' => $payload,
                'bank_id' => 999999,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'ELIGIBLE')
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('member.id', $user->id)
            ->assertJsonPath('member.name', $user->name)
            ->assertJsonPath('member.number', $user->number)
            ->assertJsonMissingPath('member.email');
        $this->assertSame($before, $this->state());
    }

    public function test_http_resolver_rejects_citizen_and_malformed_requests(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        [$admin, $bank] = $this->adminAndBanks();
        $user = $this->citizen('HTTP Citizen', $bank);
        $payload = app(CitizenIdentityService::class)->qrPayload($user);

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->postJson(route('admin.member.resolve-qr'), ['payload' => $payload])->assertUnauthorized();
        $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->actingAs($user)->postJson(route('admin.member.resolve-qr'), ['payload' => $payload])->assertForbidden();
        $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->actingAs($admin)->postJson(route('admin.member.resolve-qr'), ['payload' => 'foreign'])->assertUnprocessable();
    }

    /** @return array{0: User, 1: WasteBank, 2?: WasteBank} */
    private function adminAndBanks(): array
    {
        $bank = WasteBank::factory()->create();
        $admin = $this->adminFor($bank);

        return [$admin, $bank];
    }

    /** @return array{0: User, 1: WasteBank, 2: WasteBank, 3: User, 4: WasteBank} */
    private function adminAndThreeBanks(): array
    {
        $bankA = WasteBank::factory()->create();
        $bankB = WasteBank::factory()->create();
        $bankC = WasteBank::factory()->create();

        return [$this->adminFor($bankA), $bankA, $bankB, $this->adminFor($bankB), $bankC];
    }

    private function adminFor(WasteBank $bank): User
    {
        $ids = $this->location();
        $admin = User::query()->create([
            'name' => 'Bank Admin '.Str::random(6),
            'number' => 'ADMIN-'.Str::random(8),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => 'password',
            'district_id' => $ids[0],
            'sub_district_id' => $ids[1],
            'status' => 1,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        WasteBankStaff::query()->create(['waste_bank_id' => $bank->id, 'user_id' => $admin->id]);

        return $admin->refresh();
    }

    private function citizen(string $name, WasteBank $bank, string $membershipStatus = 'active'): User
    {
        $ids = $this->location();
        $user = app(CitizenIdentityService::class)->createCitizen([
            'name' => $name,
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => 'password',
            'district_id' => $ids[0],
            'sub_district_id' => $ids[1],
            'address' => 'Test address',
            'status' => 1,
            'balance' => 0,
        ]);
        WasteBankMember::query()->create([
            'waste_bank_id' => $bank->id,
            'user_id' => $user->id,
            'joined_at' => now(),
            'status' => $membershipStatus,
        ]);

        return $user->refresh();
    }

    /** @return array{0: int, 1: int} */
    private function location(): array
    {
        $district = DB::table('districts')->insertGetId([
            'name' => 'Resolution District '.Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrict = DB::table('sub_districts')->insertGetId([
            'district_id' => $district,
            'name' => 'Resolution Subdistrict '.Str::random(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$district, $subDistrict];
    }

    /** @return array<string, int> */
    private function state(): array
    {
        return [
            'users' => User::query()->count(),
            'memberships' => DB::table('waste_bank_members')->count(),
            'accounts' => DB::table('waste_bank_accounts')->count(),
            'ledger' => DB::table('account_ledger_entries')->count(),
            'deposits' => DB::table('waste_deposits')->count(),
            'withdrawals' => DB::table('withdrawals')->count(),
        ];
    }
}
