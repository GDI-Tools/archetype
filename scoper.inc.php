<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'ArchetypeVendor',
    'output-dir' => 'build',

    'finders' => [
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/')
            ->exclude([
                'doc', 'docs', 'test', 'tests', 'Tests', 'vendor-bin',
                'phpunit', 'phpstan', 'squizlabs', 'wp-coding-standards',
                'dealerdirect', 'jetbrains', 'myclabs', 'nikic', 'phar-io',
                'phpcsstandards', 'sebastian', 'theseer', 'voku', 'webmozart',
                'composer'  // ← EXCLUDE entire composer directory
            ])
            ->notPath('composer/')  // ← Additional protection
            ->in('vendor'),

        Finder::create()
            ->files()
            ->name('*.php')
            ->in('src'),
    ],

    // Exclude Composer's autoloader completely
    'exclude-namespaces' => [
        'Composer\Autoload',
        'Composer\Installers',
        'Composer\Semver',
        'Composer',
    ],

    'exclude-classes' => [
        // Composer autoloader classes (critical!)
        'Composer\Autoload\ClassLoader',
        'Composer\Autoload\ComposerStaticInit*',
        'ComposerAutoloaderInit*',

        // WordPress REST API classes
        'WP_REST_Request',
        'WP_REST_Response',
        'WP_Error',
        'WP_Query',
        'WP_Post',
        'WP_User',
        'wpdb',

        // PHP built-ins
        'stdClass',
        'Exception',
        'Throwable',
        'Closure',
    ],

    'exclude-functions' => [
        // WordPress functions
        'wp_mkdir_p',
        'wp_upload_dir',
        'add_action',
        'register_rest_route',
        'rest_authorization_required_code',
        'sanitize_text_field',
        '__', '_e', '_x', '_n',
        'get_option', 'update_option',
        'add_filter', 'remove_action', 'remove_filter',
        'esc_html', 'esc_attr',
    ],

    'exclude-constants' => [
        // WordPress constants
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
        'WP_DEBUG', 'ABSPATH', 'WP_CONTENT_DIR', 'WP_PLUGIN_DIR',
    ],

    'expose-global-constants' => false,
    'expose-global-classes' => false,
    'expose-global-functions' => false,
];