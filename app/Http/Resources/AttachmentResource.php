<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'name'=>$this->name,
            'uploaded_by'=>$this->uploaded_by,
            'path'       =>asset('storage/'.$this->path),
            'mime_type'  => $this->mime_type,
            'size'=>$this->size,
            'formattedSize' => $this->formattedSize,
            'uploaded_at'   => $this->created_at?->format('Y-m-d H:i:s'),
        ];;
    }
}
