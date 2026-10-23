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
 * Catálogo das imagens personalizáveis ("slots").
 *
 * FONTE ÚNICA DE VERDADE: tudo que depende de um slot (validação no servidor,
 * validação no navegador, textos de ajuda, prévia, CSS gerado, rota de imagem)
 * lê as regras daqui. Para adicionar uma nova imagem basta:
 *   1. criar um novo "case";
 *   2. preencher spec();
 *   3. usá-la no ThemeCssBuilder e na prévia (templates/config.html.twig).
 *
 * Sobre os tamanhos:
 *  - "display" = tamanho em que o GLPI exibe o elemento (pixels CSS);
 *  - "recommended" = 2x o display (ou Full HD no fundo) para ficar nítido em
 *    telas de alta densidade (Retina/4K). Abaixo disso a imagem é aceita,
 *    mas gera um aviso;
 *  - "min"/"max" = limites rígidos; fora deles o envio é recusado.
 */
enum ImageSlot: string
{
    case LoginLogo          = 'logo_login';
    case MenuLogo           = 'logo_menu';
    case MenuLogoReduced    = 'logo_menu_reduced';
    case Favicon            = 'favicon';
    case LoginBackground    = 'login_background';
    case LoginBackgroundMob = 'login_background_mobile';

    /** Formatos raster aceitos (SVG é bloqueado: pode conter scripts). */
    private const array FORMATS_LOGO       = ['image/png', 'image/webp', 'image/jpeg'];
    private const array FORMATS_PHOTO      = ['image/jpeg', 'image/webp', 'image/png'];
    private const array FORMATS_FAVICON    = ['image/png'];

    private const int KB = 1024;
    private const int MB = 1024 * 1024;

    /**
     * Regras do slot.
     */
    public function spec(): ImageSpec
    {
        return match ($this) {
            self::LoginLogo => new ImageSpec(
                display: [200, 110],
                recommended: [400, 220],
                min: [200, 110],
                max: [2000, 1100],
                maxBytes: 1 * self::MB,
                mimes: self::FORMATS_LOGO,
                aspect: AspectRule::Free,
            ),
            self::MenuLogo => new ImageSpec(
                display: [100, 55],
                recommended: [200, 110],
                min: [100, 55],
                max: [1000, 550],
                maxBytes: 1 * self::MB,
                mimes: self::FORMATS_LOGO,
                aspect: AspectRule::Free,
            ),
            self::MenuLogoReduced => new ImageSpec(
                display: [40, 40],
                recommended: [128, 128],
                min: [40, 40],
                max: [512, 512],
                maxBytes: 512 * self::KB,
                mimes: self::FORMATS_LOGO,
                aspect: AspectRule::SquarePreferred,
            ),
            self::Favicon => new ImageSpec(
                display: [32, 32],
                recommended: [192, 192],
                min: [32, 32],
                max: [512, 512],
                maxBytes: 512 * self::KB,
                mimes: self::FORMATS_FAVICON,
                aspect: AspectRule::SquareRequired,
            ),
            self::LoginBackground => new ImageSpec(
                display: [1920, 1080],
                recommended: [1920, 1080],
                min: [1280, 720],
                max: [3840, 2160],
                maxBytes: 5 * self::MB,
                mimes: self::FORMATS_PHOTO,
                aspect: AspectRule::Landscape,
            ),
            self::LoginBackgroundMob => new ImageSpec(
                display: [1080, 1920],
                recommended: [1080, 1920],
                min: [720, 1280],
                max: [2160, 3840],
                maxBytes: 3 * self::MB,
                mimes: self::FORMATS_PHOTO,
                aspect: AspectRule::Portrait,
            ),
        };
    }

    /** Rótulo exibido na tela de configuração. */
    public function label(): string
    {
        return match ($this) {
            self::LoginLogo          => __('Login page logo', 'custombranding'),
            self::MenuLogo           => __('Side menu logo (expanded)', 'custombranding'),
            self::MenuLogoReduced    => __('Side menu logo (collapsed)', 'custombranding'),
            self::Favicon            => __('Browser tab icon (favicon)', 'custombranding'),
            self::LoginBackground    => __('Login background (desktop / large screens)', 'custombranding'),
            self::LoginBackgroundMob => __('Login background (mobile / portrait)', 'custombranding'),
        };
    }

    /** Explicação de onde a imagem aparece e como ela é ajustada. */
    public function help(): string
    {
        return match ($this) {
            self::LoginLogo => __(
                'Shown above the login card. Scaled to fit without distortion. Prefer PNG/WebP with transparent background.',
                'custombranding'
            ),
            self::MenuLogo => __(
                'Shown at the top of the side menu, over the menu color. Use a light version of the logo for dark menus.',
                'custombranding'
            ),
            self::MenuLogoReduced => __(
                'Shown when the side menu is collapsed. Use a square symbol (icon) of the brand.',
                'custombranding'
            ),
            self::Favicon => __(
                'Icon of the browser tab, bookmarks and home screen shortcuts. Square PNG.',
                'custombranding'
            ),
            self::LoginBackground => __(
                'Fills the whole login screen (cropped to cover, centered). Full HD (1920×1080) or higher is recommended.',
                'custombranding'
            ),
            self::LoginBackgroundMob => __(
                'Optional. Used on phones held in portrait. If empty, the desktop background is cropped automatically.',
                'custombranding'
            ),
        };
    }

    /** Ícone Tabler usado no cabeçalho do cartão do slot. */
    public function icon(): string
    {
        return match ($this) {
            self::LoginLogo, self::MenuLogo => 'ti-photo',
            self::MenuLogoReduced           => 'ti-square-rounded',
            self::Favicon                   => 'ti-browser',
            self::LoginBackground           => 'ti-device-desktop',
            self::LoginBackgroundMob        => 'ti-device-mobile',
        };
    }
}
