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

namespace GlpiPlugin\Custombranding\Config;

/**
 * Utilitários de cor. Só aceitamos o formato hexadecimal "#rrggbb": é o que o
 * <input type="color"> envia e elimina qualquer risco de injeção no CSS.
 */
final class Color
{
    /** Contraste mínimo recomendado pela WCAG 2.1 (nível AA, texto normal). */
    public const float WCAG_AA = 4.5;

    /** Devolve a cor em minúsculas se válida; senão, o valor padrão. */
    public static function normalize(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : $fallback;
    }

    /** "#2f6fb0" → "47, 111, 176" (formato das variáveis --tblr-*-rgb). */
    public static function rgbTriplet(string $hex): string
    {
        return implode(', ', self::toRgb($hex));
    }

    /** Preto ou branco, o que tiver maior contraste sobre a cor dada. */
    public static function readableForeground(string $hex): string
    {
        return self::contrast($hex, '#ffffff') >= self::contrast($hex, '#000000') ? '#ffffff' : '#000000';
    }

    /** Razão de contraste WCAG entre duas cores (1 a 21). */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** @return array{int, int, int} */
    private static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /** Luminância relativa (WCAG). */
    private static function luminance(string $hex): float
    {
        $channels = array_map(static function (int $c): float {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::toRgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
