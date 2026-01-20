<?php

namespace Kuantaz\SitioConfianzaMDSF\Contracts;

interface PostLoginRedirectInterface
{
    /**
     * A partir del resultado del login, decide a qué URL o ruta interna debe ir el usuario.
     *
     * El tipo de $loginResult queda intencionalmente abierto (mixed) para que cada
     * aplicación pueda retornar lo que necesite desde su LoginHandler.
     */
    public function resolveRedirect(mixed $loginResult): string;
}

