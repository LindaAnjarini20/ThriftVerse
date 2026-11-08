<?php
namespace App\Http\Controllers;

use App\Models\{Conversation, Listing, Message};
use Illuminate\Http\Request;

/** Chat antara pembeli dan penjual per listing. Nomor telepon & tautan chat luar disamarkan. */
class ChatController extends Controller
{
    public function index()
    {
        $me = auth()->user();
        $convs = Conversation::with(['listing', 'buyer', 'seller'])
            ->where(fn ($q) => $q->where('buyer_id', $me->id)->orWhere('seller_id', $me->id))
            ->orderByDesc('last_message_at')->orderByDesc('id')->get();
        $last = Message::whereIn('conversation_id', $convs->pluck('id'))->orderBy('id')->get()->groupBy('conversation_id');
        return view('chat.index', ['convs' => $convs, 'me' => $me, 'last' => $last]);
    }

    public function start(Listing $listing)
    {
        $me = auth()->user();
        abort_if($listing->seller_id === $me->id, 403, 'Ini listing Anda sendiri.');
        $c = Conversation::firstOrCreate(['listing_id' => $listing->id, 'buyer_id' => $me->id], ['seller_id' => $listing->seller_id]);
        return redirect()->route('chat.show', $c);
    }

    public function show(Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($conversation->involves($me), 403);
        $conversation->load(['listing', 'buyer', 'seller', 'messages']);
        Message::where('conversation_id', $conversation->id)->where('sender_id', '!=', $me->id)->whereNull('read_at')->update(['read_at' => now()]);
        return view('chat.show', ['c' => $conversation, 'me' => $me, 'other' => $conversation->other($me)]);
    }

    public function store(Request $request, Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($conversation->involves($me), 403);
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $body = Message::sanitize($data['body']);
        abort_if($body === '', 422, 'Pesan kosong.');

        $hadUnread = Message::where('conversation_id', $conversation->id)->where('sender_id', $me->id)->whereNull('read_at')->exists();
        $m = Message::create(['conversation_id' => $conversation->id, 'sender_id' => $me->id, 'body' => $body]);
        $conversation->update(['last_message_at' => now()]);
        if (! $hadUnread) {
            $conversation->other($me)->tell('Pesan baru', "{$me->name} mengirim pesan tentang {$conversation->listing->title}", route('chat.show', $conversation));
        }
        return $request->expectsJson() ? response()->json($this->fmt($m, $me)) : redirect()->route('chat.show', $conversation);
    }

    public function poll(Request $request, Conversation $conversation)
    {
        $me = auth()->user();
        abort_unless($conversation->involves($me), 403);
        $after = (int) $request->query('after', 0);
        $msgs = Message::where('conversation_id', $conversation->id)->where('id', '>', $after)->orderBy('id')->get();
        Message::where('conversation_id', $conversation->id)->where('sender_id', '!=', $me->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json($msgs->map(fn ($m) => $this->fmt($m, $me))->values());
    }

    private function fmt(Message $m, $me): array
    {
        return ['id' => $m->id, 'mine' => $m->sender_id === $me->id, 'body' => $m->body, 'time' => $m->created_at->format('H:i')];
    }
}
