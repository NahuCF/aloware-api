<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendSmsRequest;
use App\Http\Resources\SmsMessageResource;
use App\Models\SmsMessage;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmsController extends Controller
{
    public function __construct(
        private SmsService $sms
    ) {}

    public function send(SendSmsRequest $request): SmsMessageResource
    {
        $input = $request->validated();

        $to = data_get($input, 'to');
        $body = data_get($input, 'body');

        $message = $this->sms->send($to, $body, $request->user()->id);

        return new SmsMessageResource($message);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $messages = SmsMessage::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return SmsMessageResource::collection($messages);
    }
}
