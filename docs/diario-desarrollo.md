# Diario de desarrollo de HostalManager

## Octubre de 2026 · Comprobación de salud de MariaDB

Durante la primera preparación del entorno, el contenedor de MariaDB estaba en ejecución, pero Docker mostraba el estado `unhealthy`.

Al revisar la salida de la comprobación encontré el mensaje `Unknown healthcheck option ping`. La opción `ping` era incompatible con el script utilizado. Configuré en `compose.yaml` el comando `healthcheck.sh --connect --innodb_initialized` y comprobé con `docker compose ps` que MariaDB pasaba al estado `healthy`.



## 24 de noviembre de 2026 · Creación de la aplicación base

Creé la aplicación base de Laravel 13 en `aplicacion/` usando Composer desde un contenedor temporal de Docker. Antes de guardarla, comprobé con `git add --dry-run` qué archivos incluiría Git. Verifiqué que se incluían `.env.example` y `composer.lock`, y que `.env` y `vendor/` quedaban excluidos.

Commit de referencia: `febe555`.



## 25 de noviembre de 2026 · Primer arranque con Sail y MariaDB

### Objetivo

Arrancar HostalManager con Laravel Sail, comprobar la conexión con MariaDB y abrir la aplicación desde el navegador.

### Configuración y arranque

Instalé Sail 1.71.0 como dependencia de desarrollo y generé `compose.yaml` con los servicios `laravel.test` y `mariadb`. Cambié la etiqueta de la imagen de MariaDB de `11` a `11.4` para ajustarla al diseño del proyecto.

La imagen de aplicación generada por Sail utiliza PHP 8.5. Anoté esta diferencia respecto al PHP 8.3 indicado en el diagrama de arquitectura para actualizar la documentación técnica.

Ejecuté `./vendor/bin/sail up -d`. Durante el arranque apareció un aviso al intentar descargar `sail-8.5/app`. La salida final mostró `Built`: Docker había construido la imagen localmente y pudo iniciar ambos contenedores. Con `./vendor/bin/sail ps` comprobé que Laravel estaba en ejecución y que MariaDB aparecía como `healthy`.

### Incidencia al abrir la aplicación

Al abrir `http://localhost` por primera vez, Laravel mostró el error `Table 'laravel.sessions' doesn't exist`.

El nombre de la tabla en el error me permitió localizar el problema. Laravel había conseguido conectar con MariaDB y ejecutar una consulta en la base `laravel`. Faltaba la tabla `sessions`, que la aplicación intentaba consultar para cargar la sesión del navegador.

Ejecuté `./vendor/bin/sail artisan migrate` para crear las tablas iniciales y recargué la página. Esta vez apareció la bienvenida de Laravel.

### Resultado

El entorno local quedó operativo. Comprobé que el navegador llega al contenedor de Laravel por el puerto 80 y que Laravel puede consultar MariaDB. La incidencia me ayudó a distinguir dos comprobaciones: el estado `healthy` indica que el servicio de base de datos está preparado, y las migraciones crean las tablas que necesita la aplicación.



## 26 de noviembre de 2026 · Migraciones de establecimientos, tipos de habitación y habitaciones

Hoy creé las tres primeras tablas propias de HostalManager en MariaDB. Empecé por
`establishments`, continué con `room_types` y terminé con `rooms`. Elegí ese
orden porque cada tipo de habitación pertenece a un establecimiento y cada
habitación hace referencia tanto a su establecimiento como a su tipo.

### Tabla `establishments`

Desde `~/fct-hostalmanager/aplicacion`, generé el archivo de migración con Artisan.

```bash
./vendor/bin/sail artisan make:migration create_establishments_table
```

Decidí que el teléfono, la dirección, la ciudad y el código postal fueran
obligatorios para registrar un establecimiento. De esta forma, cada hostal
tiene una ubicación y un teléfono de contacto. El correo electrónico es
opcional. También definí `slug` como único para que dos establecimientos
no puedan compartir el mismo identificador en la URL.

```php
Schema::create('establishments', function (Blueprint $table) {
    $table->id();
    $table->string('name', 150);
    $table->string('slug', 150)->unique();
    $table->string('email', 255)->nullable();
    $table->string('phone', 30);
    $table->string('address', 255);
    $table->string('city', 100);
    $table->string('postal_code', 20);
    $table->timestamps();
});
```

Antes de aplicar la migración, revisé el SQL que generaría Laravel.

```bash
./vendor/bin/sail artisan migrate --pretend
```

En la salida comprobé que `email` admitía `NULL` y que los campos obligatorios
aparecían como `NOT NULL`. También observé que Laravel generaba un `CREATE TABLE`
para crear la tabla y un `ALTER TABLE` para añadir la restricción de unicidad
de `slug`.

A continuación, apliqué la migración en MariaDB.

```bash
./vendor/bin/sail artisan migrate
```

```text
2026_11_26_164838_create_establishments_table ... 59.36ms DONE
```

### Alias de Sail en WSL

Para abreviar los comandos, añadí este alias al final de `~/.bashrc`.

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

Cargué la nueva configuración en la terminal que tenía abierta y probé el alias
desde la carpeta `aplicacion/`.

```bash
source ~/.bashrc
sail artisan migrate --pretend
```

Artisan respondió `Nothing to migrate`. En ese momento ya había aplicado la
migración de `establishments` y todavía no había creado la de `room_types`.

