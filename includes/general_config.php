<?php

/*
|--------------------------------------------------------------------------
| Drawing Sections
|--------------------------------------------------------------------------
*/

$sections = [

    'General Arrangement',
    'Core',
    'Winding',
    'Insulation',
    'Tank',
    'HVCableBox',
    'LVCableBox',
    'LVBusBar',
    'HVBusBar',
    'LVBusBarExtension',
    'HVBusBarExtension',
    'HVPICable',
    'Rollers',
    'TankUnderbase',
    'Accessories',
    'CCALocking',
    'YokeClamp',
    'Radiator',
    'Conservator',
    'LVBushing',
    'HVBushing',
    'Rating Plate',
    'TapSwitch',
    'Other'

];


/*
|--------------------------------------------------------------------------
| Drawing Defaults
|--------------------------------------------------------------------------
|
| code  = drawing code used in drawing numbering
| title = default drawing title
|
*/

$sectionDefaults = [

    'General Arrangement' => [
        'code'  => 'GA',
        'title' => 'General Arrangement Drawing'
    ],

    'Core' => [
        'code'  => 'CORE',
        'title' => 'Core Drawing'
    ],

    'Winding' => [
        'code'  => 'WND',
        'title' => 'Winding Drawing'
    ],

    'Insulation' => [
        'code'  => 'INS',
        'title' => 'Insulation Drawing'
    ],

    'Tank' => [
        'code'  => 'TANK',
        'title' => 'Tank Drawing'
    ],

    'HVCableBox' => [
        'code'  => 'HVCB',
        'title' => 'HV Cable Box Drawing'
    ],

    'LVCableBox' => [
        'code'  => 'LVCB',
        'title' => 'LV Cable Box Drawing'
    ],

    'LVBusBar' => [
        'code'  => 'LVBUS',
        'title' => 'LV Bus Bar Drawing'
    ],

    'HVBusBar' => [
        'code'  => 'HVBUS',
        'title' => 'HV Bus Bar Drawing'
    ],

    'LVBusBarExtension' => [
        'code'  => 'HVBUEXT',
        'title' => 'LV Bus Bar Extension Drawing'
    ],

    'HVBusBarExtension' => [
        'code'  => 'HVBUEXT',
        'title' => 'HV Bus Bar Extension Drawing'
    ],

    'HVPICable' => [
        'code'  => 'HVPICAB',
        'title' => 'HV PI Cable Drawing'
    ],

    'Rollers' => [
        'code'  => 'ROLLER',
        'title' => 'Rollers Drawing'
    ],

    'TankUnderbase' => [
        'code'  => 'TANKUBASE',
        'title' => 'Tank Underbase Drawing'
    ],

    'Accessories' => [
        'code'  => 'ACC',
        'title' => 'Accessories Drawing'
    ],

    'CCALocking' => [
        'code'  => 'CCAL',
        'title' => 'CCA Locking Drawing'
    ],

    'YokeClamp' => [
        'code'  => 'YC',
        'title' => 'Yoke Clamp Drawing'
    ],

    'Radiator' => [
        'code'  => 'RAD',
        'title' => 'Radiator Drawing'
    ],

    'Conservator' => [
        'code'  => 'CON',
        'title' => 'Conservator Drawing'
    ],

    'LVBushing' => [
        'code'  => 'LVBUSH',
        'title' => 'LV Bushing Drawing'
    ],

    'HVBushing' => [
        'code'  => 'HVBUSH',
        'title' => 'HV Bushing Drawing'
    ],

    'Rating Plate' => [
        'code'  => 'RP',
        'title' => 'Rating Plate Drawing'
    ],

    'TapSwitch' => [
        'code'  => 'TS',
        'title' => 'Tap Switch Drawing'
    ],

    'Other' => [
        'code'  => 'OTH',
        'title' => 'Other Drawing'
    ]

];

/*
|--------------------------------------------------------------------------
| Production Processes
|--------------------------------------------------------------------------
|
| A process is a logical stage.
| Job-specific units/items are stored in the database.
|
*/

$processDefinitions = [

    'CORE' => [
        'name' => 'Core'
    ],

    'WINDING' => [
        'name' => 'Winding'
    ],

    'INSULATION' => [
        'name' => 'Insulation'
    ],

    'ASSEMBLY' => [
        'name' => 'Assembly'
    ],

    'TANKING' => [
        'name' => 'Tanking'
    ],
	
	'FABRICATION' => [
        'name' => 'Fabrication'
    ],
	
    'PAINTING' => [
        'name' => 'Painting'
    ],

    'TESTING' => [
        'name' => 'Testing'
    ]

];


/*
|--------------------------------------------------------------------------
| Production Statuses
|--------------------------------------------------------------------------
*/

$processStatuses = [

    'NOT_STARTED' => [
        'name' => 'Not Started'
    ],

    'DRAWING_PENDING' => [
        'name' => 'Drawing Pending'
    ],

    'DRAWING_AVAILABLE' => [
        'name' => 'Drawing Available'
    ],

    'ORDER_PENDING' => [
        'name' => 'Order Pending'
    ],

    'ORDERED' => [
        'name' => 'Ordered'
    ],

    'INTRANSIT' => [
        'name' => 'Intransit'
    ],

    'MATERIAL_AVAILABLE' => [
        'name' => 'Material Available'
    ],

    'UNDER_PROCESS' => [
        'name' => 'Under Process'
    ],

    'COMPLETE' => [
        'name' => 'Complete'
    ]

];

