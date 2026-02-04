<?php

namespace Kuantaz\SitioConfianzaMDSF\Config;

class MdsfidConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $crearIdentidadPath,
        public readonly string $validarIdentidadPath,
        public readonly int $timeoutSeconds = 10,
        public readonly int $jwtExpirationSeconds = 300, // 5 minutos por defecto
        public readonly bool $secretIsBase64 = false, // Si true, decodifica el secret desde Base64 antes de firmar
    ) {
    }
}

