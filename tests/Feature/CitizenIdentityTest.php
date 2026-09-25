<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WasteBank;
use App\Services\BankMembershipService;
use App\Services\CitizenIdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CitizenIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_creation_assigns_a_stable_number_and_qr_token_without_membership(): void
    {
        $identity = app(CitizenIdentityService::class);
        $attributes = $this->citizenAttributes();
        $user = $identity->createCitizen($attributes);

        $this->assertSame('user', $user->getRoleNames()->first());
        $this->assertMatchesRegularExpression('/^001\d{2}\d{2}\d{4}\d{4}$/', $user->number);
        $this->assertNotSame((string) $user->id, $user->qr_token);
        $this->assertSame($user->qr_token, $identity->ensureQrToken($user));
        $this->assertDatabaseCount('waste_bank_members', 0);
    }

    public function test_member_number_collision_retries_the_actual_insert(): void
    {
        $attributes = $this->citizenAttributes();
        $collision = app(CitizenIdentityService::class)->generateMemberNumber(
            $attributes['district_id'],
            $attributes['sub_district_id'],
        );
        User::query()->create([
            ...$attributes,
            'email' => 'collision-'.Str::random(8).'@example.test',
            'number' => $collision,
        ]);

        $identity = \Mockery::mock(CitizenIdentityService::class)->makePartial();
        $identity->shouldReceive('generateMemberNumber')->twice()->andReturn($collision, '00101012026'.Str::random(4));

        $user = $identity->createCitizen($attributes);

        $this->assertNotSame($collision, $user->number);
    }

    public function test_member_number_collision_retry_is_bounded(): void
    {
        $attributes = $this->citizenAttributes();
        $collision = app(CitizenIdentityService::class)->generateMemberNumber(
            $attributes['district_id'],
            $attributes['sub_district_id'],
        );
        User::query()->create([
            ...$attributes,
            'email' => 'collision-'.Str::random(8).'@example.test',
            'number' => $collision,
        ]);

        $identity = \Mockery::mock(CitizenIdentityService::class)->makePartial();
        $identity->shouldReceive('generateMemberNumber')->times(5)->andReturn($collision);

        $this->expectException(QueryException::class);
        $identity->createCitizen($attributes);
    }

    public function test_member_creation_uses_one_identity_for_multiple_memberships(): void
    {
        $actor = $this->createSuperAdmin();
        $banks = WasteBank::factory()->count(2)->create();
        $user = app(BankMembershipService::class)->createMember($actor, [
            ...$this->citizenAttributes(),
            'waste_bank_id' => $banks[0]->id,
        ]);
        $number = $user->number;
        $token = $user->qr_token;

        app(BankMembershipService::class)->addMember($actor, $user, $banks[1]);

        $this->assertSame($number, $user->refresh()->number);
        $this->assertSame($token, $user->qr_token);
        $this->assertDatabaseCount('waste_bank_members', 2);
    }

    public function test_qr_rotation_changes_only_the_qr_token(): void
    {
        $user = app(CitizenIdentityService::class)->createCitizen($this->citizenAttributes());
        $oldToken = $user->qr_token;
        $number = $user->number;

        $newToken = app(CitizenIdentityService::class)->rotateQrToken($user);

        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame($number, $user->refresh()->number);
        $this->assertSame($newToken, $user->qr_token);
    }

    public function test_platform_identity_update_cannot_change_member_number(): void
    {
        $user = app(CitizenIdentityService::class)->createCitizen($this->citizenAttributes());
        $actor = $this->createSuperAdmin();

        app(\App\Services\UserRoleService::class)->update($actor, $user, [
            'number' => 'CHANGED-'.Str::random(8),
            'name' => 'Still Same Number',
        ]);

        $this->assertSame($user->number, $user->refresh()->number);
        $this->assertSame('Still Same Number', $user->name);
    }

    public function test_role_transitions_create_and_retain_qr_identity(): void
    {
        $actor = $this->createSuperAdmin();
        $target = app(CitizenIdentityService::class)->createCitizen($this->citizenAttributes());
        $target->syncRoles(Role::findOrCreate('admin', 'web'));
        $target->forceFill(['qr_token' => null])->saveQuietly();

        $target = app(\App\Services\UserRoleService::class)->changeRole($actor, $target, 'user');
        $token = $target->qr_token;
        $target = app(\App\Services\UserRoleService::class)->changeRole($actor, $target, 'admin', WasteBank::factory()->create());

        $this->assertNotEmpty($token);
        $this->assertSame($token, $target->qr_token);
        $this->assertTrue($target->hasRole('admin'));
    }

    public function test_backfill_command_is_dry_run_then_idempotent(): void
    {
        $citizen = app(CitizenIdentityService::class)->createCitizen($this->citizenAttributes());
        $citizen->forceFill(['qr_token' => null])->saveQuietly();
        $admin = app(CitizenIdentityService::class)->createCitizen($this->citizenAttributes());
        $admin->syncRoles(Role::findOrCreate('admin', 'web'));
        $admin->forceFill(['qr_token' => null])->saveQuietly();

        $this->artisan('members:backfill-qr', ['--dry-run' => true])
            ->assertSuccessful();
        $this->assertNull($citizen->refresh()->qr_token);
        $this->assertNull($admin->refresh()->qr_token);

        $this->artisan('members:backfill-qr')->assertSuccessful();
        $token = $citizen->refresh()->qr_token;
        $this->assertNotEmpty($token);
        $this->artisan('members:backfill-qr')->assertSuccessful();
        $this->assertSame($token, $citizen->refresh()->qr_token);
        $this->assertNull($admin->refresh()->qr_token);
    }

    public function test_qr_payload_is_opaque_and_parseable(): void
    {
        $identity = app(CitizenIdentityService::class);
        $user = $identity->createCitizen($this->citizenAttributes());
        $payload = $identity->qrPayload($user);

        $this->assertStringStartsWith('WRG:M:', $payload);
        $this->assertSame($user->qr_token, $identity->parseQrPayload($payload));
        $this->assertStringNotContainsString($user->number, $payload);
        $this->assertNull($identity->parseQrPayload('WRG:M:invalid'));
    }

    public function test_member_card_is_available_only_to_the_current_citizen(): void
    {
        $user = app(CitizenIdentityService::class)->createCitizen($this->citizenAttributes());

        $this->actingAs($user, 'web')
            ->get('/user/kartu-anggota')
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->number)
            ->assertSee('data:image/svg+xml');
    }

    public function test_member_card_does_not_expose_another_citizen_or_admin_access(): void
    {
        $firstAttributes = $this->citizenAttributes();
        $firstAttributes['name'] = 'First Card Citizen';
        $secondAttributes = $this->citizenAttributes();
        $secondAttributes['name'] = 'Second Card Citizen';
        $first = app(CitizenIdentityService::class)->createCitizen($firstAttributes);
        $second = app(CitizenIdentityService::class)->createCitizen($secondAttributes);
        $admin = $this->createSuperAdmin();

        $this->actingAs($first, 'web')
            ->get('/user/kartu-anggota')
            ->assertOk()
            ->assertSee($first->name)
            ->assertDontSee($second->name);

        $this->actingAs($admin, 'web')
            ->get('/user/kartu-anggota')
            ->assertForbidden();
    }

    private function createSuperAdmin(): User
    {
        $admin = app(CitizenIdentityService::class)->createCitizen($this->citizenAttributes());
        $admin->syncRoles(Role::findOrCreate('super_admin', 'web'));

        return $admin;
    }

    private function citizenAttributes(): array
    {
        $districtId = DB::table('districts')->insertGetId([
            'name' => 'Identity District '.Str::random(8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subDistrictId = DB::table('sub_districts')->insertGetId([
            'district_id' => $districtId,
            'name' => 'Identity Subdistrict '.Str::random(8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'name' => 'Identity Citizen',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => 'password',
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'address' => 'Identity address',
            'status' => 1,
            'balance' => 0,
        ];
    }
}
