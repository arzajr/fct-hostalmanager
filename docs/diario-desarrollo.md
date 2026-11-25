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