<?php

declare(strict_types=1);

$path = dirname(__DIR__).DIRECTORY_SEPARATOR.'Costume Rental API.postman_collection.json';
$json = (string) file_get_contents($path);
$json = preg_replace('/^\xEF\xBB\xBF/', '', $json) ?? $json;
$collection = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

function setCollectionVariable(array &$collection, string $key, string $value): void
{
    foreach ($collection['variable'] as &$variable) {
        if (($variable['key'] ?? null) === $key) {
            $variable['value'] = $value;

            return;
        }
    }
    unset($variable);

    $collection['variable'][] = [
        'key' => $key,
        'value' => $value,
        'type' => 'string',
    ];
}

function buildUrl(string $rawUrl): array
{
    $trimmed = preg_replace('/^\{\{base_url\}\}\/?/', '', $rawUrl) ?? $rawUrl;
    $parts = array_values(array_filter(explode('/', $trimmed), static fn (string $part): bool => $part !== ''));

    return [
        'raw' => $rawUrl,
        'host' => ['{{base_url}}'],
        'path' => $parts,
    ];
}

function requestItem(string $name, string $method, string $rawUrl, array $options = []): array
{
    $body = $options['body'] ?? null;
    $description = $options['description'] ?? '';
    $tests = $options['tests'] ?? [];

    $request = [
        'method' => $method,
        'header' => [],
        'url' => buildUrl($rawUrl),
        'description' => $description,
    ];

    if ($body !== null) {
        $request['header'][] = [
            'key' => 'Content-Type',
            'value' => 'application/json',
        ];
        $request['body'] = [
            'mode' => 'raw',
            'raw' => $body,
            'options' => [
                'raw' => [
                    'language' => 'json',
                ],
            ],
        ];
    }

    $item = [
        'name' => $name,
        'request' => $request,
        'response' => [],
    ];

    if ($tests !== []) {
        $item['event'] = [[
            'listen' => 'test',
            'script' => [
                'type' => 'text/javascript',
                'exec' => $tests,
            ],
        ]];
    }

    return $item;
}

function folder(string $name, array $items): array
{
    return [
        'name' => $name,
        'item' => $items,
    ];
}

$workflowFolderNames = [
    'Workflow - Customers',
    'Workflow - Inventory',
    'Workflow - Internal Borrow',
    'Workflow - Rentals',
    'Workflow - Incidents & Maintenance',
];

$collection['item'] = array_values(array_filter(
    $collection['item'],
    static fn (array $item): bool => ! in_array($item['name'] ?? '', $workflowFolderNames, true)
));

$variables = [
    'category_id' => '1',
    'image_id' => '2',
    'costume_id' => '15',
    'equipment_prop_id' => '19',
    'customer_id' => '1',
    'borrow_employee_id' => '2',
    'prop_warehouse_id' => '2',
    'costume_warehouse_id' => '1',
    'borrow_equipment_prop_id' => '19',
    'rental_equipment_prop_id' => '15',
    'rental_damaged_equipment_prop_id' => '16',
    'internal_inventory_item_id' => '44',
    'rental_inventory_item_id' => '3',
    'rental_inventory_item_2_id' => '4',
    'sample_internal_slip_id' => '3',
    'sample_rental_slip_id' => '3',
    'sample_internal_incident_id' => '2',
    'sample_rental_incident_id' => '2',
    'sample_maintenance_ticket_id' => '2',
    'sample_internal_inventory_item_id' => '53',
    'sample_maintenance_inventory_item_id' => '26',
    'maintenance_cancel_inventory_item_id' => '45',
    'internal_borrow_slip_id' => '',
    'internal_borrow_cancel_slip_id' => '',
    'internal_borrow_detail_id' => '',
    'internal_incident_id' => '',
    'rental_slip_id' => '',
    'rental_cancel_slip_id' => '',
    'rental_detail_id' => '',
    'rental_incident_id' => '',
    'rental_remaining_amount' => '',
    'maintenance_ticket_id' => '',
    'maintenance_cancel_ticket_id' => '',
];

foreach ($variables as $key => $value) {
    setCollectionVariable($collection, $key, $value);
}

$internalBorrowCreateBody = <<<'JSON'
{
  "employee_id": {{borrow_employee_id}},
  "warehouse_id": {{prop_warehouse_id}},
  "borrow_date": "2026-05-19",
  "due_date": "2026-05-22",
  "purpose": "Postman internal borrow"
}
JSON;

