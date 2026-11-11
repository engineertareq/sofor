<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A refusal the app must be able to tell apart from the others with the same
 * status: `code` says which screen to show (verify, update the app, sign in).
 */
class ApiException extends HttpException
{
    public function __construct(int $status, private readonly string $errorCode, string $message)
    {
        parent::__construct($status, $message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->getStatusCode());
    }
}
