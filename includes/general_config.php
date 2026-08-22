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
| Default Production Activities
|--------------------------------------------------------------------------
|
| These are copied into job_process_items the first time Process Setup is
| opened for a job with no process items. Existing job-specific activities
| are never overwritten or supplemented automatically.
|
*/

$defaultProcessItems = [

    ['process_code' => 'CORE',        'item_code' => 'CORE',        'item_name' => 'Core Assembly'],

    ['process_code' => 'WINDING',     'item_code' => 'LV1',         'item_name' => 'LV Winding 1'],
    ['process_code' => 'WINDING',     'item_code' => 'LV2',         'item_name' => 'LV Winding 2'],
    ['process_code' => 'WINDING',     'item_code' => 'LV3',         'item_name' => 'LV Winding 3'],
    ['process_code' => 'WINDING',     'item_code' => 'HV1',         'item_name' => 'HV Winding 1'],
    ['process_code' => 'WINDING',     'item_code' => 'HV2',         'item_name' => 'HV Winding 2'],
    ['process_code' => 'WINDING',     'item_code' => 'HV3',         'item_name' => 'HV Winding 3'],

    ['process_code' => 'ASSEMBLY',    'item_code' => 'LVCA',        'item_name' => 'LV Coil Assembly'],
    ['process_code' => 'ASSEMBLY',    'item_code' => 'HVCA',        'item_name' => 'HV Coil Assembly'],
    ['process_code' => 'ASSEMBLY',    'item_code' => 'YOKEFILLING', 'item_name' => 'Yoke Filling'],
    ['process_code' => 'ASSEMBLY',    'item_code' => 'LVCONN',      'item_name' => 'LV Connection'],
    ['process_code' => 'ASSEMBLY',    'item_code' => 'HVCONN',      'item_name' => 'HV Connection'],

    ['process_code' => 'TANKING',     'item_code' => 'TANKING',    'item_name' => 'Job Tanking'],
    ['process_code' => 'TANKING',     'item_code' => 'PRESSURE',   'item_name' => 'Tank Pressure'],
    ['process_code' => 'TANKING',     'item_code' => 'PIPELINE',   'item_name' => 'Pipe Line Connection'],

    ['process_code' => 'TESTING',     'item_code' => '2KV',        'item_name' => '2 KV Testing'],
    ['process_code' => 'TESTING',     'item_code' => 'CCALV',      'item_name' => 'CCA LV Testing'],
    ['process_code' => 'TESTING',     'item_code' => 'FINALTEST',  'item_name' => 'Final Testing'],

    ['process_code' => 'FABRICATION', 'item_code' => 'YOKECLAMP',  'item_name' => 'Yoke Clamp'],
    ['process_code' => 'FABRICATION', 'item_code' => 'MARKING',    'item_name' => 'Design Marking'],
    ['process_code' => 'FABRICATION', 'item_code' => 'CUTTING',    'item_name' => 'Material Cutting'],
    ['process_code' => 'FABRICATION', 'item_code' => 'ASSEMBLY',   'item_name' => 'Parts Assembly'],
    ['process_code' => 'FABRICATION', 'item_code' => 'WELDING',    'item_name' => 'Final Welding'],

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

    'HOLD' => [
        'name' => 'Hold'
    ],

    'COMPLETE' => [
        'name' => 'Complete'
    ]

];


/*
|--------------------------------------------------------------------------
| Production Status Defaults
|--------------------------------------------------------------------------
|
| The Production Status page opens with jobs that have had at least one
| process activity updated in this many days.
|
*/

$recentStatusDays = 10;

