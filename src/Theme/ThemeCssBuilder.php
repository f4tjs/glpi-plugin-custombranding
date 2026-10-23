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

use GlpiPlugin\Custombranding\Config\BrandingConfig;
use GlpiPlugin\Custombranding\Config\Color;
use GlpiPlugin\Custombranding\Image\ImageSlot;
use GlpiPlugin\Custombranding\Urls;

/**
 * Gera a folha de estilo servida em /plugins/custombranding/theme.css.
 *
 * Estratégia: o GLPI 12 desenha os logos como imagens de fundo ligadas a
 * variáveis CSS (--glpi-logo-*) e as cores por variáveis (--tblr-*,
 * --glpi-mainmenu-*). Basta redefinir essas variáveis — sem tocar em
 * arquivos do núcleo e sem JavaScript manipulando o DOM.
 *
 * Especificidade: ":root:root[data-glpi-theme]" vence as paletas do GLPI
 * (":root[data-glpi-theme=x]"), e a folha do plugin é carregada depois.
 *
 * Responsividade:
 *  - logos usam "contain" (qualquer proporção, sem distorção) e limites em vw;
 *  - o fundo do login usa "cover" numa camada fixa do tamanho da viewport,
 *    com imagem própria para celular em retrato (se enviada);
 *  - em telas muito grandes (≥ 1920 px / 4K) o logo do login cresce um pouco.
 *
 * Segurança: todos os valores interpolados são cores já normalizadas
 * (#rrggbb), inteiros ou URLs geradas pelo próprio plugin.
 */
final class ThemeCssBuilder
{
    public function __construct(private readonly BrandingConfig $config) {}

    public function build(): string
    {
        $blocks = array_filter([
            $this->header(),
            $this->logos(),
            $this->loginBackground(),
            $this->colors(),
            $this->loginFooter(),
        ]);

        return implode("\n\n", $blocks) . "\n";
    }

    private function header(): string
    {
        return sprintf("/* Custom Branding — gerado automaticamente (rev. %s). Não edite. */", $this->config->revision());
    }

    private function logos(): string
    {
        $vars = [];
        $css  = [];

        if ($url = $this->imageUrl(ImageSlot::MenuLogo)) {
            $vars[] = "--glpi-logo-light: url(\"$url\");";
            $vars[] = "--glpi-logo-dark: url(\"$url\");";
            // O GLPI não define background-size para o logo do menu:
            // sem isto uma imagem 2x (HiDPI) apareceria cortada.
            $css[] = <<<CSS
            .page .glpi-logo {
                background-size: contain !important;
                background-position: center !important;
            }
            CSS;
        }

        if ($url = $this->imageUrl(ImageSlot::MenuLogoReduced)) {
            $vars[] = "--glpi-logo-light-reduced: url(\"$url\");";
            $vars[] = "--glpi-logo-dark-reduced: url(\"$url\");";
        }

        if ($url = $this->imageUrl(ImageSlot::LoginLogo)) {
            $vars[] = "--glpi-logo-light-login: url(\"$url\");";
            $vars[] = "--glpi-logo-dark-login: url(\"$url\");";
            // O núcleo usa "content: url()" (estica a imagem). Trocamos por
            // fundo com "contain" para respeitar qualquer proporção.
            $css[] = <<<CSS
            .page-anonymous .glpi-logo {
                content: none !important;
                display: inline-block;
                width: 200px;
                height: 110px;
                max-width: 70vw;
                background: var(--logo) center / contain no-repeat;
            }
            @media (min-width: 1920px) {
                .page-anonymous .glpi-logo { width: 260px; height: 143px; }
            }
            @media (min-width: 3000px) {
                .page-anonymous .glpi-logo { width: 340px; height: 187px; }
            }
            CSS;
        }

        if ($vars === []) {
            return '';
        }

        return ":root:root {\n    " . implode("\n    ", $vars) . "\n}\n\n" . implode("\n\n", $css);
    }

    private function loginBackground(): string
    {
        $desktop = $this->imageUrl(ImageSlot::LoginBackground);
        $mobile  = $this->imageUrl(ImageSlot::LoginBackgroundMob);
        if ($desktop === null && $mobile === null) {
            return '';
        }

        $alpha   = number_format($this->config->loginOverlay / 100, 2, '.', '');
        $overlay = "linear-gradient(rgba(0, 0, 0, $alpha), rgba(0, 0, 0, $alpha))";

        $css = [];

        // Camada fixa do tamanho da tela: funciona igual em desktop e celular
        // (evita os problemas de "background-attachment: fixed" no iOS).
        $css[] = <<<CSS
        body.welcome-anonymous::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background: $overlay, url("{$this->fallback($desktop, $mobile)}") center / cover no-repeat;
        }
        /* Textos fora do cartão ficam legíveis sobre a foto. */
        body.welcome-anonymous .container-tight > .text-center,
        body.welcome-anonymous .container-tight > .text-center a {
            color: #fff !important;
            text-shadow: 0 1px 3px rgba(0, 0, 0, .65);
        }
        body.welcome-anonymous .main-content-card {
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, .25);
        }
        CSS;

        if ($desktop !== null && $mobile !== null) {
            $css[] = <<<CSS
            @media (orientation: portrait) and (max-width: 991.98px) {
                body.welcome-anonymous::before {
                    background-image: $overlay, url("$mobile");
                }
            }
            CSS;
        }

        // Celulares: margens laterais menores para aproveitar a tela.
        $css[] = <<<CSS
        @media (max-width: 575.98px) {
            body.welcome-anonymous .container-tight { padding-left: .75rem; padding-right: .75rem; }
        }
        CSS;

        return implode("\n\n", $css);
    }

    private function colors(): string
    {
        if (!$this->config->colorsEnabled) {
            return '';
        }

        $primary   = $this->config->primaryColor;
        $primaryFg = Color::readableForeground($primary);
        $menuBg    = $this->config->menuBgColor;
        $menuFg    = $this->config->menuFgColor;
        $rgb       = Color::rgbTriplet($primary);

        return <<<CSS
        :root:root[data-glpi-theme] {
            --tblr-primary-rgb: $rgb;
            --tblr-primary: rgb(var(--tblr-primary-rgb));
            --tblr-primary-fg: $primaryFg;
            --tblr-link-color-rgb: $rgb;
            --glpi-mainmenu-bg: $menuBg;
            --glpi-mainmenu-fg: $menuFg;
            --glpi-mainmenu-fg-muted: {$menuFg}99;
        }
        CSS;
    }

    /**
     * O GLPI envolve a saída do hook "display_login" numa coluna
     * (.col-auto) ao lado do formulário. Fazer essa coluna ocupar 100% da
     * linha coloca o texto abaixo do botão "Entrar" em qualquer tela.
     */
    private function loginFooter(): string
    {
        if ($this->config->loginFooter === '') {
            return '';
        }

        return <<<CSS
        .page-anonymous .row > :has(> .custombranding-login-footer) {
            flex: 0 0 100%;
            max-width: 100%;
            order: 99;
        }
        .page-anonymous .custombranding-login-footer {
            overflow-wrap: anywhere;
        }
        CSS;
    }

    private function imageUrl(ImageSlot $slot): ?string
    {
        $image = $this->config->image($slot);
        return $image !== null ? Urls::image($slot, $image) : null;
    }

    private function fallback(?string ...$urls): string
    {
        foreach ($urls as $url) {
            if ($url !== null) {
                return $url;
            }
        }
        return '';
    }
}
