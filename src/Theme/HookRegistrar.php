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

namespace GlpiPlugin\Custombranding\Theme;

use Glpi\Plugin\Hooks;
use GlpiPlugin\Custombranding\Config\BrandingConfig;
use GlpiPlugin\Custombranding\Image\ImageSlot;
use GlpiPlugin\Custombranding\Urls;

/**
 * Registra, a cada requisição, os hooks visuais conforme a configuração.
 * Só registra o que estiver configurado: sem personalização, o GLPI fica
 * exatamente como o original e nenhuma requisição extra é feita.
 */
final class HookRegistrar
{
    private const string KEY = Urls::PLUGIN_KEY;

    public static function register(BrandingConfig $config): void
    {
        self::registerStylesheet($config);
        self::registerFavicon($config);
        self::registerAppName($config);
        self::registerLoginFooter($config);
    }

    /** CSS gerado, em páginas logadas e anônimas (login, recuperação de senha...). */
    private static function registerStylesheet(BrandingConfig $config): void
    {
        global $PLUGIN_HOOKS;

        if (!$config->hasCustomizations()) {
            return;
        }

        // ?rev muda quando a configuração muda → cache do navegador invalidado.
        $file = 'theme.css?rev=' . $config->revision();
        $PLUGIN_HOOKS[Hooks::ADD_CSS][self::KEY]                = [$file];
        $PLUGIN_HOOKS[Hooks::ADD_CSS_ANONYMOUS_PAGE][self::KEY] = [$file];
    }

    /**
     * Favicon: o GLPI declara o próprio ícone de forma fixa no <head>; um
     * pequeno script (public/js/favicon.js) remove o ícone padrão para que
     * o navegador use o do plugin.
     */
    private static function registerFavicon(BrandingConfig $config): void
    {
        global $PLUGIN_HOOKS;

        $image = $config->image(ImageSlot::Favicon);
        if ($image === null) {
            return;
        }

        $url  = Urls::image(ImageSlot::Favicon, $image);
        $tags = [
            ['tag' => 'link', 'properties' => [
                'rel' => 'icon', 'type' => $image->mime, 'sizes' => "{$image->width}x{$image->height}",
                'href' => $url, 'data-custombranding' => 'favicon',
            ]],
            ['tag' => 'link', 'properties' => [
                'rel' => 'apple-touch-icon', 'href' => $url, 'data-custombranding' => 'favicon',
            ]],
        ];

        foreach ([Hooks::ADD_HEADER_TAG, Hooks::ADD_HEADER_TAG_ANONYMOUS_PAGE] as $hook) {
            $PLUGIN_HOOKS[$hook][self::KEY] = $tags;
        }
        foreach ([Hooks::ADD_JAVASCRIPT, Hooks::ADD_JAVASCRIPT_ANONYMOUS_PAGE] as $hook) {
            $PLUGIN_HOOKS[$hook][self::KEY] = ['js/favicon.js'];
        }
    }

    /**
     * Nome da aplicação: o GLPI usa $CFG_GLPI['app_name'] no título da aba,
     * no rodapé das notificações por e-mail e no rótulo do 2FA (TOTP).
     */
    private static function registerAppName(BrandingConfig $config): void
    {
        global $CFG_GLPI;

        if ($config->appName !== '') {
            $CFG_GLPI['app_name'] = $config->appName;
        }
    }

    private static function registerLoginFooter(BrandingConfig $config): void
    {
        global $PLUGIN_HOOKS;

        if ($config->loginFooter !== '') {
            $PLUGIN_HOOKS[Hooks::DISPLAY_LOGIN][self::KEY] = [self::class, 'displayLogin'];
        }
    }

    /** Saída do hook "display_login" (texto sempre escapado). */
    public static function displayLogin(): void
    {
        $text = BrandingConfig::load()->loginFooter;
        if ($text !== '') {
            echo '<div class="custombranding-login-footer text-center text-secondary small mt-3">'
                . htmlescape($text)
                . '</div>';
        }
    }
}
