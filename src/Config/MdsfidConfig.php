<?php

namespace Kuantaz\SitioConfianzaMDSF\Config;

class MdsfidConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $crearIdentidadPath,
        public readonly string $validarIdentidadPath,
        public readonly int $timeoutSeconds = 10,
    ) {
    }
}

