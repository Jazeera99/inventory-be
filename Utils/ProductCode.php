<?php

namespace App\Utils;

class ProductCode
{
    public function __construct(private readonly string $code) {}

    /**
     * Valid formats:
     * A1000
     * A1000*S
     * A1000*S*BR
     *
     * Invalid formats:
     * A1000*
     * A1000**BR
     * A1000*BR*
     * A1000*BR*S*
     * A1000*BR*S*S
     */
    public function isValid(): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9]+(?:\*[a-zA-Z0-9]+){0,2}$/', $this->code);
    }

    /**
     * Usage:
     * $code = new ProductCode($code);
     * [$productCode, $size, $color] = $code->getParts();
     *
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    public function getParts(): array
    {
        $parts = explode('*', $this->code);

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
            $parts[2] ?? null,
        ];
    }

    public function getProductCode(): ?string
    {
        return $this->getParts()[0];
    }

    public function getSize(): ?string
    {
        return $this->getParts()[1];
    }

    public function getColor(): ?string
    {
        return $this->getParts()[2];
    }
}
