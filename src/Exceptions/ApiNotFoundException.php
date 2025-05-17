<?php

namespace Archetype\Exceptions;

use Archetype\Exceptions\ApiException;
use Archetype\http\HttpStatus;

class ApiNotFoundException extends ApiException{
	public function __construct(string $message = 'Resource not found') {
		parent::__construct(error_code: 'not_found', message: $message, status_code:HttpStatus::NOT_FOUND);
	}
}