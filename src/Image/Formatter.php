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

/**
 * Formatação de valores exibidos ao usuário (tamanhos, dimensões, formatos).
 * Centralizado para manter as mensagens consistentes em todo o plugin.
 */
final class Formatter
{
    public static function bytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1, ',', ''), '0'), ',') . ' MB';
        }
        if ($bytes >= 1024) {
            return (string) round($bytes / 1024) . ' KB';
        }
        return $bytes . ' B';
    }

    public static function dimensions(int $width, int $height): string
    {
        return $width . '×' . $height . ' px';
    }

    /** @param list<string> $mimes */
    public static function formats(array $mimes): string
    {
        return implode(', ', array_map(ImageFormat::labelFor(...), $mimes));
    }

    /** Proporção simplificada, ex.: 1920×1080 → "16:9". */
    public static function ratio(int $width, int $height): string
    {
        $gcd = static function (int $a, int $b) use (&$gcd): int {
            return $b === 0 ? $a : $gcd($b, $a % $b);
        };
        $d = $gcd($width, $height) ?: 1;
        return ($width / $d) . ':' . ($height / $d);
    }
}
