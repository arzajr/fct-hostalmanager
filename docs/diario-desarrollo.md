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

## 27 de noviembre de 2026 · Huéspedes, reservas, tarifas y personal del establecimiento

Retomé el diagrama con la habitación `201` como ejemplo. Si una huésped vuelve a alojarse en ella, se crea una reserva con otras fechas y se reutilizan la ficha de la huésped y el registro de la habitación. Como `rooms` ya existía, creé primero `guests` y después `reservations`. Así, las dos tablas referenciadas por la reserva ya existían al ejecutar su migración.

### Creación y revisión de las migraciones

| Tabla | Trabajo realizado |
|---|---|
| `guests` | Creé la ficha del huésped con `first_name`, `last_name`, `email` y `phone` obligatorios. Definí el teléfono como texto de hasta 30 caracteres para conservar prefijos, espacios y ceros iniciales. |
| `reservations` | Vinculé cada reserva con `guests.id` mediante `guest_id` y con `rooms.id` mediante `room_id`. Para reservar la `201` se guarda el identificador interno de la habitación. Añadí `check_in`, `check_out`, `status` y `total_price`. Durante la revisión corregí una declaración duplicada de `check_in`. |
| `room_rates` | Definí el precio por noche en `price_per_night` y su periodo de vigencia mediante `valid_from` y `valid_until`. Corregí el nombre de la clave foránea a `room_type_id`, que referencia `room_types.id`. Así, las habitaciones del mismo tipo comparten la tarifa correspondiente al periodo. |
| `users` | Inspeccioné la tabla con `sail artisan db:table users` y comprobé en Tinker que contenía **0 usuarios**. Con `Schema::table()` añadí la clave foránea obligatoria `establishment_id` y el campo `role`, que admite `admin` y `staff`, con `staff` por defecto. En `down()` definí la retirada de la clave foránea antes de eliminar las dos columnas añadidas. |

Las tres tablas nuevas incluyen `id`, `created_at` y `updated_at`. Para `price_per_night` y `total_price` utilicé `DECIMAL(10, 2)`, con diez dígitos en total y dos posiciones decimales.

Desde `aplicacion/`, revisé el SQL con `sail artisan migrate --pretend` para comprobar los tipos, los valores por defecto y las referencias generadas por `constrained()`. Después ejecuté `sail artisan migrate`. Con ello quedaron representadas en MariaDB las **siete tablas del modelo**. Creé seis mediante migraciones propias y amplié la tabla `users` que Laravel ya había creado.

### Confirmación, disponibilidad y precio de la reserva

Revisé la propuesta inicial de confirmación automática y decidí que **recepción confirmará las solicitudes desde el panel de administración**. Una solicitud nueva nace en `pending`. Para la consulta de disponibilidad, decidí que `pending` y `confirmed` bloquean las noches solicitadas y que `cancelled` las libera. El día de salida queda disponible para otra entrada. La comprobación de disponibilidad, la confirmación desde el panel y el aviso al huésped quedan pendientes de implementación.

También definí cuándo se fija el precio. Para crear una reserva pendiente, cada noche debe tener un precio inequívoco. El total que ve el huésped se guarda en `reservations.total_price` y se mantiene si el hotel actualiza sus tarifas antes o después de confirmar. Al guardar un importe calculado, asumí el riesgo de que quede desactualizado si cambia la estancia. Por eso, si se modifican las fechas o la habitación de una reserva pendiente, la aplicación deberá volver a comprobar la disponibilidad, recalcular el total y obtener la aceptación del huésped antes de guardar el cambio.

### Comprobación de claves foráneas e índices

Después de aplicar las migraciones, inspeccioné `rooms`, `room_types`, `users` y `reservations` con `sail artisan db:table`. En las cuatro tablas comprobé que las claves foráneas mostraban `restrict / restrict` para actualización y borrado.

Esta comprobación aclaró dos consecuencias para el proyecto. MariaDB impide eliminar un establecimiento mientras tenga una habitación, un tipo de habitación o una cuenta de personal vinculada, aunque nunca haya recibido reservas. También impide eliminar un huésped o una habitación mientras alguna reserva los referencie. **Cancelar una reserva libera las noches y conserva el historial**, porque la fila sigue vinculada al huésped y a la habitación.

Al revisar los índices encontré una diferencia respecto al `slug` único de `establishments`. `RoomSeeder` localiza una habitación por `establishment_id` y `number`, y `RoomTypeSeeder` localiza un tipo por `establishment_id` y `name`. Comprobé que ninguna de las dos tablas tiene un índice único sobre esa pareja. Por tanto, MariaDB permite registrar dos habitaciones `201` o dos tipos `Doble` dentro del mismo hotel. La inspección demuestra que el esquema admite duplicados.

Detecté además que las dos claves foráneas de `rooms` comprueban por separado que existen el establecimiento y el tipo de habitación. Esto permite asignar a una habitación de un hotel un tipo que pertenece a otro. Decidí conservar `rooms.establishment_id` para consultar directamente las habitaciones de cada hotel y validar la coincidencia en Laravel al crear o modificar la habitación. También habrá que impedir el traslado de un tipo a otro establecimiento mientras tenga habitaciones asignadas. Una escritura directa en MariaDB podría eludir esa validación.

### Seeders y cierre de la sesión

Como práctica, recorrí con `foreach` un array con los tipos Individual, Doble y Triple y sus capacidades de 1, 2 y 3 personas. El dato de prueba que comprobé en Tinker fue la habitación `201` del Hotel Restaurante Las Vegas, vinculada al tipo Doble.

Actualicé `02_BASE_DE_DATOS.md` con las restricciones comprobadas, las reglas acordadas y las decisiones pendientes. Dejé identificados los siguientes pasos:

- **Integridad de los datos.** Comprobar si hay números de habitación o nombres de tipo repetidos dentro de un mismo establecimiento. Verificar también si existe el `CHECK` de fechas y si alguna reserva incumple `check_out > check_in`. Después, preparar una migración nueva para añadir los dos `UNIQUE` compuestos y el `CHECK` si falta. Preví validar las fechas también en Laravel para poder explicar el error al usuario.
- **Tarifas.** Decidir cómo tratar periodos solapados, cómo calcular una estancia que atraviesa un cambio de tarifa y si `valid_until` puede quedar vacío para expresar una vigencia indefinida.
- **Mantenimiento.** Definir qué debe hacer recepción si una habitación pasa a `maintenance` y ya tiene reservas confirmadas para fechas futuras.
- **Documentación.** Actualizar el diagrama relacional con el esquema aplicado y reflejar en la documentación funcional el flujo de solicitud del huésped y confirmación por recepción.s