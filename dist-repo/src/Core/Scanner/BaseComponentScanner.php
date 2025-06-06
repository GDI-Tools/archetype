<?php
/**
 * Archetype - WordPress Plugin Framework
 *
 * BaseComponentScanner provides common functionality for all component scanners.
 *
 * @package Archetype\Core\Scanner
 */

namespace Archetype\Core\Scanner;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use ReflectionClass;

abstract class BaseComponentScanner {
    /**
     * Cache of already scanned classes
     *
     * @var array
     */
    protected $classCache = [];

    /**
     * Scan directories for components
     *
     * @param array $paths Directories to scan
     * @param array $excluded_folders Folders to exclude from scanning
     * @param int $max_depth Maximum recursion depth (0 for unlimited)
     * @return array Array of component information
     */
    abstract public function scan(array $paths, array $excluded_folders = [], int $max_depth = 0): array;

    /**
     * Find all PHP classes in a directory
     *
     * @param string $directory Directory to scan
     * @param array $excluded_folders Folders to exclude from scanning
     * @param int $max_depth Maximum recursion depth (0 for unlimited)
     * @return array Array of fully qualified class names
     */
    protected function findClassesInDirectory(string $directory, array $excluded_folders = [], int $max_depth = 0): array {
        $cacheKey = $directory . ':' . implode(',', $excluded_folders) . ':' . $max_depth;

        if (isset($this->classCache[$cacheKey])) {
            return $this->classCache[$cacheKey];
        }

        $classes = [];

        try {
            // Set recursion mode based on max_depth
            $recursionMode = RecursiveIteratorIterator::LEAVES_ONLY;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                $recursionMode
            );

            // Set max depth if specified
            if ($max_depth > 0) {
                $iterator->setMaxDepth($max_depth - 1); // -1 because the first level is depth 0
            }

            $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i');

            foreach ($phpFiles as $phpFile) {
                // Skip excluded folders
                $relativePath = str_replace($directory, '', $phpFile->getPath());
                $pathParts = explode(DIRECTORY_SEPARATOR, trim($relativePath, DIRECTORY_SEPARATOR));

                $shouldSkip = false;
                foreach ($pathParts as $part) {
                    if (in_array($part, $excluded_folders)) {
                        $shouldSkip = true;
                        break;
                    }
                }

                if ($shouldSkip) {
                    continue;
                }

                // Get the current depth relative to the base directory
                $currentDepth = count($pathParts);

                // Skip if beyond max depth (extra precaution)
                if ($max_depth > 0 && $currentDepth > $max_depth) {
                    continue;
                }

                $className = $this->getClassNameFromFile($phpFile->getRealPath());

                if ($className) {
                    $classes[] = $className;
                }
            }

            $this->classCache[$cacheKey] = $classes;
        } catch (\Exception $e) {
            error_log('Archetype: Error scanning directory ' . $directory . ': ' . $e->getMessage());
        }

        return $classes;
    }

    /**
     * Extract the fully qualified class name from a PHP file
     *
     * @param string $file File path
     * @return string|null Fully qualified class name or null if not found
     */
    protected function getClassNameFromFile(string $file): ?string {
        $namespace = null;
        $className = null;

        try {
            // Parse the file content to find namespace and class name
            $tokens = token_get_all(file_get_contents($file));

            for ($i = 0; $i < count($tokens); $i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    // Get namespace
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NAME_QUALIFIED) {
                            $namespace = $tokens[$j][1];
                            break;
                        }
                    }
                }

                if ($tokens[$i][0] === T_CLASS) {
                    // Get class name
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if ($tokens[$j][0] === T_STRING) {
                            $className = $tokens[$j][1];
                            break;
                        }
                    }
                    break;
                }
            }

            if ($namespace && $className) {
                return $namespace . '\\' . $className;
            }
        } catch (\Exception $e) {
            error_log('Archetype: Error parsing file ' . $file . ': ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Clear the class cache
     *
     * @return void
     */
    public function clear_cache(): void {
        $this->classCache = [];
    }
}