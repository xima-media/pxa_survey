<?php


return [
    'ctrl' => [
        'title' => 'Registrierungen',
        'label' => 'email',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,
        'delete' => 'deleted',
        'enablecolumns' => [
//            'disabled' => 'hidden'
        ],
        'searchFields' => 'email, verification_code',
        'iconfile' => 'typo3/sysext/core/Resources/Public/Icons/T3Icons/status/status-user-frontend.svg',
        'default_sortby' => 'verification_date DESC'
    ],
    'interface' => [
        'showRecordFieldList' => 'email, verification_code, verification_date, bestaetigt'
    ],
    'types' => [
        '1' => [
            'showitem' => 'email, verification_date, verification_code, bestaetigt'
        ]
    ],
    'columns' => [
        'email' => [
            'exclude' => 1,
            'label' => 'E-Mail Adresse',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'readOnly' => 1
            ]
        ],
        'verification_code' => [
            'exclude' => 1,
            'label' => 'Verifizierungscode',
            'config' => [
                'type' => 'input',
                'size' => '64',
                'readOnly' => 1
            ]
        ],
        'verification_date' => [
            'exclude' => 1,
            'label' => 'Verifizierungsdatum',
            'config' => [
                'type' => 'input',
                'size' => 12,
                'eval' => 'datetime',
                'checkbox' => 0,
                'readOnly' => 1
            ]
        ],
        'bestaetigt' => array(
            'exclude' => 1,
            'label' => 'Bestätigt?',
            'config' => array(
                'type' => 'check',
                'readOnly' => '1',
            ),
        )
    ]
];