### Tabla `room_types`

Generé la migración para guardar los tipos de habitación que ofrece cada
establecimiento, por ejemplo, «Doble» con capacidad para dos personas.

```bash
sail artisan make:migration create_room_types_table
```

```php
Schema::create('room_types', function (Blueprint $table) {
    $table->id();
    $table->foreignId('establishment_id')->constrained();
    $table->string('name', 100);
    $table->unsignedTinyInteger('capacity');
    $table->text('description')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

Definí `establishment_id` como clave foránea que apunta a `establishments.id`.
Así, MariaDB exige que el establecimiento exista antes de registrar un tipo de
habitación asociado a él.

Al revisar la migración con `sail artisan migrate --pretend`, vi que faltaban
`description` y `active` en el SQL. Había escrito las dos líneas en VS Code,
pero todavía no había guardado el archivo. Lo guardé y repetí la comprobación.
Esta vez aparecieron la descripción opcional y el campo `active` con valor
predeterminado `1` (`true`). Después ejecuté `sail artisan migrate`.

### Tabla `rooms`

Generé la migración para las habitaciones físicas de cada establecimiento,
como la habitación `201`.

```bash
sail artisan make:migration create_rooms_table
```

```php
Schema::create('rooms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('establishment_id')->constrained();
    $table->foreignId('room_type_id')->constrained();
    $table->string('number', 20);
    $table->enum('operational_status', ['active', 'maintenance'])->default('active');
    $table->text('description')->nullable();
    $table->timestamps();
});
```

Guardé el número de puerta como texto para conservar identificadores como
`001` o `201A`. Para el estado operativo elegí un `ENUM` con dos valores:
`active` y `maintenance`. Una habitación se registra como `active` por defecto.
Este campo permite indicar si está en mantenimiento. Para saber si está libre
durante una estancia, más adelante consultaré las reservas de esas fechas.

Antes de aplicar la migración, ejecuté `sail artisan migrate --pretend`.
Revisé los valores del `ENUM` y comprobé el destino de las dos claves foráneas
en las cláusulas `REFERENCES` del SQL.

| Columna de `rooms` | Columna a la que hace referencia |
|---|---|
| `establishment_id` | `establishments.id` |
| `room_type_id` | `room_types.id` |

Por último, ejecuté `sail artisan migrate` para crear `rooms` en MariaDB.

### Cierre de la sesión

Quedaron creadas `establishments`, `room_types` y `rooms`. El siguiente paso
será cargar un establecimiento, un tipo de habitación y una habitación de
ejemplo mediante un seeder.

Queda pendiente actualizar el diagrama relacional para reflejar las decisiones
que cambiaron respecto al diseño inicial: el teléfono, la dirección y la ciudad
pasaron a ser obligatorios, y `rooms.operational_status` pasó de `VARCHAR(20)`
a `ENUM('active', 'maintenance')`.

### Carga de datos de prueba con seeders

Creé tres seeders en `aplicacion/database/seeders/` para representar un caso de prueba: la habitación `201` del Hotel Restaurante Las Vegas, de tipo `Doble` y con capacidad para dos personas.

Los ejecuté primero de forma individual. Empecé por el establecimiento porque necesitaba su `id` para registrar el tipo de habitación. Después utilicé ambos identificadores para crear la habitación física.

| Seeder | Registro de prueba | Campos utilizados para localizarlo |
|---|---|---|
| `EstablishmentSeeder` | Hotel Restaurante Las Vegas | `slug` |
| `RoomTypeSeeder` | Tipo `Doble`, para dos personas y con camas separadas | `establishment_id` y `name` |
| `RoomSeeder` | Habitación `201` de tipo `Doble` | `establishment_id` y `number` |

En los tres seeders utilicé `updateOrInsert()` para poder repetir la carga. El primer array indica cómo localizar el registro y el segundo contiene los datos que quiero guardar. Por ejemplo, el seeder busca la habitación por su establecimiento y su número de puerta. Si encuentra la `201`, actualiza su `room_type_id`. Si es nueva, la crea con los datos de ambos arrays.

Para obtener las claves foráneas utilicé `value('id')`. Busqué el hotel por su `slug` y el tipo `Doble` por el nombre y el identificador del hotel. Así, los seeders funcionan aunque MariaDB asigne identificadores distintos en otra instalación.

### Comprobación de las relaciones

Desde Tinker consulté la habitación `201`. Utilicé un `join` para recuperar el nombre del hotel mediante `rooms.establishment_id` y otro para recuperar el tipo mediante `rooms.room_type_id`. La consulta devolvió una sola fila:

| Habitación | Establecimiento | Tipo |
|---|---|---|
| `201` | Hotel Restaurante Las Vegas | Doble |

### Ejecución conjunta y cierre de la sesión

En `DatabaseSeeder.php` quité la llamada que creaba el usuario de ejemplo y configuré el método `run()` para ejecutar los tres seeders en orden:

```php
$this->call([
    EstablishmentSeeder::class,
    RoomTypeSeeder::class,
    RoomSeeder::class,
]);
```

Desde `aplicacion/`, repetí la carga completa con un único comando:

```bash
sail artisan db:seed
```

Los tres seeders terminaron con `DONE` en el orden previsto. La habitación `201` quedó preparada como dato de prueba para el módulo de reservas. Queda pendiente actualizar el diagrama relacional con los cambios anotados al crear las migraciones.