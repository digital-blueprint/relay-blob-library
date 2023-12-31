<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobLibrary\Helpers;

class Error extends \Exception
{
    private const WITH_DETAILS_STATUS = -1;

    public function __construct(?string $message = '', int $code = 0, \Throwable $previous = null)
    {
        if ($code === self::WITH_DETAILS_STATUS) {
            try {
                $decoded = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $decoded = [];
            }

            $code = $decoded['code'];
            unset($decoded['code']);
        } else {
            $decoded = [
                'message' => $message,
                'errorId' => '',
                'errorDetails' => null,
            ];
        }

        parent::__construct(json_encode($decoded), $code, $previous);
    }

    public static function withDetails(?string $message = '', string $errorId = '', array $errorDetails = [], int $code = 0): Error
    {
        $message = [
            'code' => $code,
            'message' => $message,
            'errorDetails' => $errorDetails,
            'errorId' => $errorId,
        ];

        return new Error(json_encode($message), self::WITH_DETAILS_STATUS);
    }

    /**
     * Decode the error id from the body of a request.
     */
    public static function decodeErrorId(string $body): string
    {
        try {
            $jsonData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return '';
        }

        return $jsonData['errorId'] ?? '';
    }
}
