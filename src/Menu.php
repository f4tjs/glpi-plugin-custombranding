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

use CommonGLPI;
use Config;

/**
 * Entrada "Personalização da marca" no menu Configurar.
 * Visível apenas para quem pode ALTERAR a configuração geral do GLPI.
 */
final class Menu extends CommonGLPI
{
    public static string $rightname = 'config';

    public static function getTypeName($nb = 0): string
    {
        return __('Custom Branding', 'custombranding');
    }

    public static function getMenuName(): string
    {
        return self::getTypeName();
    }

    public static function getIcon(): string
    {
        return 'ti ti-palette';
    }

    public static function canView(): bool
    {
        return Config::canUpdate();
    }

    /**
     * @return array<string, mixed>|false
     */
    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }

        return [
            'title' => self::getMenuName(),
            'page'  => Urls::relative('config'),
            'icon'  => self::getIcon(),
        ];
    }
}
