<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
    // Unique prefix for all dependencies
    'prefix' => 'ArchetypeVendor',

    // Output directory for scoped code
    'output-dir' => 'build',

    // Files to scope
    'finders' => [
        // Scope all vendor dependencies
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/')
            ->exclude([
                'doc', 'docs', 'test', 'tests', 'Tests', 'example', 'examples'
            ])
            ->in('vendor'),

        // Include your source files (these won't be scoped)
        Finder::create()
            ->files()
            ->name('*.php')
            ->in('src'),
    ],

    // Patchers to fix WordPress integration
    'patchers' => [
        // Don't scope WordPress functions
        function (string $filePath, string $prefix, string $contents): string {
            $wpFunctions = [
                'wp_mkdir_p', 'wp_upload_dir', 'get_option', 'update_option',
                'add_action', 'add_filter', 'register_rest_route',
                'rest_authorization_required_code', 'sanitize_text_field',
                '__', '_e', '_x', '_n', 'esc_html', 'esc_attr'
            ];

            foreach ($wpFunctions as $func) {
                $contents = str_replace(
                    "\\{$prefix}\\{$func}(",
                    "\\{$func}(",
                    $contents
                );
            }

            return $contents;
        },

        // Don't scope WordPress classes
        function (string $filePath, string $prefix, string $contents): string {
            $wpClasses = [
                'WP_REST_Request', 'WP_REST_Response', 'WP_Error',
                'WP_Query', 'WP_Post', 'WP_User', 'wpdb'
            ];

            foreach ($wpClasses as $class) {
                $contents = str_replace(
                    "\\{$prefix}\\{$class}",
                    "\\{$class}",
                    $contents
                );
                $contents = str_replace(
                    "use {$prefix}\\{$class}",
                    "use {$class}",
                    $contents
                );
            }

            return $contents;
        },
    ],

    // Don't scope these namespaces/classes/functions
    'exclude-namespaces' => [''],
    'exclude-classes' => [
        'WP_REST_Request', 'WP_REST_Response', 'WP_Error',
        'WP_Query', 'WP_Post', 'WP_User', 'wpdb',
        'stdClass', 'Exception', 'Throwable', 'Closure'
    ],
    'exclude-functions' => [
        'wp_mkdir_p', 'wp_upload_dir', 'get_option', 'update_option',
        'add_action', 'add_filter', 'register_rest_route',
        'sanitize_text_field', '__', '_e', '_x', '_n'
    ],
    'exclude-constants' => [
        'WP_DEBUG', 'ABSPATH', 'WP_CONTENT_DIR', 'WP_PLUGIN_DIR',
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'
    ],

    'expose-global-constants' => false,
    'expose-global-classes' => false,
    'expose-global-functions' => false,
];