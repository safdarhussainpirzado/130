<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** One evidence image per call: private disk, mime sniffed by content, max 5 MB. */
class CallEvidenceController extends Controller
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function store(Request $request, string $uuid): JsonResponse
    {
        $call = Call::where('uuid', $uuid)->firstOrFail();
        $request->validate(['image' => ['required', 'file', 'max:5120']]);
        $file = $request->file('image');
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! isset(self::ALLOWED[$mime])) {
            return response()->json(['success' => false, 'message' => 'Only JPEG, PNG or WebP images are accepted.'], 422);
        }
        if (CallEvidence::where('call_id', $call->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Evidence already uploaded for this call.'], 409);
        }
        $path = "evidence/{$call->uuid}.".self::ALLOWED[$mime];
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
        $ev = CallEvidence::create(['call_id' => $call->id, 'path' => $path, 'mime' => $mime, 'size_bytes' => $file->getSize(), 'uploaded_by' => $request->user()->id, 'uploaded_at' => now()]);

        return response()->json(['success' => true, 'data' => ['uploaded_at' => $ev->uploaded_at, 'size_bytes' => $ev->size_bytes]], 201);
    }

    /** Web route: authorised, scope-visible users only. */
    public function show(Request $request, Call $call): StreamedResponse
    {
        abort_unless($request->user()?->can('calls.view') || $request->user()?->hasPermission('calls.view'), 403);
        $ev = CallEvidence::where('call_id', $call->id)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($ev->path), 404);

        return Storage::disk('local')->response($ev->path, null, ['Content-Type' => $ev->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
