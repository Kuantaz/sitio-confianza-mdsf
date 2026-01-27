<?php

namespace Kuantaz\SitioConfianzaMDSF;

use Kuantaz\SitioConfianzaMDSF\Config\ExternalConfig;
use Kuantaz\SitioConfianzaMDSF\Contracts\StateStoreInterface;
use Kuantaz\SitioConfianzaMDSF\DTO\VuIdentityDTO;
use Kuantaz\SitioConfianzaMDSF\Exceptions\MdsfidException;

/**
 * Orquestador del flujo de autenticación con sistemas externos (VUS/MDSFID).
 *
 * Esta clase se mantendrá agnóstica del framework. Aquí solo definimos
 * la estructura básica; la lógica concreta se implementará en pasos
 * posteriores del refactor.
 */
class ExternalAuthFlow
{
    public const DEFAULT_STATE_KEY = 'sitio_confianza_mdsfid_auth_state';

    public function __construct(
        public readonly ExternalConfig $config,
        public readonly MdsfidClient $mdsfidClient,
        public readonly StateStoreInterface $stateStore,
        private readonly string $stateKey = self::DEFAULT_STATE_KEY,
    ) {
    }

    /**
     * Inicia el flujo de login:
     * - Genera un state aleatorio.
     * - Lo persiste vía StateStoreInterface.
     * - Construye y retorna la URL de autenticación en el sistema externo.
     *
     * La aplicación consumidora solo debe redirigir a esa URL.
     *
     * @throws \InvalidArgumentException si el redirectUri no es válido según allowedRedirectHosts
     */
    public function startLogin(string $redirectUri): string
    {
        if (!$this->isValidRedirectUri($redirectUri)) {
            throw new \InvalidArgumentException('redirect_uri no permitido para el sistema externo');
        }

        $state = $this->generateState();

        $this->stateStore->put($this->stateKey, $state);

        $vusAuthUrl = rtrim($this->config->baseUrl, '/') . '/' . ltrim($this->config->authPath, '/');

        $query = http_build_query([
            'state'       => $state,
            'client_id'   => $this->config->clientId,
            'redirect_uri'=> $redirectUri,
        ]);

        return $vusAuthUrl . '?' . $query;
    }

    /**
     * Valida el state recibido contra el almacenado.
     * Extrae (pull) el state almacenado para evitar reusos.
     */
    public function validateState(?string $receivedState): bool
    {
        $storedState = $this->stateStore->pull($this->stateKey);

        if (!$storedState) {
            return false;
        }

        return hash_equals($storedState, (string) $receivedState);
    }

    /**
     * A partir de un id_token válido, obtiene la identidad del ciudadano como DTO.
     *
     * Envuelve la llamada a MdsfidClient::validarIdentidad y normaliza
     * la estructura a VuIdentityDTO.
     *
     * @throws MdsfidException
     */
    public function obtenerIdentidadDesdeToken(string $idToken): VuIdentityDTO
    {
        $validationData = $this->mdsfidClient->validarIdentidad($idToken);

        // Se espera que pueda venir un campo 'id_user' (string JSON) o los campos directos.
        $idUserString = $validationData['id_user'] ?? null;
        $run = null;
        $dv = '';

        if ($idUserString) {
            $decoded = json_decode($idUserString, true);
            if (is_array($decoded)) {
                $run = $decoded['run_ciudadano'] ?? null;
                $dv = (string) ($decoded['dv_ciudadano'] ?? '');
            }
        } else {
            if (isset($validationData['run_ciudadano'])) {
                $run = $validationData['run_ciudadano'];
            }
            if (isset($validationData['dv_ciudadano'])) {
                $dv = (string) $validationData['dv_ciudadano'];
            }
        }

        if (!$run) {
            throw new MdsfidException('RUN no encontrado en token/validación de identidad');
        }

        return new VuIdentityDTO(
            runCiudadano: (int) $run,
            dvCiudadano: $dv,
            rawPayload: $validationData,
        );
    }

    /**
     * Verifica si la redirect_uri pertenece a alguno de los hosts permitidos.
     */
    public function isValidRedirectUri(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $allowedHosts = array_map('trim', $this->config->allowedRedirectHosts);

        return in_array($host, $allowedHosts, true);
    }

    private function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }
}