$internalBorrowDetailBody = <<<'JSON'
{
  "equipment_prop_id": {{borrow_equipment_prop_id}},
  "borrowed_quantity": 1
}
JSON;

$internalBorrowCancelBody = <<<'JSON'
{
  "reason": "Khong con nhu cau muon"
}
JSON;

$internalBorrowAssignBody = <<<'JSON'
{
  "assignments": [
    {
      "detail_id": {{internal_borrow_detail_id}},
      "inventory_item_ids": [{{internal_inventory_item_id}}],
      "condition_on_borrow_id": 1
    }
  ]
}
JSON;

$internalBorrowReturnBody = <<<'JSON'
{
  "items": [
    {
      "inventory_item_id": {{internal_inventory_item_id}},
      "return_action": "DAMAGED",
      "condition_on_return_id": 1,
      "compensation_amount": 120000,
      "note": "Hong khoa day"
    }
  ]
}
JSON;

$rentalCreateBody = <<<'JSON'
{
  "customer_id": {{customer_id}},
  "warehouse_id": {{costume_warehouse_id}},
  "rental_date": "2026-05-19",
  "start_date": "2026-05-19",
  "due_date": "2026-05-21"
}
JSON;

$rentalDetailBody = <<<'JSON'
{
  "equipment_prop_id": {{rental_equipment_prop_id}},
  "rented_quantity": 2,
  "rental_unit_price": 100000,
  "rental_days": 2,
  "deposit_amount": 50000
}
JSON;

$rentalAssignBody = <<<'JSON'
{
  "assignments": [
    {
      "detail_id": {{rental_detail_id}},
      "inventory_item_ids": [{{rental_inventory_item_id}}, {{rental_inventory_item_2_id}}],
      "condition_on_rent_id": 1
    }
  ]
}
JSON;

$rentalPaymentBody = <<<'JSON'
{
  "payment_type": "DEPOSIT",
  "payment_date": "2026-05-19",
  "amount": 50000,
  "payment_method": "CASH"
}
JSON;

$rentalSettlementPaymentBody = <<<'JSON'
{
  "payment_type": "RENTAL_PAYMENT",
  "payment_date": "2026-05-19",
  "amount": {{rental_remaining_amount}},
  "payment_method": "BANK_TRANSFER",
  "note": "Thanh toan phan con lai"
}
JSON;

$rentalReturnBody = <<<'JSON'
{
  "items": [
    {
      "inventory_item_id": {{rental_inventory_item_id}},
      "return_action": "RETURNED",
      "condition_on_return_id": 1
    },
    {
      "inventory_item_id": {{rental_inventory_item_2_id}},
      "return_action": "DAMAGED",
      "condition_on_return_id": 1,
      "compensation_amount": 150000,
      "note": "Rach vai"
    }
  ]
}
JSON;

$maintenanceCreateBody = <<<'JSON'
{
  "maintenance_type": "REPAIR",
  "reported_date": "2026-05-19",
  "expected_return_date": "2026-05-22",
  "vendor": "May nhanh"
}
JSON;

$maintenanceStartBody = <<<'JSON'
{
  "started_date": "2026-05-19"
}
JSON;

$maintenanceCompleteBody = <<<'JSON'
{
  "return_date": "2026-05-21",
  "cost": 80000
}
JSON;

$maintenanceReturnBody = <<<'JSON'
{
  "inventory_condition_id": 1
}
JSON;

$resolveInternalIncidentBody = <<<'JSON'
{
  "resolution": "Da ghi nhan va xu ly su co",
  "compensation_amount": 120000
}
JSON;

$closeInternalIncidentBody = <<<'JSON'
{
  "note": "Dong su co noi bo"
}
JSON;

$resolveRentalIncidentBody = <<<'JSON'
{
  "resolution": "Da xu ly va cap nhat boi thuong",
  "compensation_amount": 150000
}
JSON;

$closeRentalIncidentBody = <<<'JSON'
{
  "note": "Dong su co thue"
}
JSON;

$rentalCloseBody = <<<'JSON'
{
  "note": "Dong phieu sau khi quyet toan"
}
JSON;

$maintenanceCancelCreateBody = <<<'JSON'
{
  "inventory_item_id": {{maintenance_cancel_inventory_item_id}},
  "maintenance_type": "REPAIR",
  "reported_date": "2026-05-19",
  "vendor": "May nhanh"
}
JSON;

$maintenanceCancelBody = <<<'JSON'
{
  "inventory_condition_id": 1,
  "note": "Khong can sua nua"
}
JSON;

