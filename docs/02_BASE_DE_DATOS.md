# Base de datos de HostalManager

## 1. Alcance del modelo

HostalManager utiliza MariaDB para gestionar las reservas de habitaciones. Las migraciones del módulo ya están aplicadas y se encuentran en `aplicacion/database/migrations/`. El modelo comprende siete tablas, incluida la tabla `users` de Laravel, ampliada para identificar al personal de cada establecimiento.

## 2. Establecimiento y habitaciones

| Tabla | Qué representa | Reglas del esquema |
|---|---|---|
| `establishments` | El establecimiento que ofrece el alojamiento. | El `slug` identifica al establecimiento de forma única. El teléfono, la dirección, la ciudad y el código postal son obligatorios. El correo electrónico es opcional. |
| `room_types` | Una categoría de alojamiento de un establecimiento, como una habitación doble. | `establishment_id` referencia a `establishments.id`. La capacidad se almacena como entero sin signo. La descripción es opcional y `active` vale verdadero por defecto. |
| `rooms` | Una habitación física, identificable por su número, como la 201. | `establishment_id` referencia a `establishments.id` y `room_type_id` a `room_types.id`. El estado operativo admite `active` y `maintenance`, con `active` como valor inicial. |

Un tipo de habitación puede agrupar varias habitaciones físicas. La capacidad se define una vez en el tipo. El estado operativo se guarda en cada habitación, de modo que recepción puede distinguir qué unidad concreta está en mantenimiento.

### Unicidad por establecimiento

El `slug` es único en toda la tabla `establishments`. En cambio, cada establecimiento tiene su propia numeración de habitaciones y su propio catálogo de tipos. Dos hoteles pueden tener una habitación `201` y un tipo `Doble`. Dentro de un mismo hotel, cada número de habitación y cada nombre de tipo deben ser únicos.

La inspección con `sail artisan db:table` muestra que ambas tablas solo tienen la clave primaria sobre `id` e índices individuales para las claves foráneas. MariaDB permite repetir `number` o `name` dentro del mismo establecimiento. Los seeders buscan cada pareja con `updateOrInsert()`, pero otras escrituras pueden crear duplicados.

### Coherencia entre la habitación y su tipo

Se conserva `rooms.establishment_id` para poder listar las habitaciones de un establecimiento sin consultar `room_types`. Las dos claves foráneas de `rooms` comprueban por separado que existen el establecimiento y el tipo. Si ambos existen, MariaDB admite que una habitación del hotel A apunte a un tipo del hotel B. En ese caso, la capacidad y las tarifas consultadas a través del tipo corresponderían al hotel B.

La decisión es controlar esta regla en Laravel. Al crear una habitación o cambiar su tipo, la aplicación deberá buscar el tipo por su `id` y por el `establishment_id` de la habitación. Si la consulta no devuelve una fila, deberá rechazar la operación. Cambiar de establecimiento una habitación también exigirá repetir la comprobación. Además, se impedirá trasladar un tipo a otro establecimiento mientras tenga habitaciones asignadas.

Esta validación queda pendiente de implementación. Las escrituras directas en MariaDB pueden saltársela. Para garantizar la misma regla en la base de datos se podría usar una clave foránea compuesta que vincule el par `(establishment_id, room_type_id)` de `rooms` con `(establishment_id, id)` de `room_types`.

## 3. Huéspedes y reservas

Cada fila de `guests` representa la ficha de un huésped. El nombre, los apellidos, el correo electrónico y el teléfono son obligatorios. El teléfono se almacena como texto de hasta 30 caracteres para conservar los prefijos y los ceros iniciales.

Cada fila de `reservations` vincula a un huésped con una habitación durante una estancia.

| Dato | Definición |
|---|---|
| Huésped | `guest_id` referencia a `guests.id`. Si el huésped regresa, la nueva reserva puede utilizar la misma ficha. |
| Habitación | `room_id` referencia a `rooms.id`. Una habitación puede figurar en distintas reservas a lo largo del tiempo. |
| Fechas | `check_in` indica el día de llegada y `check_out` el de salida. La estancia comprende al menos una noche, por lo que `check_out` debe ser posterior a `check_in`. La noche del día de salida queda excluida. |
| Importe total | `total_price` guarda el precio de la estancia como `DECIMAL(10, 2)`. |
| Estado | Admite `pending` (pendiente), `confirmed` (confirmada) y `cancelled` (cancelada). El valor por defecto es `pending`. |

Por ejemplo, si un huésped se aloja en la habitación 201 en dos ocasiones, su historial consta de dos reservas con fechas distintas. Ambas apuntan a la misma ficha de huésped y a la misma habitación.

Las tablas `guests` y `reservations` incluyen un identificador y marcas de creación y actualización.

