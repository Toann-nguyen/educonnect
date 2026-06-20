<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use App\Models\RefreshToken;
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

        if ($session->refreshToken) {
            $session->refreshToken->update(['revoked_at' => now()]);
        }

        $session->delete();

        return response()->json(['message' => 'Session revoked successfully.']);
    }
}
