<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Device */
class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $manage = $user && ($user->isAdmin() || $user->id === $this->user_id);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'is_public' => $this->is_public,
            'types' => $this->whenLoaded('types', fn () => $this->types->map(fn ($t) => [
                'id' => $t->id, 'key' => $t->key, 'name' => $t->name, 'unit' => $t->unit,
            ])),
            // Owner/admin-only configuration is never exposed on public responses.
            $this->mergeWhen($manage, [
                'owner_id' => $this->user_id,
                'source_url' => $this->source_url,
                'source_mapping' => $this->source_mapping,
                'fetch_interval_min' => $this->fetch_interval_min,
                'last_fetched_at' => $this->last_fetched_at?->toIso8601String(),
                'last_error' => $this->last_error,
                'has_ingest_token' => $this->ingest_token_hash !== null,
            ]),
        ];
    }
}
