<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = Conversation::query()->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id))->with(['participants.user:id,name,role', 'messages' => fn ($query) => $query->latest()->limit(1)])->latest()->paginate();

        return response()->json(['data' => $conversations]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['participant_ids' => ['required', 'array', 'min:1'], 'participant_ids.*' => ['integer', 'exists:users,id', 'distinct'], 'subject' => ['nullable', 'string', 'max:255'], 'type' => ['sometimes', 'in:DIRECT,GROUP']]);
        $conversation = DB::transaction(function () use ($data, $request): Conversation {
            $conversation = Conversation::create(['type' => $data['type'] ?? (count($data['participant_ids']) > 1 ? Conversation::TYPE_GROUP : Conversation::TYPE_DIRECT), 'subject' => $data['subject'] ?? null, 'created_by_user_id' => $request->user()->id]);
            foreach (array_unique([...$data['participant_ids'], $request->user()->id]) as $id) {
                ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $id]);
            }

            return $conversation;
        });

        return response()->json(['data' => $conversation->load('participants.user:id,name,role')], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);

        return response()->json(['data' => $conversation->load(['participants.user:id,name,role', 'messages.sender:id,name,role'])]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);
        $message = Message::create(['conversation_id' => $conversation->id, 'sender_id' => $request->user()->id, 'body' => $request->validate(['body' => ['required', 'string', 'max:5000']])['body']]);

        return response()->json(['data' => $message->load('sender:id,name,role')], 201);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $participant = $this->authorizeParticipant($request, $conversation);
        $participant->update(['last_read_at' => now()]);

        return response()->json(['data' => ['last_read_at' => $participant->last_read_at?->toISOString()]]);
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): ConversationParticipant
    {
        return ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $request->user()->id)->firstOr(fn () => abort(403));
    }
}
