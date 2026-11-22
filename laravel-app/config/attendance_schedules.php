<?php

return [
    'grace_minutes' => 10,

    'day_names' => [
        1 => 'LUNES',
        2 => 'MARTES',
        3 => 'MIERCOLES',
        4 => 'JUEVES',
        5 => 'VIERNES',
    ],

    'late_threshold_overrides' => [
        '301-M|MARTES' => '08:00:00',
        '301-M|MIERCOLES' => '08:00:00',
        '301-M|VIERNES' => '08:00:00',
        '303-M|JUEVES' => '08:00:00',
        '303-M|VIERNES' => '08:00:00',
        '504-M|MARTES' => '08:00:00',
        '504-M|MIERCOLES' => '08:00:00',
        '302-M|VIERNES' => '08:00:00',
        '102-M|MIERCOLES' => '08:00:00',
        '102-M|JUEVES' => '08:00:00',
        '501-M|LUNES' => '08:00:00',
        '503-M|LUNES' => '08:00:00',
        '503-M|MARTES' => '08:00:00',
        '503-M|VIERNES' => '08:00:00',
        '502-M|LUNES' => '08:00:00',
        '502-M|JUEVES' => '08:00:00',
        '502-M|VIERNES' => '08:00:00',
        '105-V|MARTES' => '14:30:00',
        '105-V|VIERNES' => '14:30:00',
        '102-V|LUNES' => '14:30:00',
        '102-V|MIERCOLES' => '14:30:00',
        '301-V|MIERCOLES' => '14:30:00',
        '302-V|LUNES' => '14:30:00',
        '303-V|MARTES' => '14:30:00',
        '303-V|JUEVES' => '14:30:00',
        '104-V|JUEVES' => '14:30:00',
        '104-V|VIERNES' => '14:30:00',
        '103-V|LUNES' => '14:30:00',
    ],
];
