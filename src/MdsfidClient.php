<?php

namespace Kuantaz\SitioConfianzaMDSF;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Kuantaz\SitioConfianzaMDSF\Config\MdsfidConfig;
use Kuantaz\SitioConfianzaMDSF\Exceptions\MdsfidException;

/**
 * Cliente HTTP para MDSFID.
 *
 * Esta clase maneja internamente la comunicación HTTP y la generación de tokens JWT.
 */
class MdsfidClient
{
    private readonly Client $httpClient;

    public function __construct(
        public readonly MdsfidConfig $config,
        private readonly string $secret,
        private readonly string $key,
    ) {
        $this->httpClient = new Client([
            'timeout' => $this->config->timeoutSeconds,
        ]);
    }

    /**
     * Genera un token JWT para autenticarse contra MDSFID.
     */
    private function generateJwtToken(): string
    {
        $payload = [
            'iat' => time(),
            'exp' => time() + 3600, // 1 hora de validez
        ];

        return JWT::encode($payload, $this->secret, 'HS256', $this->key);
    }

    /**
     * Realiza una petición HTTP usando Guzzle.
     *
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $url URL completa
     * @param array $options Opciones de Guzzle
     * @return array Con 'status', 'body' y 'json'
     * @throws MdsfidException
     */
    private function makeHttpRequest(string $method, string $url, array $options): array
    {
        try {
            $response = $this->httpClient->request($method, $url, $options);
            $status = $response->getStatusCode();
            $body = (string) $response->getBody();
            $json = null;

            $contentType = $response->getHeaderLine('Content-Type');
            if (str_contains($contentType, 'application/json')) {
                $json = json_decode($body, true);
            }

            return [
                'status' => $status,
                'body' => $body,
                'json' => $json,
            ];
        } catch (GuzzleException $e) {
            throw new MdsfidException('Error en comunicación HTTP con MDSFID: ' . $e->getMessage());
        }
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

        $token = $this->generateJwtToken();

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'id_user'   => $idUserJson,
                'id_client' => $clientId,
                'uri'       => $redirectUri,
            ],
        ];

        $response = $this->makeHttpRequest('POST', $url, $options);

        $status = (int) ($response['status'] ?? 0);
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

        $token = $this->generateJwtToken();

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'query' => [
                'id_token' => $idToken,
            ],
        ];

        $response = $this->makeHttpRequest('GET', $url, $options);

        $status = (int) ($response['status'] ?? 0);
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
