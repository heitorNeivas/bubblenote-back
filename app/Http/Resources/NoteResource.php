<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Camada "View" da API: transforma o Model Note em payload JSON.
 *
 * @mixin \App\Models\Note
 */
class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body_markdown' => $this->body_markdown,
            'properties' => $this->properties ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'linked_notes' => self::collection($this->whenLoaded('linkedNotes')),
            'backlinks' => self::collection($this->whenLoaded('backlinks')),
        ];
    }
}
