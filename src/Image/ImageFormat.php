<?php

/**
 * -------------------------------------------------------------------------
 * Custom Branding — plugin de personalização de marca para o GLPI 12
 * -------------------------------------------------------------------------
 *
 * @author    F4tjs <https://github.com/f4tjs>
 * @copyright 2026 F4tjs
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/f4tjs/glpi-plugin-custombranding
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Custombranding\Image;

use InvalidArgumentException;

/**
 * Mapeamento único entre MIME, extensão e constante IMAGETYPE_* do PHP.
 *
 * A extensão gravada em disco é SEMPRE derivada do conteúdo real do arquivo,
 * nunca do nome enviado pelo usuário.
 */
final class ImageFormat
{
    /** @var array<string, array{ext: string, imagetype: int, label: string}> */
    private const array FORMATS = [
        'image/png'  => ['ext' => 'png',  'imagetype' => IMAGETYPE_PNG,  'label' => 'PNG'],
        'image/jpeg' => ['ext' => 'jpg',  'imagetype' => IMAGETYPE_JPEG, 'label' => 'JPG'],
        'image/webp' => ['ext' => 'webp', 'imagetype' => IMAGETYPE_WEBP, 'label' => 'WebP'],
    ];

    public static function isKnown(string $mime): bool
    {
        return isset(self::FORMATS[$mime]);
    }

    public static function extensionFor(string $mime): string
    {
        return self::get($mime)['ext'];
    }

    public static function imageTypeFor(string $mime): int
    {
        return self::get($mime)['imagetype'];
    }

    public static function labelFor(string $mime): string
    {
        return self::get($mime)['label'];
    }

    /** MIME a partir da extensão gravada (usado ao servir o arquivo). */
    public static function mimeForExtension(string $ext): ?string
    {
        foreach (self::FORMATS as $mime => $info) {
            if ($info['ext'] === $ext) {
                return $mime;
            }
        }
        return null;
    }

    /** @return array{ext: string, imagetype: int, label: string} */
    private static function get(string $mime): array
    {
        return self::FORMATS[$mime] ?? throw new InvalidArgumentException("Unsupported image type: $mime");
    }
}
