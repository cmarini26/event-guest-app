<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AttachmentController extends Controller
{
    use AuthorizesRequests;

    /** Max attachments per event. */
    private const MAX_PER_EVENT = 10;

    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json($event->attachments()->latest()->get());
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        if ($event->attachments()->count() >= self::MAX_PER_EVENT) {
            return response()->json([
                'message' => 'This event has reached the maximum of '.self::MAX_PER_EVENT.' attachments.',
            ], 422);
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB (kilobytes)
                'mimes:pdf,png,jpg,jpeg,gif,webp,doc,docx,xls,xlsx,csv,txt',
            ],
        ]);

        $file = $request->file('file');
        $path = $file->store('attachments/'.$event->id, 'public');

        $attachment = $event->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json($attachment, 201);
    }

    public function destroy(Request $request, Event $event, Attachment $attachment): JsonResponse
    {
        $this->authorize('update', $event);
        abort_unless($attachment->event_id === $event->id, 404);

        $attachment->delete(); // model booted() removes the underlying file

        return response()->json(null, 204);
    }
}
