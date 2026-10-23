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

namespace GlpiPlugin\Custombranding;

use GlpiPlugin\Custombranding\Image\ImageSlot;
use GlpiPlugin\Custombranding\Image\StoredImage;

/**
 * Montagem centralizada das URLs do plugin (respeita instalações do GLPI
 * em subdiretório, ex.: https://servidor/glpi/).
 */
final class Urls
{
    public const string PLUGIN_KEY = 'custombranding';

    /** Caminho relativo à raiz do GLPI (formato esperado pelos menus). */
    public static function relative(string $path = ''): string
    {
        return '/plugins/' . self::PLUGIN_KEY . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    /** Caminho absoluto, incluindo o subdiretório do GLPI (root_doc). */
    public static function absolute(string $path = ''): string
    {
        /** @var array{root_doc?: string} $CFG_GLPI */
        global $CFG_GLPI;
        return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . self::relative($path);
    }

    public static function configPage(): string
    {
        return self::absolute('config');
    }

    /** URL pública da imagem, versionada pelo hash do conteúdo. */
    public static function image(ImageSlot $slot, StoredImage $image): string
    {
        return self::absolute('asset/' . $slot->value) . '?v=' . rawurlencode($image->hash);
    }
}
