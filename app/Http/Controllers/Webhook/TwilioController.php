<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\CallSessionStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\Line;
use App\Models\User;
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

        $newContext = $this->mergeContext($session->context, $step['context'] ?? []);
        $newPath = array_merge($session->path, [$digit]);

        $session->update([
            'path' => $newPath,
            'context' => $newContext,
        ]);

        return match ($step['action_type']) {
            'menu' => $this->playMenu($session, $step['sub_steps'] ?? []),
            'route_to_skill' => $this->routeToSkill($session),
            'route_to_user' => $this->routeToUser($session, $step['target_id']),
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

        $handleInputUrl = '/webhook/twilio/voice/handle-input?session_id=' . $session->id;

        $gather = $response->gather([
            'numDigits' => 1,
            'action' => $handleInputUrl,
            'method' => 'POST',
            'timeout' => 5,
        ]);

        foreach ($steps as $step) {
            $this->playStepPrompt($gather, $step);
        }

        $response->redirect($handleInputUrl, ['method' => 'POST']);

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

    private function mergeContext(array $existing, array $new): array
    {
        $merged = $existing;

        if (!empty($new['language_id'])) {
            $merged['language_id'] = $new['language_id'];
        }

        $existingSkills = $existing['skill_ids'] ?? [];
        $newSkills = $new['skill_ids'] ?? [];
        $merged['skill_ids'] = array_unique(array_merge($existingSkills, $newSkills));

        return $merged;
    }

    private function routeToSkill(CallSession $session): \Illuminate\Http\Response
    {
        $context = $session->context;
        $languageId = $context['language_id'] ?? null;
        $skillIds = $context['skill_ids'] ?? [];

        $query = User::where('status', UserStatus::Available);

        if ($languageId) {
            $query->whereHas('languages', function ($q) use ($languageId) {
                $q->where('languages.id', $languageId);
            });
        }

        if (!empty($skillIds)) {
            $query->whereHas('skills', function ($q) use ($skillIds) {
                $q->whereIn('skills.id', $skillIds);
            }, '=', count($skillIds));
        }

        $user = $query->orderBy('last_activity_at', 'asc')->first();

        $response = new VoiceResponse();

        if (!$user) {
            return $this->errorResponse('No agents available');
        }


        $user->update(['status' => UserStatus::OnCall]);
        $session->update(['status' => CallSessionStatus::Connected]);

        $response->say('Connecting you with ' . $user->name);

        $dial = $response->dial(null, [
            'callerId' => $session->line->phone_number,
            'timeout' => 30,
            'action' => '/webhook/twilio/voice/dial-complete?session_id=' . $session->id . '&user_id=' . $user->id,
        ]);

        $dial->client('agent_' . $user->id);

        return $this->twimlResponse($response);
    }

    private function routeToUser(CallSession $session, int $userId): \Illuminate\Http\Response
    {
        $user = User::find($userId);

        if (!$user) {
            return $this->errorResponse('The requested agent is not available.');
        }

        $response = new VoiceResponse();

        $session->update(['status' => CallSessionStatus::Connected]);

        $response->say('Connecting you to agent.');

        $dial = $response->dial(null, [
            'callerId' => $session->line->phone_number,
            'timeout' => 30,
            'action' => '/webhook/twilio/voice/dial-complete?session_id=' . $session->id . '&user_id=' . $user->id,
        ]);

        $dial->client('agent_' . $user->id);

        return $this->twimlResponse($response);
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

    public function outbound(Request $request)
    {
        $input = $request->all();
        $to = data_get($input, 'To');

        $response = new VoiceResponse();

        if (!$to) {
            $response->say('No destination number provided.');
            $response->hangup();
            return $this->twimlResponse($response);
        }

        $dial = $response->dial(null, [
            'callerId' => config('services.twilio.phone_number'),
            'timeout' => 30,
        ]);

        $dial->number($to);

        return $this->twimlResponse($response);
    }

    public function dialComplete(Request $request)
    {
        $input = $request->all();
        $sessionId = data_get($input, 'session_id');
        $status = data_get($input, 'DialCallStatus');
        $userId = data_get($input, 'user_id');

        if ($userId) {
            User::where('id', $userId)->update([
                'status' => UserStatus::Available,
                'last_activity_at' => now(),
            ]);
        }

        CallSession::where('id', $sessionId)->update(['status' => CallSessionStatus::Completed]);

        $response = new VoiceResponse();

        if ($status !== 'completed') {
            $response->say('Agent could not pick up your call.');
        }

        $response->hangup();

        return $this->twimlResponse($response);
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
