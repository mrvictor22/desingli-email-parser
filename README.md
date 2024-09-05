
# Laravel Project: SES Email Parser

This project is a Laravel-based implementation that exposes an endpoint to transform an AWS Simple Email Service (SES) event JSON into a custom structure using a **custom mapper** (`SesMapperService`). The project is fully dockerized using Laravel Sail.

## Table of Contents
- [Installation](#installation)
- [Project Structure](#project-structure)
  - [SesEvent Model](#sesevent-model)
  - [SesMapperService Custom Mapper](#sesmapperservice-custom-mapper)
  - [TransformedEvent Class](#transformedevent-class)
  - [Controller and Routes](#controller-and-routes)
  - [Service Registration in AppServiceProvider](#service-registration-in-appserviceprovider)
  - [Disabling CSRF Validation for Testing](#disabling-csrf-validation-for-testing)
- [Docker Integration](#docker-integration)
- [Usage](#usage)
- [Conclusion](#conclusion)

---

## Installation

To set up the project, follow these steps:

1. **Clone the repository**:

   ```bash
   git clone https://github.com/mrvictor22/desingli-email-parser.git
   ```

2. **Navigate to the project directory**:

   ```bash
   cd ses-email-parser
   ```

3. **Install dependencies**:

   Install the Laravel dependencies using Composer:

   ```bash
   composer install
   ```

4. **Set up environment variables**:

   Copy the `.env.example` file to `.env`:

   ```bash
   cp .env.example .env
   ```

   Adjust the `.env` file with the correct environment values, especially database configuration.

5. **Run Docker (Laravel Sail)**:

   The project is dockerized using Laravel Sail. If you haven't installed Sail, run the following command:

   ```bash
   ./vendor/bin/sail up
   ```

   This will spin up the application with all necessary services like MySQL.

6. **Generate application key**:

   ```bash
   ./vendor/bin/sail artisan key:generate
   ```

7. **Run migrations**:

   Run database migrations (optional, depending on your use case):

   ```bash
   ./vendor/bin/sail artisan migrate
   ```

---

## Project Structure

### SesEvent Model

The `SesEvent` model is used to represent the structure of the SES event JSON received by the application. This model simplifies the handling of SES data by organizing it into structured properties.

```php
namespace App\Models;

class SesEvent
{
    public $eventVersion;
    public $receipt;
    public $mail;
    public $eventSource;

    public function __construct($json)
    {
        $this->eventVersion = $json['eventVersion'];
        $this->receipt = $json['ses']['receipt'];
        $this->mail = $json['ses']['mail'];
        $this->eventSource = $json['eventSource'];
    }
}
```

This model extracts the key sections from the incoming JSON object, such as `eventVersion`, `receipt`, `mail`, and `eventSource`, providing an easy-to-use structure for the transformation process.

---

### SesMapperService Custom Mapper

The `SesMapperService` is the custom service that maps the structure of the `SesEvent` model into the desired output format. This service is responsible for transforming the data according to business rules.

```php
namespace App\Services;

use AutoMapperPlus\AutoMapper;
use AutoMapperPlus\Configuration\AutoMapperConfig;
use App\Models\SesEvent;
use App\DTO\TransformedEvent;

class SesMapperService
{
    protected $mapper;

    public function __construct()
    {
        $config = new AutoMapperConfig();
        $config->registerMapping(SesEvent::class, TransformedEvent::class)
            ->forMember('spam', function (SesEvent $source) {
                return $source->receipt['spamVerdict']['status'] === 'PASS';
            })
            ->forMember('virus', function (SesEvent $source) {
                return $source->receipt['virusVerdict']['status'] === 'PASS';
            })
            ->forMember('dns', function (SesEvent $source) {
                return $source->receipt['spfVerdict']['status'] === 'PASS' &&
                       $source->receipt['dkimVerdict']['status'] === 'PASS' &&
                       $source->receipt['dmarcVerdict']['status'] === 'PASS';
            })
            ->forMember('mes', function (SesEvent $source) {
                return date('F', strtotime($source->mail['timestamp']));
            })
            ->forMember('retrasado', function (SesEvent $source) {
                return $source->receipt['processingTimeMillis'] > 1000;
            })
            ->forMember('emisor', function (SesEvent $source) {
                return explode('@', $source->mail['source'])[0];
            })
            ->forMember('receptor', function (SesEvent $source) {
                return array_map(function ($recipient) {
                    return explode('@', $recipient)[0];
                }, $source->mail['destination']);
            });

        $this->mapper = new AutoMapper($config);
    }

    public function map(SesEvent $sesEvent): TransformedEvent
    {
        return $this->mapper->map($sesEvent, TransformedEvent::class);
    }
}
```

The mapper uses the `AutoMapperPlus` library to transform each property of the `SesEvent` model into a corresponding property of the `TransformedEvent` class, following custom rules.

---

### TransformedEvent Class

The `TransformedEvent` class represents the structure of the transformed data that will be returned by the API. The custom mapper (`SesMapperService`) fills this structure with the processed values.

```php
namespace App\DTO;

class TransformedEvent
{
    public $spam;
    public $virus;
    public $dns;
    public $mes;
    public $retrasado;
    public $emisor;
    public $receptor;
}
```

This class holds the transformed values after applying the business logic defined in the `SesMapperService`.

---

### Controller and Routes

The controller, `SesEventController`, handles incoming requests and uses the `SesMapperService` to process and transform the SES event JSON.

#### Controller (`SesEventController.php`):

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SesEvent;
use App\Services\SesMapperService;

class SesEventController extends Controller
{
    protected $mapperService;

    public function __construct(SesMapperService $mapperService)
    {
        $this->mapperService = $mapperService;
    }

    public function transform(Request $request)
    {
        // Convert the incoming JSON to SesEvent model
        $sesEvent = new SesEvent($request->input('Records')[0]);

        // Use the service to map the SesEvent model to the desired structure
        $transformedEvent = $this->mapperService->map($sesEvent);

        // Return the transformed JSON
        return response()->json($transformedEvent);
    }
}
```

#### Routes (`routes/web.php`):

```php
use App\Http\Controllers\SesEventController;

Route::post('/transform', [SesEventController::class, 'transform']);
```

The `/transform` route accepts a POST request with the SES event JSON and returns the transformed JSON.

---

### Service Registration in AppServiceProvider

The `SesMapperService` is registered as a singleton in the `AppServiceProvider`. This ensures the service is only instantiated once per application lifecycle.

#### `AppServiceProvider.php`:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SesMapperService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SesMapperService::class, function () {
            return new SesMapperService();
        });
    }

    public function boot(): void
    {
        //
    }
}
```

---

### Disabling CSRF Validation for Testing

For testing purposes, CSRF validation has been disabled for the `/transform` route. This is done in the `bootstrap/app.php` file.

```php
use App\Http\Middleware\VerifyCsrfToken;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            '/transform' // Exclude this route from CSRF validation
        ]);
    })
    ->create();
```

This configuration disables the CSRF token verification for the `/transform` route to avoid `419 - Page Expired` errors during development.

---

## Docker Integration

The project is dockerized using **Laravel Sail**. This simplifies the development environment by running the application in isolated containers for PHP, MySQL, and Nginx.

### Docker Setup

- **Dockerfile**: Defines the PHP environment with necessary extensions.
- **docker-compose.yml**: Includes the services for PHP (Laravel Sail), MySQL, and Nginx.
  
To bring up the Docker environment, simply run:

```bash
./vendor/bin/sail up
```

This command will start the Laravel application along with MySQL.

---

## Usage

1. **Start the application**:

   If you have Laravel Sail installed, run the application using:

   ```bash
   ./vendor/bin/sail up
   ```

2. **Test the `/transform` endpoint**:

   Send a POST request to the `/transform` route using a tool like Postman or cURL:

   ```bash
   curl -X POST http://localhost:80/transform -H

` "Content-Type: application/json" -d '{
       "Records": [...]
   }'`
   ```

---

Ensure that the body of the request contains the correct SES event JSON.

3. **Receive the transformed response**:

   The API will return the transformed JSON based on the custom structure defined in `TransformedEvent`.

