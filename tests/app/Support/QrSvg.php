<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Throwable;

/**
 * Membuat QR Code sebagai SVG inline memakai bacon/bacon-qr-code.
 * SVG tidak membutuhkan ekstensi GD/Imagick dan tetap tajam saat dicetak.
 */
class QrSvg
{
    public static function make(string $text, int $size = 240): string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd()
            );

            // bacon-qr-code v3 memakai enum, v2 memakai static method. Dukung keduanya.
            $level = enum_exists(ErrorCorrectionLevel::class)
                ? constant(ErrorCorrectionLevel::class . '::M')
                : ErrorCorrectionLevel::M();

            $svg = (new Writer($renderer))->writeString($text, 'UTF-8', $level);

            // Buang deklarasi XML pembuka supaya valid saat ditempel di HTML.
            return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
        } catch (Throwable $e) {
            report($e);

            return '<span class="qr-error">QR gagal dibuat</span>';
        }
    }
}
