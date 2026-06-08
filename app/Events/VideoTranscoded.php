<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoTranscoded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $videoId,
        public string $hlsUrl,
        public string $transcodeStatus = 'ready',
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('video.'.$this->videoId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'video.transcoded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'video_id' => $this->videoId,
            'hls_url' => $this->hlsUrl,
            'transcode_status' => $this->transcodeStatus,
        ];
    }
}
