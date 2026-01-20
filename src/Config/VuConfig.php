<?php

namespace Kuantaz\SitioConfianzaMDSF\Config;

class VuConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $authPath,
        public readonly string $clientId,
        /**
         * Lista de hosts permitidos para redirect_uri (whitelist de sitios de confianza).
         *
         * @var string[]
         */
        public readonly array $allowedRedirectHosts = [],
    ) {
    }
}

