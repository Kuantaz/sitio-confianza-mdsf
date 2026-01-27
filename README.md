## SitioConfianzaMDSF

Librería PHP para integrar aplicaciones con **MDSFID/VUS** mediante un flujo de “sitio de confianza”.  
Separa la lógica de comunicación con MDSFID y el manejo de `id_token` de la lógica de autenticación propia de cada aplicativo (Laravel u otro framework).

---

### Características

- Cliente HTTP para MDSFID (`MdsfidClient`) con HTTP client y generación de JWT internos.
- Orquestador de flujo de autenticación con sistemas externos (`ExternalAuthFlow`).
- DTO de identidad (`VuIdentityDTO`) que normaliza los datos devueltos.
- Contratos para integrar con cualquier aplicación:
  - `LoginHandlerInterface`
  - `PostLoginRedirectInterface`
  - `StateStoreInterface`
- Soporta **múltiples integraciones** en una misma app mediante instancias configurables (`MdsfidConfig`, `ExternalConfig`).

---

### Instalación vía Composer

1. Agregar el paquete al proyecto:

```bash
composer require kuantaz/sitio-confianza-mdsf
```

2. Composer registrará automáticamente el autoload PSR-4 del namespace:

```text
Kuantaz\SitioConfianzaMDSF\
```

3. A partir de ahí podrás usar las clases de la librería directamente en tu aplicación (Laravel u otra) sin configuración adicional de autoload.

4. Configurar las variables de entorno necesarias:

```bash
API_PROCESS_MDSFID_JWT_SECRET=tu_secret_aqui
API_PROCESS_MDSFID_JWT_KEY=tu_key_aqui
```

---

### Conceptos principales

#### Configuración

- `Kuantaz\SitioConfianzaMDSF\Config\MdsfidConfig`

```php
new MdsfidConfig(
    baseUrl: 'https://api-centro.mdsf.cl',
    crearIdentidadPath: '/process/v1/mdsfid/crearidentidad',
    validarIdentidadPath: '/process/v1/mdsfid/validaridentidad',
    timeoutSeconds: 10,
);
```

- `Kuantaz\SitioConfianzaMDSF\Config\ExternalConfig`

```php
new ExternalConfig(
    baseUrl: 'https://vus.mdsf.cl',
    authPath: '/mdsfid/auth',
    clientId: 'CLIENT_ID_ENTREGADO_POR_VUS',
    allowedRedirectHosts: ['cav.mideplan.cl'],
);
```

#### Cliente MDSFID

- `Kuantaz\SitioConfianzaMDSF\MdsfidClient`

El cliente maneja internamente la comunicación HTTP (usando Guzzle) y la generación de tokens JWT.

Requiere:

- `MdsfidConfig`
- `string $secret` - Secret para la generación del JWT (usar `API_PROCESS_MDSFID_JWT_SECRET`)
- `string $key` - Key ID para el JWT (usar `API_PROCESS_MDSFID_JWT_KEY`)

Ejemplo de uso:

```php
$mdsfidClient = new MdsfidClient(
    config: $mdsfidConfig,
    secret: env('API_PROCESS_MDSFID_JWT_SECRET', 'Kdt682W+'),
    key: env('API_PROCESS_MDSFID_JWT_KEY', 'mdsfid-dev'),
);

$idToken = $mdsfidClient->crearIdentidad($idUserJson, $clientId, $redirectUri);

$payload = $mdsfidClient->validarIdentidad($idToken); // array
```

#### Flujo de Autenticación Externa

- `Kuantaz\SitioConfianzaMDSF\ExternalAuthFlow`

Requiere:

- `ExternalConfig`
- `MdsfidClient`
- `StateStoreInterface` (para guardar/recuperar el `state`)

Métodos principales:

```php
// 1. Inicio de login: construir URL del sistema externo
$authUrl = $externalAuthFlow->startLogin($redirectUri); // redirigir a esta URL

// 2. Validar state a la vuelta
$isValid = $externalAuthFlow->validateState($receivedState);

// 3. Obtener identidad a partir de id_token
$identityDto = $externalAuthFlow->obtenerIdentidadDesdeToken($idToken); // VuIdentityDTO
```

---

### Integración típica con Laravel (ejemplo simplificado)

#### StateStore con sesión

```php
use Kuantaz\SitioConfianzaMDSF\Contracts\StateStoreInterface;

class LaravelSessionStateStore implements StateStoreInterface
{
    public function put(string $key, string $value): void
    {
        session()->put($key, $value);
    }

    public function pull(string $key): ?string
    {
        return session()->pull($key);
    }
}
```

#### LoginHandler

```php
use Kuantaz\SitioConfianzaMDSF\Contracts\LoginHandlerInterface;
use Kuantaz\SitioConfianzaMDSF\DTO\VuIdentityDTO;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class LaravelVuLoginHandler implements LoginHandlerInterface
{
    public function login(VuIdentityDTO $identity): mixed
    {
        $user = User::where('run', $identity->runCiudadano)->first();

        if (! $user) {
            return null; // o lanzar excepción / resultado específico
        }

        Auth::login($user);

        return $user;
    }
}
```

#### Uso en un controlador

```php
public function start()
{
    $redirectUri = route('vu.confirmar');

    $authUrl = $this->externalAuthFlow->startLogin($redirectUri);

    return redirect()->away($authUrl);
}

public function confirm(Request $request)
{
    $state = $request->input('state');
    $idToken = $request->input('id_token');

    if (! $this->externalAuthFlow->validateState($state)) {
        // manejar error de CSRF / state inválido
    }

    $identity = $this->externalAuthFlow->obtenerIdentidadDesdeToken($idToken);

    $user = $this->loginHandler->login($identity);

    // redirigir según el resultado de login...
}
```

---

### Multi-integración

Puedes crear tantas instancias de `MdsfidClient` y `ExternalAuthFlow` como necesites, cada una con su propio par `MdsfidConfig` / `ExternalConfig`, permitiendo manejar más de una integración VUS/MDSFID en la misma aplicación.

### Variables de Entorno Requeridas

La librería requiere las siguientes variables de entorno para funcionar correctamente:

- `API_PROCESS_MDSFID_JWT_SECRET`: Secret utilizado para la generación de tokens JWT
- `API_PROCESS_MDSFID_JWT_KEY`: Key ID utilizado en la generación de tokens JWT

Estas variables deben estar configuradas en tu aplicación antes de instanciar `MdsfidClient`.

---

### Licencia

MIT.

