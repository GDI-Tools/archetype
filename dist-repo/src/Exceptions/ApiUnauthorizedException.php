<?php

namespace Archetype\Exceptions;

use Archetype\Exceptions\ApiException;
use Archetype\Http\HttpStatus;

class ApiUnauthorizedException extends ApiException{
	public function __construct(string $message = 'Unauthorized') {
		parent::__construct(error_code: 'forbidden', message: $message, status_code:HttpStatus::FORBIDDEN);
	}
}