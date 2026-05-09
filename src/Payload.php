<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * An immutable PromptPay EMVCo payload string with helpers for rendering it
 * as a QR code image (SVG or PNG).
 *
 * QR rendering requires bacon/bacon-qr-code (^2.0). The core payload string
 * is always available without that dependency.
 */
final class Payload
{
    public function __construct(private readonly string $payload)
    {
    }

    public function toString(): string
    {
        return $this->payload;
    }

    public function __toString(): string
    {
        return $this->payload;
    }

    /**
     * Render the QR as an SVG string. SVG has no extension dependency
     * beyond ext-dom, which ships with PHP by default.
     */
    public function svg(int $size = 300, int $margin = 1): string
    {
        $this->assertRendererInstalled();

        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($this->payload);
    }

    /**
     * Render the QR as raw PNG bytes. Tries ext-imagick first, then falls
     * back to bacon/bacon-qr-code's GD renderer (ext-gd) when present.
     */
    public function png(int $size = 300, int $margin = 1): string
    {
        $this->assertRendererInstalled();

        if (extension_loaded('imagick')) {
            $renderer = new ImageRenderer(
                new RendererStyle($size, $margin),
                new ImagickImageBackEnd()
            );

            return (new Writer($renderer))->writeString($this->payload);
        }

        if (extension_loaded('gd') && class_exists(GDLibRenderer::class)) {
            $renderer = new GDLibRenderer($size, $margin);

            return (new Writer($renderer))->writeString($this->payload);
        }

        throw new \RuntimeException(
            'PNG rendering requires either ext-imagick or ext-gd (with bacon/bacon-qr-code ^2.0.5+).'
            . ' Use ->svg() instead, or install one of those extensions.'
        );
    }

    /**
     * Build a base64-encoded data URI suitable for an <img src="..."> tag.
     * Defaults to SVG (no extension dep); pass 'png' to get a PNG URI.
     */
    public function dataUri(int $size = 300, int $margin = 1, string $format = 'svg'): string
    {
        $format = strtolower($format);

        return match ($format) {
            'svg' => 'data:image/svg+xml;base64,' . base64_encode($this->svg($size, $margin)),
            'png' => 'data:image/png;base64,' . base64_encode($this->png($size, $margin)),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}. Use 'svg' or 'png'."),
        };
    }

    private function assertRendererInstalled(): void
    {
        if (! class_exists(Writer::class)) {
            throw new \RuntimeException(
                'QR rendering requires bacon/bacon-qr-code. Install it with:'
                . ' composer require bacon/bacon-qr-code'
            );
        }
    }
}
