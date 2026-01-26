<?php

namespace Kuantaz\SitioConfianzaMDSF;

use Kuantaz\SitioConfianzaMDSF\Config\MdsfidConfig;
use Kuantaz\SitioConfianzaMDSF\Exceptions\MdsfidException;

/**
 * Cliente HTTP genérico para MDSFID.
 *
 * Nota: esta clase NO depende de Laravel. El token JWT y el cliente HTTP
 * concreto se inyectarán desde la aplicación que use la librería.
 */
class MdsfidClient
{
    public function __construct(
        public readonly MdsfidConfig $config,
        /**
         * Cliente HTTP genérico.
         *
         * Debe ser un callable con firma:
         *   function (string $method, string $url, array $options): array
         *
         * Y retornar un arreglo con al menos:
         *   - 'status' (int)
         *   - 'body' (string)
         *   - 'json' (array|null) si la respuesta es JSON parseado
         */
        private readonly callable $httpClient,
        /**
         * Proveedor de token JWT para autenticarse contra MDSFID.
         *
         * Firma:
         *   function (): string
         */
        private readonly callable $jwtTokenProvider,
    ) {
    }

    /**
     * Llama al endpoint crearidentidad de API Centro.
     *
     * @param string $idUserJson JSON string con {run_ciudadano, dv_ciudadano}
     * @param string $clientId   ID del cliente (VU)
     * @param string $redirectUri URI de redirección
     *
     * @throws MdsfidException
     */
    public function crearIdentidad(string $idUserJson, string $clientId, string $redirectUri): string
    {
        $baseUrl = $this->config->baseUrl;
        $path = $this->config->crearIdentidadPath;

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        $token = ($this->jwtTokenProvider)();

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => $this->config->timeoutSeconds,
            'json' => [
                'id_user'   => $idUserJson,
                'id_client' => $clientId,
                'uri'       => $redirectUri,
            ],
        ];

        $response = ($this->httpClient)('POST', $url, $options);

        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');
        $data = $response['json'] ?? null;

        if ($status < 200 || $status >= 300 || !is_array($data)) {
            throw new MdsfidException('Error en comunicación con MDSFID (crearidentidad)');
        }

        if (!isset($data['data']['id_token'])) {
            throw new MdsfidException('Respuesta inválida de MDSFID: falta id_token');
        }

        return (string) $data['data']['id_token'];
    }

    /**
     * Valida el id_token recibido desde VUS mediante MDSFID.
     *
     * @return array Payload con datos del usuario (run_ciudadano, etc.)
     *
     * @throws MdsfidException
     */
    public function validarIdentidad(string $idToken): array
    {
        $baseUrl = $this->config->baseUrl;
        $path = $this->config->validarIdentidadPath;

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        $token = ($this->jwtTokenProvider)();

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'timeout' => $this->config->timeoutSeconds,
            'query' => [
                'id_token' => $idToken,
            ],
        ];

        $response = ($this->httpClient)('GET', $url, $options);

        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');
        $data = $response['json'] ?? null;

        if ($status < 200 || $status >= 300 || !is_array($data)) {
            throw new MdsfidException('Error validando identidad con MDSFID');
        }

        if (!isset($data['code'])) {
            throw new MdsfidException('Error validando identidad con MDSFID (sin code)');
        }

        if ($data['code'] !== 1000) {
            throw new MdsfidException('Error validando identidad con MDSFID (code distinto de 1000)');
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new MdsfidException('Respuesta inválida de MDSFID (sin data)');
        }

        return $data['data'];
    }
}