### Política de borrado

HostalManager debe conservar el historial de reservas, incluidas las canceladas. Cancelar una reserva cambia su estado a `cancelled` y mantiene sus vínculos con el huésped y la habitación.

La inspección con `sail artisan db:table` confirma que las claves foráneas de `rooms`, `room_types`, `users` y `reservations` aplican `ON DELETE RESTRICT`. MariaDB rechaza el borrado de una fila mientras otra la referencie.

| Intento de borrado | Regla de MariaDB |
|---|---|
| Eliminar un establecimiento con registros vinculados | Basta con que una habitación (`rooms`), un tipo de habitación (`room_types`) o una cuenta de personal (`users`) lo referencie mediante `establishment_id` para que MariaDB rechace el borrado, incluso si el establecimiento nunca ha recibido reservas. |
| Eliminar un huésped con reservas | `reservations.guest_id` impide borrar su ficha mientras exista una reserva vinculada, incluidas las canceladas. |
| Eliminar una habitación con reservas | `reservations.room_id` impide borrar la habitación mientras alguna reserva la referencie, cualquiera que sea su estado. |

## 4. Tarifas por tipo de habitación

La tabla `room_rates` guarda el precio por noche como `DECIMAL(10, 2)`. Cada tarifa pertenece a un tipo de habitación mediante `room_type_id`, que referencia a `room_types.id`. Cada tarifa tiene también un identificador y marcas de creación y actualización. Los campos `valid_from` y `valid_until` delimitan su periodo de vigencia.

`reservations.total_price` guarda el importe acordado para la estancia, de modo que un cambio posterior de tarifas no altere el precio de una reserva confirmada. El riesgo de guardar un valor calculado es que quede desactualizado si cambian las fechas o se asigna una habitación de otro tipo sin revisar el importe.

Para crear una reserva `pending`, cada noche debe tener un precio inequívoco. Si falta alguno o hay ambigüedad, se rechaza la creación. El importe mostrado al huésped se guarda en `total_price` y se mantiene al confirmar, aunque el hotel haya actualizado sus tarifas. Si se modifican las fechas o la habitación de una reserva pendiente, se comprueban la disponibilidad y las tarifas para la nueva estancia. Solo se guarda el cambio cuando hay un precio para cada noche y el huésped acepta el nuevo total.

## 5. Personal del establecimiento

La tabla `users` contiene las cuentas del personal. Cada cuenta pertenece a un establecimiento mediante la clave foránea obligatoria `establishment_id`, que referencia a `establishments.id`. El rol admite dos valores, `admin` y `staff`. Las cuentas nuevas reciben `staff` por defecto.

## 6. Disponibilidad y confirmación

Para ofrecer una habitación, debe estar `active` y disponible durante todas las noches solicitadas. Las reservas `pending` y `confirmed` bloquean la habitación desde `check_in` hasta la noche anterior a `check_out`. Una reserva `cancelled` libera esas noches.

Las migraciones ya permiten guardar el estado de una reserva. Queda por implementar la comprobación de disponibilidad antes de confirmarla.

## 7. Decisiones pendientes

- **Solapamiento de tarifas.** Decidir si se rechazan dos periodos coincidentes para el mismo tipo y, si se admiten, qué tarifa tiene prioridad.
- **Cambio de tarifa durante la estancia.** Definir cómo se incluyen los extremos de cada periodo y cómo se suman las noches. Para una entrada el 30 de junio y una salida el 3 de julio, el caso de prueba comprende las noches del 30, del 1 y del 2. Si la primera tarifa incluye el 30 y la segunda empieza el 1, el cálculo por noche sería una noche al primer precio y dos al segundo.
- **Tarifa sin fecha de fin.** Decidir si `valid_until` puede ser `NULL` para indicar vigencia indefinida y cómo terminar ese periodo cuando entre una nueva tarifa.
- **Mantenimiento con reservas futuras.** Si la habitación pasa a `maintenance` en noviembre y tiene reservas confirmadas para diciembre, decidir cuándo debe intervenir recepción y si debe reasignarlas antes de cambiar el estado.

### Reglas acordadas pendientes de implementación o comprobación

- Revisar si ya existen números o nombres duplicados en cada hotel y resolverlos antes de añadir `UNIQUE (establishment_id, number)` a `rooms` y `UNIQUE (establishment_id, name)` a `room_types`.
- Implementar en Laravel la validación de pertenencia del tipo de habitación al establecimiento.
- Validar las fechas en Laravel para informar al usuario y comprobar que MariaDB también exige `CHECK (check_out > check_in)`. Añadir la restricción si falta.
- Implementar el recálculo de reservas pendientes y la comprobación de disponibilidad descritos en los apartados 4 y 6.