$customersFolder = folder('Workflow - Customers', [
    requestItem('List Customers', 'GET', '{{base_url}}/customers', [
        'description' => 'List customers.',
    ]),
    requestItem('Show Sample Customer', 'GET', '{{base_url}}/customers/{{customer_id}}', [
        'description' => 'Show seeded customer.',
    ]),
]);

$inventoryFolder = folder('Workflow - Inventory', [
    requestItem('Available Props', 'GET', '{{base_url}}/inventory/available?warehouse_id={{prop_warehouse_id}}&equipment_prop_id={{borrow_equipment_prop_id}}', [
        'description' => 'Available prop inventory.',
    ]),
    requestItem('Available Costumes', 'GET', '{{base_url}}/inventory/available?warehouse_id={{costume_warehouse_id}}&equipment_prop_id={{rental_equipment_prop_id}}', [
        'description' => 'Available costume inventory.',
    ]),
    requestItem('Sample Maintenance Timeline', 'GET', '{{base_url}}/inventory/{{sample_maintenance_inventory_item_id}}/timeline', [
        'description' => 'Timeline of seeded maintenance item.',
    ]),
    requestItem('Inventory Transactions By Item', 'GET', '{{base_url}}/inventory-transactions?inventory_item_id={{sample_maintenance_inventory_item_id}}', [
        'description' => 'Inventory transactions by item.',
    ]),
]);

$internalBorrowFolder = folder('Workflow - Internal Borrow', [
    requestItem('List Internal Borrow Slips', 'GET', '{{base_url}}/internal-borrow-slips', [
        'description' => 'List internal borrow slips.',
    ]),
    requestItem('Show Sample Internal Borrow Slip', 'GET', '{{base_url}}/internal-borrow-slips/{{sample_internal_slip_id}}', [
        'description' => 'Show seeded internal borrow slip.',
    ]),
    requestItem('Create Internal Borrow Slip', 'POST', '{{base_url}}/internal-borrow-slips', [
        'body' => $internalBorrowCreateBody,
        'description' => 'Create internal borrow slip.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.slip && jsonData.slip.id) pm.collectionVariables.set("internal_borrow_slip_id", String(jsonData.slip.id));',
        ],
    ]),
    requestItem('Create Internal Borrow Slip (Cancel Path)', 'POST', '{{base_url}}/internal-borrow-slips', [
        'body' => $internalBorrowCreateBody,
        'description' => 'Create a draft internal borrow slip for cancel flow.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.slip && jsonData.slip.id) pm.collectionVariables.set("internal_borrow_cancel_slip_id", String(jsonData.slip.id));',
        ],
    ]),
    requestItem('Add Internal Borrow Detail', 'POST', '{{base_url}}/internal-borrow-slips/{{internal_borrow_slip_id}}/details', [
        'body' => $internalBorrowDetailBody,
        'description' => 'Add internal borrow detail.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.detail && jsonData.detail.id) pm.collectionVariables.set("internal_borrow_detail_id", String(jsonData.detail.id));',
        ],
    ]),
    requestItem('Assign Internal Borrow Item', 'POST', '{{base_url}}/internal-borrow-slips/{{internal_borrow_slip_id}}/assign-items', [
        'body' => $internalBorrowAssignBody,
        'description' => 'Assign concrete inventory item.',
    ]),
    requestItem('Approve Internal Borrow Slip', 'POST', '{{base_url}}/internal-borrow-slips/{{internal_borrow_slip_id}}/approve', [
        'body' => '',
        'description' => 'Approve internal borrow slip.',
    ]),
    requestItem('Checkout Internal Borrow Slip', 'POST', '{{base_url}}/internal-borrow-slips/{{internal_borrow_slip_id}}/checkout', [
        'body' => '',
        'description' => 'Checkout internal borrow slip.',
    ]),
    requestItem('Return Internal Borrow Damaged', 'POST', '{{base_url}}/internal-borrow-slips/{{internal_borrow_slip_id}}/return', [
        'body' => $internalBorrowReturnBody,
        'description' => 'Return damaged item to create incident.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.incident_ids && jsonData.incident_ids.length > 0) pm.collectionVariables.set("internal_incident_id", String(jsonData.incident_ids[0]));',
        ],
    ]),
    requestItem('Show Internal Incident', 'GET', '{{base_url}}/internal-incidents/{{internal_incident_id}}', [
        'description' => 'Show internal incident created above.',
    ]),
    requestItem('Resolve Internal Incident', 'POST', '{{base_url}}/internal-incidents/{{internal_incident_id}}/resolve', [
        'body' => $resolveInternalIncidentBody,
        'description' => 'Resolve the internal incident created in return flow.',
    ]),
    requestItem('Close Internal Incident', 'POST', '{{base_url}}/internal-incidents/{{internal_incident_id}}/close', [
        'body' => $closeInternalIncidentBody,
        'description' => 'Close the resolved internal incident.',
    ]),
    requestItem('Cancel Internal Borrow Slip', 'POST', '{{base_url}}/internal-borrow-slips/{{internal_borrow_cancel_slip_id}}/cancel', [
        'body' => $internalBorrowCancelBody,
        'description' => 'Cancel a separate draft internal borrow slip created by the cancel path request.',
    ]),
]);

