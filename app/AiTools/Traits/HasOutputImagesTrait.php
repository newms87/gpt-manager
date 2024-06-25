<?php

namespace App\AiTools\Traits;

trait HasOutputImagesTrait
{
    protected array $outputImages = [];

    public function getOutputImages(): array
    {
        return $this->outputImages;
    }
}
