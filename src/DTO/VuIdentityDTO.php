<?php

namespace Kuantaz\SitioConfianzaMDSF\DTO;

class VuIdentityDTO
{
    public function __construct(
        public readonly int $runCiudadano,
        public readonly string $dvCiudadano,
        public readonly ?array $rawPayload = null,
    ) {
    }
}

