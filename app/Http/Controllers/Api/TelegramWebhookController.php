<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessTelegramUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        ProcessTelegramUpdate::dispatch($request->all());

        return response('', Response::HTTP_OK);
    }
}
