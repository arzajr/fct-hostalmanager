# Datos de prueba de HostalManager

## Seeders del establecimiento, el tipo de habitación y la habitación

Preparé un caso de prueba para comprobar las relaciones entre las tres tablas: la habitación 201 del Hotel Restaurante Las Vegas es de tipo Doble y tiene capacidad para dos personas.

En `aplicacion/database/seeders/DatabaseSeeder.php` configuré el orden de ejecución. Cada seeder recupera los identificadores que necesita de los registros anteriores.

| Orden | Seeder | Criterio de búsqueda | Datos de prueba |
|---|---|---|---|
| 1 | `EstablishmentSeeder` | `slug` | Hotel Restaurante Las Vegas y sus datos de contacto y dirección. |
| 2 | `RoomTypeSeeder` | `establishment_id` y `name` | Tipo Doble, capacidad 2 y descripción de las camas. |
| 3 | `RoomSeeder` | `establishment_id` y `number` | Habitación 201 vinculada al tipo Doble mediante `room_type_id`. |

Usé `updateOrInsert()` para poder repetir la carga. El primer array identifica la fila y el segundo contiene los datos que quiero guardar. Si la fila ya existe, se actualiza. Si es nueva, se inserta con los campos de ambos arrays.

## Ejecución

Con los contenedores en marcha y las migraciones aplicadas, ejecuté los tres seeders desde `aplicacion/`:

```bash
sail artisan db:seed
```

Los tres terminaron con `DONE` en el orden indicado.

## Comprobación de las relaciones

Consulté la habitación desde Tinker. El primer `join` recupera el nombre del establecimiento y el segundo, el nombre del tipo de habitación.

```bash
sail artisan tinker --execute="dump(
    DB::table('rooms')
        ->join('establishments', 'rooms.establishment_id', '=', 'establishments.id')
        ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
        ->where('establishments.slug', 'hotel-restaurante-las-vegas')
        ->where('rooms.number', '201')
        ->get(['rooms.number', 'establishments.name as hotel', 'room_types.name as tipo'])
        ->all()
);"
```

La consulta devolvió una fila:

| number | hotel | tipo |
|---|---|---|
| 201 | Hotel Restaurante Las Vegas | Doble |
