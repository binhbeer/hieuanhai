<?php

namespace App\Events;

use App\Models\AiImage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiImageCompleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public AiImage $image) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->image->user_id ? [new PrivateChannel('App.Models.User.'.$this->image->user_id)] : [];
    }

    /**
     * @return array{image_id: int, status: string, progress: mixed}
     */
    public function broadcastWith(): array
    {
        return [
            'image_id' => $this->image->id,
            'status' => $this->image->status,
            'progress' => data_get($this->image->request_meta, 'progress'),
        ];
    }
}
