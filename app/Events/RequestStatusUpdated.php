<?php

namespace App\Events;

use App\Models\DataRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on private channels `case.{caseId}` and `user.{userId}` (every case member).
 * Frontend listens with useEcho(`case.${id}`, 'RequestStatusUpdated', ...).
 * Payload contains NO PII.
 */
class RequestStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DataRequest $request) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('case.'.$this->request->case_id)];
        foreach ($this->request->legalCase->memberships()->pluck('user_id') as $userId) {
            $channels[] = new PrivateChannel('user.'.$userId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'RequestStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->request->id,
            'case_id' => $this->request->case_id,
            'type' => $this->request->type->value,
            'type_label' => $this->request->type->label(),
            'status' => $this->request->status->value,
            'status_label' => $this->request->status->label(),
            'rejection_reason' => $this->request->rejection_reason,
            'updated_at' => $this->request->updated_at?->toIso8601String(),
        ];
    }
}
