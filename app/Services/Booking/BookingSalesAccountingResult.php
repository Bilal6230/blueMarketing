<?php

namespace App\Services\Booking;

final class BookingSalesAccountingResult
{
    public function __construct(
        public array $data,
        public array $blockReasons,
        public bool $hasStructuralAmbiguity,
        public bool $amountsConsistent
    ) {
    }

    public function isStructurallyValid(): bool
    {
        return !$this->hasStructuralAmbiguity;
    }

    public function toArray(): array
    {
        return array_merge($this->data, [
            'block_reasons' => $this->blockReasons,
            'has_structural_ambiguity' => $this->hasStructuralAmbiguity,
            'amounts_consistent' => $this->amountsConsistent,
        ]);
    }
}
