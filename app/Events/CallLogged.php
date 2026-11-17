<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// ShouldBroadcastNow: dispatched synchronously. The queued variant (ShouldBroadcast) piled up 73k unprocessed jobs in
// Redis because no queue worker runs in production and no broadcaster/Echo client consumes this event.
class CallLogged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int|string $callId;
    public string $callNumber;
    public string $type;
    public string $priority;

    /**
     * Create a new event instance.
     */
    public function __construct(int|string $callId, string $callNumber, string $type, string $priority)
    {
        $this->callId = $callId;
        $this->callNumber = $callNumber;
        $this->type = $type;
        $this->priority = $priority;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcasts to the secure sector-specific operations room 
        return [
            new PrivateChannel('operations.hq'),
        ];
    }
    
    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'call.created';
    }
}
