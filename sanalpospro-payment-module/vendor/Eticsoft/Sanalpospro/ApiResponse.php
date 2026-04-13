<?php

namespace Eticsoft\Sanalpospro;

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class ApiResponse
{
    public ?array $rawResponse = []; 
    public ?array $headers = [];
    public ?string $body = '';
    public ?int $statusCode = 404;
    public ?array $response = [];
    public ?string $error = '';
    public ?string $message = '';

    public function __construct($response)
    {
        $this->rawResponse = $response;
        $this->parse();
    }

    private function parse(): void
    {
        $this->body = $this->rawResponse['body'];
        $this->statusCode = $this->getStatusCode();
    }

    private function getStatusCode(): int
    {
        $status = $this->rawResponse['status'];
        return (int) $status;
    }

    public function validate(): bool
    {

        $json = json_decode($this->body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error = 'Invalid JSON response';
            return false;
        }
        $this->response = $json;
        return true;
    }

    public function getMessage(): string
    {
        return $this->response['message'] ?? '';
    }

    public function getError(): string
    {
        return $this->response['error'] ?? $this->error ?? '';
    }
}
