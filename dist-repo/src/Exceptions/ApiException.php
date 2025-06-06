<?php

namespace Archetype\Exceptions;

use Exception;
use WP_Error;

abstract class ApiException extends Exception {
    protected string $error_code;
    protected int $status_code;

    public function __construct(string $error_code, string $message, int $status_code) {
        parent::__construct($message);
        $this->error_code = $error_code;
        $this->status_code = $status_code;
    }

    /**
     * Convert the exception to a WP_Error object.
     *
     * @return WP_Error
     */
    public function toWpError(): WP_Error {
        return new WP_Error($this->error_code, $this->getMessage(), ['status' => $this->status_code]);
    }

    public function get_status_code(): int{
        return $this->status_code;
    }

    public function get_error_code(): string{
        return $this->error_code;
    }
}
