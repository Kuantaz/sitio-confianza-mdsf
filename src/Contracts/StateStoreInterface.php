<?php

namespace Kuantaz\SitioConfianzaMDSF\Contracts;

interface StateStoreInterface
{
    /**
     * Guarda un valor de state asociado a una clave (normalmente 'vus_auth_state').
     */
    public function put(string $key, string $value): void;

    /**
     * Recupera y opcionalmente elimina el valor de state almacenado.
     */
    public function pull(string $key): ?string;
}

