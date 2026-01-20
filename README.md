## SitioConfianzaMDSF

Librería PHP para integrar aplicaciones con **MDSFID/VUS** mediante un flujo de “sitio de confianza”.  
Separa la lógica de comunicación con MDSFID y el manejo de `id_token` de la lógica de autenticación propia de cada aplicativo (Laravel u otro framework).

---

### Características

- Cliente HTTP genérico para MDSFID (`MdsfidClient`).
- Orquestador de flujo de autenticación con VUS/MDSFID (`VuAuthFlow`).
- DTO de identidad (`VuIdentityDTO`) que normaliza los datos devueltos.
- Contratos para integrar con cualquier aplicación:
  - `LoginHandlerInterface`
  - `PostLoginRedirectInterface`
  - `StateStoreInterface`
- Soporta **múltiples integraciones** en una misma app mediante instancias configurables (`MdsfidConfig`, `VuConfig`).

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

- `Kuantaz\SitioConfianzaMDSF\Config\VuConfig`

```php
new VuConfig(
    baseUrl: 'https://vus.mdsf.cl',
    authPath: '/mdsfid/auth',
    clientId: 'CLIENT_ID_ENTREGADO_POR_VUS',
    allowedRedirectHosts: ['cav.mideplan.cl'],
);
```

#### Cliente MDSFID

- `Kuantaz\SitioConfianzaMDSF\MdsfidClient`

Requiere:

- `MdsfidConfig`
- `callable $httpClient`  
  Firma: `function (string $method, string $url, array $options): array`
- `callable $jwtTokenProvider`  
  Firma: `function (): string`
- `Psr\Log\LoggerInterface|null` (opcional)

Métodos clave:

```php
$idToken = $mdsfidClient->crearIdentidad($idUserJson, $clientId, $redirectUri);

$payload = $mdsfidClient->validarIdentidad($idToken); // array
```

#### Flujo VUS

- `Kuantaz\SitioConfianzaMDSF\VuAuthFlow`

Requiere:

- `VuConfig`
- `MdsfidClient`
- `StateStoreInterface` (para guardar/recuperar el `state`)

Métodos principales:

```php
// 1. Inicio de login: construir URL de VUS
$authUrl = $vuAuthFlow->startLogin($redirectUri); // redirigir a esta URL

// 2. Validar state a la vuelta
$isValid = $vuAuthFlow->validateState($receivedState);

// 3. Obtener identidad a partir de id_token
$identityDto = $vuAuthFlow->obtenerIdentidadDesdeToken($idToken); // VuIdentityDTO
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

    $authUrl = $this->vuAuthFlow->startLogin($redirectUri);

    return redirect()->away($authUrl);
}

public function confirm(Request $request)
{
    $state = $request->input('state');
    $idToken = $request->input('id_token');

    if (! $this->vuAuthFlow->validateState($state)) {
        // manejar error de CSRF / state inválido
    }

    $identity = $this->vuAuthFlow->obtenerIdentidadDesdeToken($idToken);

    $user = $this->loginHandler->login($identity);

    // redirigir según el resultado de login...
}
```

---

### Multi-integración

Puedes crear tantas instancias de `MdsfidClient` y `VuAuthFlow` como necesites, cada una con su propio par `MdsfidConfig` / `VuConfig`, permitiendo manejar más de una integración VUS/MDSFID en la misma aplicación.

---

### Licencia

MIT.

