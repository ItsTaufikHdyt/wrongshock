<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CitizenIdentityService;
use Illuminate\Console\Command;

class BackfillCitizenQrTokens extends Command
{
    protected $signature = 'members:backfill-qr {--dry-run : Report eligible citizens without changing data}';

    protected $description = 'Backfill opaque QR identity tokens for citizen accounts.';

    public function handle(CitizenIdentityService $identity): int
    {
        $query = User::query()
            ->whereNull('qr_token')
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'user'));
        $eligible = (clone $query)->count();
        $citizens = User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'user'))->count();
        $alreadyTokenized = $citizens - $eligible;
        $generated = 0;
        $failed = 0;

        $this->line('Eligible citizens: '.$eligible);
        $this->line('Already tokenized: '.$alreadyTokenized);

        if ($this->option('dry-run')) {
            $this->line('Dry run: no tokens generated.');

            return self::SUCCESS;
        }

        $query->orderBy('id')->chunkById(100, function ($users) use ($identity, &$generated, &$failed): void {
            foreach ($users as $user) {
                try {
                    $identity->ensureQrToken($user);
                    $generated++;
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->error("User {$user->id}: token generation failed.");
                }
            }
        });

        $this->line('Generated: '.$generated);
        $this->line('Failed: '.$failed);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
