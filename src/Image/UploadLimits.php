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
 * Limites de upload configurados no PHP (php.ini).
 *
 * Se o servidor aceitar menos que a regra do slot, a tela de configuração
 * informa o limite real — evitando o erro silencioso em que o PHP descarta
 * o envio inteiro.
 */
final class UploadLimits
{
    /** Menor valor entre upload_max_filesize e post_max_size (0 = ilimitado). */
    public static function maxUploadBytes(): int
    {
        $limits = array_filter([
            self::iniBytes('upload_max_filesize'),
            self::iniBytes('post_max_size'),
        ], static fn(int $v): bool => $v > 0);

        return $limits === [] ? PHP_INT_MAX : min($limits);
    }

    /** Tamanho máximo do POST inteiro (todas as imagens enviadas juntas). */
    public static function maxPostBytes(): int
    {
        $value = self::iniBytes('post_max_size');
        return $value > 0 ? $value : PHP_INT_MAX;
    }

    /** Converte valores do php.ini como "8M" ou "1G" em bytes. */
    public static function iniBytes(string $directive): int
    {
        $raw = trim((string) ini_get($directive));
        if ($raw === '') {
            return 0;
        }
        $unit   = strtolower(substr($raw, -1));
        $number = (int) $raw;

        return match ($unit) {
            'g'     => $number * 1024 ** 3,
            'm'     => $number * 1024 ** 2,
            'k'     => $number * 1024,
            default => $number,
        };
    }
}
