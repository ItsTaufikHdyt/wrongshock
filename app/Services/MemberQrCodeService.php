<?php

namespace App\Services;

use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\SvgWriter;

class MemberQrCodeService
{
    public function __construct(private CitizenIdentityService $identity) {}

    public function dataUri(User $user): string
    {
        return Builder::create()
            ->writer(new SvgWriter)
            ->data($this->identity->qrPayload($user))
            ->encoding(new Encoding('UTF-8'))
            ->size(280)
            ->margin(10)
            ->build()
            ->getDataUri();
    }
}
