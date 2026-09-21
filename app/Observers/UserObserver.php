<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Storage;

class UserObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(User $user): void
    {
        if ($user->isDirty('image')) {
            $this->deleteOwnedImage($user, $user->getOriginal('image'));
        }
    }

    public function deleted(User $user): void
    {
        $this->deleteOwnedImage($user, $user->image);
    }

    private function deleteOwnedImage(User $user, ?string $image): void
    {
        $ownedDirectory = 'profile-images/'.$user->getKey().'/';

        if ($image && str_starts_with($image, $ownedDirectory)) {
            Storage::disk('public')->delete($image);
        }
    }
}
