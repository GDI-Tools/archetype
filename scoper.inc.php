<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

return [
    'prefix' => 'ArchetypeScoped',

    'finders' => [
        Finder::create()->files()->in(__DIR__ . '/src')->name('*.php'),
        Finder::create()->files()->in(__DIR__ . '/vendor')->name('*.php'),
    ],

    'exclude-namespaces' => [
        'Archetype\\',
    ],

    // NEW for scoper v0.18+
    'expose-functions' => [
        'add_action',
        'add_filter',
        'do_action',
        'apply_filters',
        'register_activation_hook',
        'register_deactivation_hook',
        'do_shortcode',
        'wpdb',
        // add more WordPress global functions as needed
    ],
    'expose-classes' => [
        'WP_Error',
        'WP_REST_Request',
        'WP_REST_Response',
        // add more WordPress classes as needed
    ],

    'patchers' => [
        static function ($filePath, $prefix, $content) {
            $content = str_replace(
                "'Doctrine\\DBAL\\Driver\\AbstractMySQLDriver'",
                "'{$prefix}\\Doctrine\\DBAL\\Driver\\AbstractMySQLDriver'",
                $content
            );
            $content = str_replace(
                '"Doctrine\\DBAL\\Driver\\AbstractMySQLDriver"',
                "\"{$prefix}\\Doctrine\\DBAL\\Driver\\AbstractMySQLDriver\"",
                $content
            );
            return $content;
        },
    ],
];
