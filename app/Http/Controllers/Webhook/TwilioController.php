<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\CallSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Line;
use Illuminate\Http\Request;
use Twilio\TwiML\VoiceResponse;

class TwilioController extends Controller
{
    public function incoming(Request $request)
    {
        $input = $request->all();
        $callSid = data_get($input, 'CallSid');
        $from = data_get($input, 'From');
        $to = data_get($input, 'To');

        $line = Line::where('phone_number', $to)->first();

        if (!$line) {
            return $this->errorResponse('Invalid number');
        }

        if (empty($line->ivr_steps)) {
            return $this->errorResponse('There is no IVR configured for this line');
        }

        $session = CallSession::create([
            'call_sid' => $callSid,
            'line_id' => $line->id,
            'from_number' => $from,
            'path' => [],
            'context' => [],
        ]);

        return $this->playMenu($session, $line->ivr_steps);
    }

    public function handleInput(Request $request)
    {
        $input = $request->all();
        $sessionId = data_get($input, 'session_id');
        $digit = data_get($input, 'Digits');

        $session = CallSession::find($sessionId);

        if (!$session) {
            return $this->errorResponse('Session not found');
        }

        $line = $session->line;

        if (!$line) {
            return $this->errorResponse('Line not found');
        }

        $currentSteps = $this->getStepsAtPath($line->ivr_steps, $session->path);

        $step = collect($currentSteps)->firstWhere('digit', $digit);

        if (!$step) {
            return $this->playMenu($session, $currentSteps);
        }

        $newPath = array_merge($session->path, [$digit]);

        $session->update(['path' => $newPath]);

        return match ($step['action_type']) {
            'menu' => $this->playMenu($session, $step['sub_steps'] ?? []),
            'route_to_line' => $this->routeToLine($session, $step['target_id']),
            default => $this->errorResponse('The action does not exists'),
        };
    }

    private function playMenu(CallSession $session, array $steps): \Illuminate\Http\Response
    {
        $response = new VoiceResponse();

        if (empty($session->path)) {
            $response->say('Welcome to ' . config('app.name'));
        }

        $gather = $response->gather([
            'numDigits' => 1,
            'action' => '/webhook/twilio/voice/handle-input?session_id=' . $session->id,
            'method' => 'POST',
            'timeout' => 5,
        ]);

        foreach ($steps as $step) {
            $this->playStepPrompt($gather, $step);
        }

        $response->redirect('/webhook/twilio/voice/handle-input?session_id=' . $session->id);

        return $this->twimlResponse($response);
    }

    private function getStepsAtPath(array $steps, array $path): array
    {
        $current = $steps;

        foreach ($path as $digit) {
            $step = collect($current)->firstWhere('digit', $digit);
            if (!$step || empty($step['sub_steps'])) {
                return [];
            }
            $current = $step['sub_steps'];
        }

        return $current;
    }

    private function routeToLine(CallSession $session, int $lineId): \Illuminate\Http\Response
    {
        $line = Line::find($lineId);

        if (!$line) {
            return $this->errorResponse('Transfer failed.');
        }

        if (empty($line->ivr_steps)) {
            return $this->errorResponse('The destination line has no IVR configured.');
        }

        $session->update(['status' => CallSessionStatus::Transferred]);

        $newSession = CallSession::create([
            'call_sid' => $session->call_sid,
            'line_id' => $line->id,
            'from_number' => $session->from_number,
            'path' => [],
            'context' => [],
        ]);

        $response = new VoiceResponse();
        $response->say('Transferring your call.');

        return $this->playMenu($newSession, $line->ivr_steps);
    }

    private function playStepPrompt($gather, array $step): void
    {
        $gather->say('Press ' . $step['digit'] . ' for ' . $step['label'], ['voice' => 'Polly.Joanna']);
    }

    private function twimlResponse(VoiceResponse $response): \Illuminate\Http\Response
    {
        return response($response->__toString(), 200)
            ->header('Content-Type', 'text/xml');
    }

    private function errorResponse(string $message): \Illuminate\Http\Response
    {
        $response = new VoiceResponse();
        $response->say($message);
        $response->hangup();

        return $this->twimlResponse($response);
    }
}
