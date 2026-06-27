<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    public array $report = [

    'road_name' => [
        'rules' => 'required|max_length[100]',
        'errors' => [
            'required' => 'Road name wajib diisi.'
        ]

    ],
    'category' => [
        'rules' => 'required|in_list[accident,broken_vehicle,fallen_tree,flood,road_obstacle,traffic_light_damage]',
        'errors' => [
            'required' => 'Kategori wajib diisi.',
            'in_list' => 'Kategori tidak valid.'
        ]

    ],
    'description' => [
        'rules' => 'required',
        'errors' => [
            'required' => 'Deskripsi wajib diisi.'
        ]
    ]
];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------
}
