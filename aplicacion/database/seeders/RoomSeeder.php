<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $establishmentId = DB::table('establishments')
            ->where('slug', 'hotel-restaurante-las-vegas')
            ->value('id');

        $roomTypeId = DB::table('room_types')
            ->where('establishment_id', $establishmentId)
            ->where('name', 'Doble')
            ->value('id');

        $criterioBusqueda = [
            'establishment_id' => $establishmentId,
            'number' => '201'
        ];

        $datosHabitacion = [
            'room_type_id' => $roomTypeId,
        ];

        DB::table('rooms')->updateOrInsert($criterioBusqueda, $datosHabitacion);
    }
}
