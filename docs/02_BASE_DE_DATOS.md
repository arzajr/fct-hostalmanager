# Base de datos de HostalManager

## 26/11/2026 · Establecimientos, tipos de habitación y habitaciones

Creé y apliqué tres migraciones propias en `aplicacion/database/migrations/`. Empecé por el establecimiento, después definí los tipos de habitación que ofrece y, por último, las habitaciones físicas. Este orden permite que las tablas referenciadas por las claves foráneas ya existan al ejecutar cada migración.

| Tabla | Decisiones aplicadas |
|---|---|
| `establishments` | El `slug` es único. El teléfono, la dirección, la ciudad y el código postal son obligatorios. El correo electrónico es opcional. |
| `room_types` | Cada tipo pertenece a un establecimiento mediante `establishment_id`. La capacidad se guarda como un entero sin signo, la descripción es opcional y el campo `active` toma el valor verdadero por defecto. |
| `rooms` | Cada habitación referencia su establecimiento mediante `establishment_id` y su tipo mediante `room_type_id`. Su estado operativo admite `active` o `maintenance`, con `active` como valor inicial. |

### Comprobación y ejecución

Desde la carpeta `aplicacion/`, revisé el SQL que Laravel iba a ejecutar:

```bash
./vendor/bin/sail artisan migrate --pretend
```

En la primera revisión faltaban campos que había editado en VS Code. Guardé los cambios y repetí la comprobación. Una vez revisado el SQL, apliqué las migraciones a MariaDB:

```bash
./vendor/bin/sail artisan migrate
```

Al terminar la sesión quedaron creadas las tablas `establishments`, `room_types` y `rooms`. Las demás migraciones del modelo quedan para las siguientes sesiones.
