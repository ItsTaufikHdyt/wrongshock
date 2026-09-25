<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteBankMember;

final readonly class MemberResolutionResult
{
    public const ELIGIBLE = 'ELIGIBLE';

    public const INVALID_QR = 'INVALID_QR';

    public const MEMBER_NOT_FOUND = 'MEMBER_NOT_FOUND';

    public const USER_INACTIVE = 'USER_INACTIVE';

    public const NOT_CITIZEN = 'NOT_CITIZEN';

    public const MEMBERSHIP_NOT_FOUND = 'MEMBERSHIP_NOT_FOUND';

    public const MEMBERSHIP_INACTIVE = 'MEMBERSHIP_INACTIVE';

    public const BANK_INACTIVE = 'BANK_INACTIVE';

    public const UNAUTHORIZED = 'UNAUTHORIZED';

    public function __construct(
        public string $status,
        public ?User $user = null,
        public ?WasteBankMember $membership = null,
    ) {}

    public function eligible(): bool
    {
        return $this->status === self::ELIGIBLE && $this->user !== null;
    }
}
