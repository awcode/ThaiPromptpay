<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip;

use Awcode\ThaiPromptpay\Slip\Exceptions\QrCodeNotFoundException;
use Zxing\QrReader;

/**
 * Decodes a QR-code payload string out of a slip image.
 *
 * Requires khanamiryan/qrcode-detector-decoder (and ext-gd or ext-imagick).
 * Accepts: a filesystem path, raw image bytes, or a base64 data URI.
 */
final class Scanner
{
    public static function decode(string $input): string
    {
        if (! class_exists(QrReader::class)) {
            throw new \RuntimeException(
                'Image scanning requires khanamiryan/qrcode-detector-decoder.'
                . ' Install it with: composer require khanamiryan/qrcode-detector-decoder'
            );
        }

        $bytes = self::resolveImageBytes($input);

        try {
            $reader = new QrReader($bytes, QrReader::SOURCE_TYPE_BLOB);
            $text = $reader->text();
        } catch (\Throwable $e) {
            throw new QrCodeNotFoundException(
                'QR decoder failed to read the image: ' . $e->getMessage(),
                previous: $e,
            );
        }

        if ($text === null || $text === false || $text === '') {
            throw new QrCodeNotFoundException(
                'No QR code was found in the supplied image.'
            );
        }

        return $text;
    }

    private static function resolveImageBytes(string $input): string
    {
        if (str_starts_with($input, 'data:')) {
            $comma = strpos($input, ',');
            if ($comma === false) {
                throw new QrCodeNotFoundException('Malformed data URI.');
            }
            $header = substr($input, 5, $comma - 5);
            $payload = substr($input, $comma + 1);

            if (str_contains($header, ';base64')) {
                $decoded = base64_decode($payload, true);
                if ($decoded === false) {
                    throw new QrCodeNotFoundException('Invalid base64 in data URI.');
                }

                return $decoded;
            }

            return rawurldecode($payload);
        }

        if (strlen($input) < 4096 && @is_file($input) && is_readable($input)) {
            $bytes = @file_get_contents($input);
            if ($bytes === false) {
                throw new QrCodeNotFoundException("Unable to read image file: {$input}");
            }

            return $bytes;
        }

        return $input;
    }
}
