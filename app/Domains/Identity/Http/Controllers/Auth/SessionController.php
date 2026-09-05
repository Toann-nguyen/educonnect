<?php

namespace App\Domains\Identity\Http\Controllers\Auth;

use App\Domains\Identity\Http\Controllers\Controller;
use App\Domains\Identity\Models\UserSession;
use App\Domains\Identity\Services\Auth\RefreshRotationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = UserSession::where('user_id', $request->user()->id)
            ->with('refreshToken')
            ->latest('last_active_at')
            ->get();
        return response()->json(['data' => $sessions]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $session = UserSession::where('user_id', $request->user()->id)->findOrFail($id);

        // T1.3 revoke 1: Redis family/sid/grace + DB revoked
        // T5.1: thêm sid_revoked để Go chặn ngay access token của session đó
        if ($session->refreshToken) {
            $hash = $session->refreshToken->token_hash;
            $sid = $session->refreshToken->sid;
            $session->refreshToken->update(['revoked_at' => now()]);
            app(RefreshRotationService::class)->revokeOne($hash, (int) $request->user()->id);
            if (!empty($sid)) {
                try { app(\App\Domains\Identity\Services\Auth\TokenRevocationService::class)->revokeSid((string) $sid); } catch (\Throwable $e) {}
            }
        }

        $session->delete();

        return response()->json(['message' => 'Session revoked successfully.']);
    }
}
