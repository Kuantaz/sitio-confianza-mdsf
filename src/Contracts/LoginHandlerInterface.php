<?php

namespace Kuantaz\SitioConfianzaMDSF\Contracts;

use Kuantaz\SitioConfianzaMDSF\DTO\VuIdentityDTO;

interface LoginHandlerInterface
{
    /**
     * Ejecuta el login de la aplicación consumidora usando la identidad validada.
     *
     * La implementación concreta (por ejemplo en Laravel) se encarga de:
     * - Buscar el usuario local (ej: por RUN).
     * - Ejecutar el mecanismo de login propio (Auth::login, etc.).
     * - Retornar cualquier información necesaria para decidir la redirección posterior.
     */
    public function login(VuIdentityDTO $identity): mixed;
}

