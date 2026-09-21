<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfilePhotoCleanupTest extends TestCase
{
    use DatabaseMigrations;

    public function test_replacing_an_owned_profile_photo_removes_the_old_file_after_update(): void
    {
        Storage::fake('public');
        $districtId = DB::table('districts')->insertGetId(['name' => 'Cleanup District', 'created_at' => now(), 'updated_at' => now()]);
        $subDistrictId = DB::table('sub_districts')->insertGetId(['district_id' => $districtId, 'name' => 'Cleanup Subdistrict', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::query()->create([
            'name' => 'Cleanup User',
            'number' => 'CLEANUP-1',
            'email' => 'cleanup@example.test',
            'password' => Hash::make('password123'),
            'district_id' => $districtId,
            'sub_district_id' => $subDistrictId,
            'status' => 1,
        ]);
        $oldPath = 'profile-images/'.$user->id.'/old.jpg';
        $newPath = 'profile-images/'.$user->id.'/new.jpg';
        Storage::disk('public')->put($oldPath, 'old-image');
        Storage::disk('public')->put($newPath, 'new-image');
        $user->forceFill(['image' => $oldPath])->save();

        $user->forceFill(['image' => $newPath])->save();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }
}
