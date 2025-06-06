<?php

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'Archetype\\Vendor',

    'finders' => [
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/')
            ->exclude([
                'bin', 'doc', 'test', 'tests', 'vendor-build'
            ])
            ->in('vendor'),
    ],

    'patchers' => [
        // Fix Laravel helper functions
        static function (string $filePath, string $prefix, string $contents): string {
            // Fix function_exists checks in helper files
            if (strpos($filePath, 'helpers.php') !== false) {
                // Remove namespace prefix from function_exists checks
                $contents = str_replace(
                    "function_exists('{$prefix}\\",
                    "function_exists('",
                    $contents
                );

                // Remove namespace declaration from helper files to keep functions global
                $contents = str_replace(
                    "namespace {$prefix};",
                    '',
                    $contents
                );

                // Fix double backslashes in function calls within helper files
                // Use regex to find all function calls with double backslashes and fix them
                $contents = preg_replace('/\\\\\\\\([a-zA-Z_][a-zA-Z0-9_]*)\\s*\\(/', '\\\\$1(', $contents);

                // Also fix single backslashes before helper functions that should be global
                $contents = preg_replace('/\\\\([a-zA-Z_][a-zA-Z0-9_]*)\\s*\\(/', '$1(', $contents);
            }

            return $contents;
        },

        // Fix Laravel Support classes that call helper functions
        static function (string $filePath, string $prefix, string $contents): string {
            if (strpos($filePath, 'illuminate/support') !== false ||
                strpos($filePath, 'illuminate/database') !== false ||
                strpos($filePath, 'illuminate/collections') !== false) {

                // List of all Laravel helper functions that should remain unprefixed
                $helperFunctions = [
                    // Core helpers
                    'value', 'tap', 'with', 'optional', 'rescue', 'retry', 'throw_if', 'throw_unless',
                    'once', 'transform', 'when', 'unless',

                    // Array/Collection helpers
                    'collect', 'data_fill', 'data_get', 'data_set', 'data_forget', 'head', 'last',

                    // String helpers
                    'blank', 'filled', 'e', 'class_basename', 'class_uses_recursive', 'trait_uses_recursive',

                    // Environment helpers
                    'env', 'windows_os',

                    // Laravel specific
                    'fluent', 'append_config',

                    // Path helpers (if any)
                    'app_path', 'base_path', 'config_path', 'database_path', 'lang_path', 'mix',
                    'public_path', 'resource_path', 'storage_path',

                    // Session/Cache helpers (if any)
                    'session', 'cache', 'config', 'request', 'response', 'route', 'url',

                    // Translation helpers (if any)
                    'trans', 'trans_choice', '__'
                ];

                foreach ($helperFunctions as $function) {
                    // Fix double backslashes first
                    $contents = preg_replace("/\\\\\\\\{$function}\\s*\\(/", "\\{$function}(", $contents);

                    // Fix single backslashes for helper functions (should be global)
                    $contents = preg_replace("/\\\\{$function}\\s*\\(/", "{$function}(", $contents);

                    // Replace prefixed function calls with unprefixed ones
                    $contents = preg_replace("/\\\\{$prefix}\\\\{$function}\\s*\\(/", "{$function}(", $contents);
                    $contents = preg_replace("/{$prefix}\\\\{$function}\\s*\\(/", "{$function}(", $contents);
                }

                // Generic fix for any remaining double backslashes in function calls
                $contents = preg_replace('/\\\\\\\\([a-zA-Z_][a-zA-Z0-9_]*)\\s*\\(/', '\\\\$1(', $contents);

                // Generic fix for single backslashes before global functions
                $contents = preg_replace('/\\\\([a-zA-Z_][a-zA-Z0-9_]*)\\s*\\(/', '$1(', $contents);
            }

            return $contents;
        },
    ],

    'exclude-namespaces' => [
        'Composer\\Autoload',
        'PHPUnit',
    ],

    'exclude-functions' => [
        // Exclude all Laravel helper functions from being prefixed
        'value', 'tap', 'with', 'optional', 'rescue', 'retry', 'throw_if', 'throw_unless',
        'once', 'transform', 'when', 'unless',
        'collect', 'data_fill', 'data_get', 'data_set', 'data_forget', 'head', 'last',
        'blank', 'filled', 'e', 'class_basename', 'class_uses_recursive', 'trait_uses_recursive',
        'env', 'windows_os', 'fluent', 'append_config',
        'app_path', 'base_path', 'config_path', 'database_path', 'lang_path', 'mix',
        'public_path', 'resource_path', 'storage_path',
        'session', 'cache', 'config', 'request', 'response', 'route', 'url',
        'trans', 'trans_choice', '__'
    ],

    'expose-global-constants' => false,
    'expose-global-classes' => false,
    'expose-global-functions' => true,
];