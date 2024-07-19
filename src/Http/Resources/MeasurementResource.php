<?php

namespace EscolaLms\Reports\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'label' => $this->resource->label,
            'value' => $this->resource->value,
            'measurable_id' => $this->resource->measurable_id,
            'measurable_type' => $this->resource->measurable_type,
        ];
    }
}
