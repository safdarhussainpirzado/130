<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CaseMessageRequest;
use App\Models\Call;
use App\Models\CaseMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseChatController extends Controller
{
    /**
     * Screen 09/10: Retrieve Case Conversation Messages.
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $case = Call::where('uuid', $uuid)->firstOrFail();

        $query = CaseMessage::with('sender')->where('call_id', $case->id);

        // Security / Privacy: Commuters strictly see only shared_case messages
        if ($user->isCommuter()) {
            $query->where('audience', 'shared_case');
        } elseif ($request->has('audience')) {
            $query->where('audience', $request->query('audience'));
        }

        $messages = $query->orderBy('created_at', 'asc')->get()->map(function (CaseMessage $msg) {
            return [
                'uuid'         => $msg->uuid,
                'sender'       => [
                    'id'        => $msg->sender?->id,
                    'name'      => $msg->sender?->full_name,
                    'type'      => $msg->sender_type,
                ],
                'audience'     => $msg->audience,
                'message_type' => $msg->message_type,
                'content'      => $msg->content,
                'media_url'    => $msg->media_url,
                'location'     => $msg->latitude && $msg->longitude ? [
                    'latitude'  => (float) $msg->latitude,
                    'longitude' => (float) $msg->longitude,
                ] : null,
                'metadata'     => $msg->metadata,
                'sent_at'      => $msg->created_at->toIso8601String(),
                'delivered_at' => $msg->delivered_at?->toIso8601String(),
                'read_at'      => $msg->read_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success'        => true,
            'case_reference' => $case->call_number,
            'messages_count' => $messages->count(),
            'messages'       => $messages,
        ]);
    }

    /**
     * Screen 09/10: Send a Message, Voice Note, Image, or Location Card into Case.
     */
    public function store(CaseMessageRequest $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $case = Call::where('uuid', $uuid)->firstOrFail();
        $validated = $request->validated();

        // Determine sender type
        $senderType = 'control_operator';
        if ($user->isCommuter()) {
            $senderType = 'commuter';
        } elseif ($user->isPatrolOfficer()) {
            $senderType = 'patrol_officer';
        }

        // Strict RBAC on audience: Commuters cannot send internal control messages
        $audience = $validated['audience'] ?? 'shared_case';
        if ($user->isCommuter()) {
            $audience = 'shared_case';
        }

        $message = CaseMessage::create([
            'call_id'        => $case->id,
            'sender_user_id' => $user->id,
            'sender_type'    => $senderType,
            'audience'       => $audience,
            'message_type'   => $validated['message_type'],
            'content'        => $validated['content'] ?? null,
            'media_url'      => $validated['media_url'] ?? null,
            'latitude'       => $validated['latitude'] ?? null,
            'longitude'      => $validated['longitude'] ?? null,
            'metadata'       => $validated['metadata'] ?? null,
            'delivered_at'   => now(),
        ]);

        return response()->json([
            'success'        => true,
            'message_id'     => $message->uuid,
            'case_reference' => $case->call_number,
            'sender_type'    => $senderType,
            'audience'       => $audience,
            'delivered_at'   => $message->delivered_at?->toIso8601String(),
        ], 201);
    }
}
