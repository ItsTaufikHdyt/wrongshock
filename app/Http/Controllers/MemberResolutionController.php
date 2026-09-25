<?php

namespace App\Http\Controllers;

use App\Services\MemberResolutionResult;
use App\Services\MemberResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberResolutionController extends Controller
{
    public function resolveQr(Request $request, MemberResolutionService $members): JsonResponse
    {
        $payload = $request->validate([
            'payload' => ['required', 'string', 'max:128'],
        ])['payload'];

        $result = $members->resolveQr($payload, $request->user());
        if ($result->status === MemberResolutionResult::UNAUTHORIZED) {
            return response()->json(['status' => $result->status, 'eligible' => false], 403);
        }

        if (! $result->eligible()) {
            return response()->json([
                'status' => $result->status,
                'eligible' => false,
                'message' => $this->message($result->status),
            ], 422);
        }

        return response()->json([
            'status' => MemberResolutionResult::ELIGIBLE,
            'eligible' => true,
            'member' => [
                'id' => $result->user->id,
                'name' => $result->user->name,
                'number' => $result->user->number,
            ],
        ]);
    }

    private function message(string $status): string
    {
        return match ($status) {
            MemberResolutionResult::INVALID_QR => 'QR anggota tidak valid.',
            MemberResolutionResult::MEMBER_NOT_FOUND => 'Anggota tidak ditemukan.',
            MemberResolutionResult::USER_INACTIVE => 'Pengguna tidak aktif.',
            MemberResolutionResult::NOT_CITIZEN => 'Akun ini bukan anggota.',
            MemberResolutionResult::MEMBERSHIP_NOT_FOUND => 'Anggota tidak terdaftar pada bank ini.',
            MemberResolutionResult::MEMBERSHIP_INACTIVE => 'Keanggotaan pada bank ini tidak aktif.',
            MemberResolutionResult::BANK_INACTIVE => 'Bank Sampah sedang tidak aktif.',
            default => 'Anggota tidak dapat dipilih.',
        };
    }
}
