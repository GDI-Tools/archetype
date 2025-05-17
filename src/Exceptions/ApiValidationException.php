<?php

namespace Archetype\Exceptions;

use Archetype\Exceptions\ApiException;
use Archetype\http\HttpStatus;

class ApiValidationException extends ApiException{
	public function __construct(string $message = 'Validation failed') {
		parent::__construct(error_code: 'validation_error', message: $message, status_code:HttpStatus::BAD_REQUEST);
	}
}