$rentalsFolder = folder('Workflow - Rentals', [
    requestItem('List Rental Slips', 'GET', '{{base_url}}/rental-slips', [
        'description' => 'List rental slips.',
    ]),
    requestItem('Show Sample Rental Slip', 'GET', '{{base_url}}/rental-slips/{{sample_rental_slip_id}}', [
        'description' => 'Show seeded rental slip.',
    ]),
    requestItem('Create Rental Slip', 'POST', '{{base_url}}/rental-slips', [
        'body' => $rentalCreateBody,
        'description' => 'Create rental slip.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.slip && jsonData.slip.id) pm.collectionVariables.set("rental_slip_id", String(jsonData.slip.id));',
        ],
    ]),
    requestItem('Create Rental Slip (Cancel Path)', 'POST', '{{base_url}}/rental-slips', [
        'body' => $rentalCreateBody,
        'description' => 'Create a draft rental slip for cancel flow.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.slip && jsonData.slip.id) pm.collectionVariables.set("rental_cancel_slip_id", String(jsonData.slip.id));',
        ],
    ]),
    requestItem('Add Rental Detail', 'POST', '{{base_url}}/rental-slips/{{rental_slip_id}}/details', [
        'body' => $rentalDetailBody,
        'description' => 'Add rental detail.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.detail && jsonData.detail.id) pm.collectionVariables.set("rental_detail_id", String(jsonData.detail.id));',
        ],
    ]),
    requestItem('Assign Rental Items', 'POST', '{{base_url}}/rental-slips/{{rental_slip_id}}/assign-items', [
        'body' => $rentalAssignBody,
        'description' => 'Assign rental inventory items.',
    ]),
    requestItem('Submit Rental Slip', 'POST', '{{base_url}}/rental-slips/{{rental_slip_id}}/submit', [
        'body' => '',
        'description' => 'Submit rental slip.',
    ]),
    requestItem('Approve Rental Slip', 'POST', '{{base_url}}/rental-slips/{{rental_slip_id}}/approve', [
        'body' => '',
        'description' => 'Approve rental slip.',
    ]),
    requestItem('Checkout Rental Slip', 'POST', '{{base_url}}/rental-slips/{{rental_slip_id}}/checkout', [
        'body' => '',
        'description' => 'Checkout rental slip.',
    ]),
    requestItem('Add Rental Payment', 'POST', '{{base_url}}/rental-slips/{{rental_slip_id}}/payments', [
        'body' => $rentalPaymentBody,
        'description' => 'Add rental payment.',
    ]),
    requestItem('Return Rental With Damage', 'POST', '{{base_url}}/rental-slips/{{rental_slip_id}}/return', [
        'body' => $rentalReturnBody,
        'description' => 'Return one normal item and one damaged item.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.incident_ids && jsonData.incident_ids.length > 0) pm.collectionVariables.set("rental_incident_id", String(jsonData.incident_ids[0]));',
            'if (jsonData.remaining_amount !== undefined && jsonData.remaining_amount !== null) pm.collectionVariables.set("rental_remaining_amount", String(jsonData.remaining_amount));',
        ],
    ]),
    requestItem('Show Rental Payments', 'GET', '{{base_url}}/rental-slips/{{rental_slip_id}}/payments', [
        'description' => 'Show rental payment history.',
    ]),
    requestItem('Add Final Rental Payment', 'POST', '{{base_url}}/rental-slips/{{rental_slip_id}}/payments', [
        'body' => $rentalSettlementPaymentBody,
        'description' => 'Pay the remaining balance after return and compensation are calculated.',
    ]),
    requestItem('Close Rental Slip', 'POST', '{{base_url}}/rental-slips/{{rental_slip_id}}/close', [
        'body' => $rentalCloseBody,
        'description' => 'Close rental slip after all incidents are closed and balance is fully paid.',
    ]),
    requestItem('Cancel Rental Slip', 'POST', '{{base_url}}/rental-slips/{{rental_cancel_slip_id}}/cancel', [
        'body' => $internalBorrowCancelBody,
        'description' => 'Cancel a separate draft rental slip created by the cancel path request.',
    ]),
]);

