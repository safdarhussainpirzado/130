<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\HelpFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Commuter feedback: only the owner of a completed call; one submission per call. */
class HelpFeedbackController extends Controller
{
    public function store(Request $request, string $uuid): JsonResponse
    {
        $call = Call::where('uuid', $uuid)->firstOrFail();
        if ((int) $call->commuter_user_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Not your case.'], 403);
        }
        if (! in_array($call->status, ['completed', 'closed', 'resolved'], true) && ! $call->completed_at) {
            return response()->json(['success' => false, 'message' => 'Feedback opens after the assistance is completed.'], 422);
        }
        $v = $request->validate([
            'timeliness' => ['required', 'integer', 'between:1,5'], 'behaviour' => ['required', 'integer', 'between:1,5'],
            'helpfulness' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        if (HelpFeedback::where('call_id', $call->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Feedback already submitted.'], 409);
        }
        $overall = (int) round(($v['timeliness'] + $v['behaviour'] + $v['helpfulness']) / 3);
        $fb = HelpFeedback::create($v + ['call_id' => $call->id, 'commuter_id' => $request->user()->id, 'officer_id' => $call->tiger_reached_status_by ?: null, 'overall' => $overall, 'submitted_at' => now()]);

        return response()->json(['success' => true, 'data' => ['overall' => $fb->overall]], 201);
    }
}
