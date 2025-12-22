<?php

namespace App\Events;

use App\Models\Email;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewEmailReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Email $email;

    /**
     * Create a new event instance.
     */
    public function __construct(Email $email)
    {
        $this->email = $email->load('attachments');
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('inbox.' . $this->email->inbox_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->email->id,
            'from_email' => $this->email->from_email,
            'from_name' => $this->email->from_name,
            'subject' => $this->email->subject,
            'preview' => $this->email->preview,
            'is_read' => $this->email->is_read,
            'received_at' => $this->email->received_at->toIso8601String(),
            'attachments_count' => $this->email->attachments->count(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'email.received';
    }
}