$incidentMaintenanceFolder = folder('Workflow - Incidents & Maintenance', [
    requestItem('List Internal Incidents', 'GET', '{{base_url}}/internal-incidents', [
        'description' => 'List internal incidents.',
    ]),
    requestItem('List Rental Incidents', 'GET', '{{base_url}}/rental-incidents', [
        'description' => 'List rental incidents.',
    ]),
    requestItem('Show Sample Rental Incident', 'GET', '{{base_url}}/rental-incidents/{{sample_rental_incident_id}}', [
        'description' => 'Show seeded rental incident.',
    ]),
    requestItem('Show Rental Incident', 'GET', '{{base_url}}/rental-incidents/{{rental_incident_id}}', [
        'description' => 'Show rental incident created in the active rental flow.',
    ]),
    requestItem('Create Maintenance From Rental Incident', 'POST', '{{base_url}}/rental-incidents/{{rental_incident_id}}/create-maintenance-ticket', [
        'body' => $maintenanceCreateBody,
        'description' => 'Create maintenance ticket from rental incident.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.maintenance_ticket && jsonData.maintenance_ticket.id) pm.collectionVariables.set("maintenance_ticket_id", String(jsonData.maintenance_ticket.id));',
        ],
    ]),
    requestItem('Start Maintenance', 'POST', '{{base_url}}/maintenance-tickets/{{maintenance_ticket_id}}/start', [
        'body' => $maintenanceStartBody,
        'description' => 'Start maintenance.',
    ]),
    requestItem('Complete Maintenance', 'POST', '{{base_url}}/maintenance-tickets/{{maintenance_ticket_id}}/complete', [
        'body' => $maintenanceCompleteBody,
        'description' => 'Complete maintenance.',
    ]),
    requestItem('Return To Stock', 'POST', '{{base_url}}/maintenance-tickets/{{maintenance_ticket_id}}/return-to-stock', [
        'body' => $maintenanceReturnBody,
        'description' => 'Return repaired item to stock.',
    ]),
    requestItem('Resolve Rental Incident', 'POST', '{{base_url}}/rental-incidents/{{rental_incident_id}}/resolve', [
        'body' => $resolveRentalIncidentBody,
        'description' => 'Resolve the rental incident after maintenance / compensation handling.',
    ]),
    requestItem('Close Rental Incident', 'POST', '{{base_url}}/rental-incidents/{{rental_incident_id}}/close', [
        'body' => $closeRentalIncidentBody,
        'description' => 'Close the resolved rental incident.',
    ]),
    requestItem('Create Maintenance Ticket (Cancel Path)', 'POST', '{{base_url}}/maintenance-tickets', [
        'body' => $maintenanceCancelCreateBody,
        'description' => 'Create a standalone maintenance ticket for cancel flow.',
        'tests' => [
            'const jsonData = pm.response.json();',
            'if (jsonData.maintenance_ticket && jsonData.maintenance_ticket.id) pm.collectionVariables.set("maintenance_cancel_ticket_id", String(jsonData.maintenance_ticket.id));',
        ],
    ]),
    requestItem('Cancel Maintenance Ticket', 'POST', '{{base_url}}/maintenance-tickets/{{maintenance_cancel_ticket_id}}/cancel', [
        'body' => $maintenanceCancelBody,
        'description' => 'Cancel the standalone maintenance ticket and return the item to available status.',
    ]),
    requestItem('List Maintenance Tickets', 'GET', '{{base_url}}/maintenance-tickets', [
        'description' => 'List maintenance tickets.',
    ]),
    requestItem('Show Sample Maintenance Ticket', 'GET', '{{base_url}}/maintenance-tickets/{{sample_maintenance_ticket_id}}', [
        'description' => 'Show seeded maintenance ticket.',
    ]),
]);

$collection['item'][] = $customersFolder;
$collection['item'][] = $inventoryFolder;
$collection['item'][] = $internalBorrowFolder;
$collection['item'][] = $rentalsFolder;
$collection['item'][] = $incidentMaintenanceFolder;

file_put_contents(
    $path,
    (string) json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);
