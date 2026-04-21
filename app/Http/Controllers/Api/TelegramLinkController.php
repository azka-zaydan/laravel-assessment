<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Telegram\LinkCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TelegramLinkController extends Controller
{
    public function __invoke(Request $request, LinkCodeService $linkCodeService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $code = $linkCodeService->generate($user);

        return response()->json([
            'code' => $code,
            'expires_in' => 600,
        ]);
    }
}
