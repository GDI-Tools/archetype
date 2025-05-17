<?php

namespace Archetype\Api;

use Archetype\http\HttpStatus;
use WP_Error;
use WP_REST_Response;

class ApiResponse {
    public static function success(mixed $data = [], int $status = HttpStatus::OK): WP_REST_Response {
        return new WP_REST_Response($data, $status);
    }

    public static function error(string $error_code, string $error_message, int $status = HttpStatus::INTERNAL_SERVER_ERROR): WP_Error {
        return new WP_Error($error_code, $error_message, ['status' => $status]);
    }

    public static function server_error(string $message = 'An unexpected error occurred.'): WP_Error{
        return new WP_Error('server_error', $message, ['status' => HttpStatus::INTERNAL_SERVER_ERROR]);
    }

//    public static function file(string $absolute_path, string $download_filename = null): WP_REST_Response|WP_Error {
//        if (!file_exists($absolute_path)) {
//            return self::error('file_not_found', 'File not found', 404);
//        }
//
//        $download_filename = $download_filename ?? basename($absolute_path);
//
//        // Open file handle
//        $file_handle = fopen($absolute_path, 'rb');
//        if (!$file_handle) {
//            return self::error('file_open_failed', 'Unable to open file for reading', 500);
//        }
//
//        // Create a stream response
//        $response = new WP_REST_Response();
//
//        // Attach stream body
//        $response->set_body($file_handle);
//
//        // Set correct headers
//        $response->header('Content-Type', 'application/zip');
//        $response->header('Content-Disposition', 'attachment; filename="' . $download_filename . '"');
//        $response->header('Content-Length', filesize($absolute_path));
//
//        return $response;
//    }

}
