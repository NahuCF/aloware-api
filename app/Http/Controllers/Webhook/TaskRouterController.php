<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\CallSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskRouterController extends Controller
{
    public function assignment(Request $request): JsonResponse
    {
        $input = $request->all();

        $taskAttributes = json_decode(data_get($input, 'TaskAttributes', '{}'), true);
        $workerAttributes = json_decode(data_get($input, 'WorkerAttributes', '{}'), true);
        $workerSid = data_get($input, 'WorkerSid');

        $userId = data_get($workerAttributes, 'user_id');
        $sessionId = data_get($taskAttributes, 'session_id');

        if ($sessionId) {
            CallSession::where('id', $sessionId)->update([
                'status' => CallSessionStatus::Connected,
            ]);
        }

        $response = [
            'instruction' => 'dequeue',
            'to' => 'client:agent_' . $userId,
            'from' => config('services.twilio.phone_number'),
            'post_work_activity_sid' => config('services.twilio.activity_available_sid'),
        ];

        return response()->json($response);
    }

    public function events(Request $request): JsonResponse
    {
        $input = $request->all();

        $eventType = data_get($input, 'EventType');
        $taskAttributes = json_decode(data_get($input, 'TaskAttributes', '{}'), true);

        $sessionId = data_get($taskAttributes, 'session_id');

        switch ($eventType) {
            case 'task.completed':
                if ($sessionId) {
                    CallSession::where('id', $sessionId)->update([
                        'status' => CallSessionStatus::Completed,
                    ]);
                }
                break;

            case 'task.canceled':
                if ($sessionId) {
                    CallSession::where('id', $sessionId)->update([
                        'status' => CallSessionStatus::Completed,
                    ]);
                }
                break;

            case 'reservation.accepted':
                $workerAttributes = json_decode(data_get($input, 'WorkerAttributes', '{}'), true);
                $userId = data_get($workerAttributes, 'user_id');
                if ($userId) {
                    User::where('id', $userId)->update([
                        'last_activity_at' => now(),
                    ]);
                }
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}
