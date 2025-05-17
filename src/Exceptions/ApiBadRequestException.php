<?php

namespace Archetype\Exceptions;

use Archetype\Exceptions\ApiException;
use Archetype\Http\HttpStatus;

class ApiBadRequestException extends ApiException{
	public function __construct(string $message = 'Bad Request', string $code = 'bad_request') {
		parent::__construct(error_code: $code, message: $message, status_code: HttpStatus::BAD_REQUEST );
	}
}