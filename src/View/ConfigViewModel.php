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

namespace GlpiPlugin\Custombranding\View;

use GlpiPlugin\Custombranding\Config\BrandingConfig;
use GlpiPlugin\Custombranding\Image\Formatter;
use GlpiPlugin\Custombranding\Image\ImageFormat;
use GlpiPlugin\Custombranding\Image\ImageSlot;
use GlpiPlugin\Custombranding\Image\UploadLimits;
use GlpiPlugin\Custombranding\Urls;

/**
 * Prepara os dados da tela de configuração (template Twig + JavaScript).
 *
 * Mantém o template "burro": nenhuma regra de negócio no Twig, apenas
 * apresentação. As regras dos slots vão para o JS em JSON, garantindo que
 * a validação no navegador use exatamente os mesmos números do servidor.
 */
final class ConfigViewModel
{
    public function __construct(private readonly BrandingConfig $config) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'action'   => Urls::configPage(),
            'config'   => $this->config,
            'limits'   => [
                'max_upload' => Formatter::bytes(UploadLimits::maxUploadBytes()),
                'max_post'   => Formatter::bytes(UploadLimits::maxPostBytes()),
            ],
            'max'      => [
                'app_name'     => BrandingConfig::APP_NAME_MAX,
                'login_footer' => BrandingConfig::LOGIN_FOOTER_MAX,
                'overlay'      => BrandingConfig::OVERLAY_MAX,
            ],
            'groups'   => $this->groups(),
            'js_data'  => [
                'slots'    => $this->slotsForJs(),
                'defaults' => $this->glpiDefaults(),
                'postMax'  => UploadLimits::maxPostBytes(),
                'i18n'     => $this->jsStrings(),
            ],
        ];
    }

    /**
     * Slots agrupados por área da interface (cada grupo vira um cartão).
     *
     * @return list<array{title: string, icon: string, slots: list<array<string, mixed>>}>
     */
    private function groups(): array
    {
        $group = fn(string $title, string $icon, ImageSlot ...$slots): array => [
            'title' => $title,
            'icon'  => $icon,
            'slots' => array_values(array_map($this->slotView(...), $slots)),
        ];

        return [
            $group(__('Logos', 'custombranding'), 'ti-photo', ImageSlot::LoginLogo, ImageSlot::MenuLogo, ImageSlot::MenuLogoReduced),
            $group(__('Login screen background', 'custombranding'), 'ti-background', ImageSlot::LoginBackground, ImageSlot::LoginBackgroundMob),
            $group(__('Browser', 'custombranding'), 'ti-browser', ImageSlot::Favicon),
        ];
    }

    /** @return array<string, mixed> */
    private function slotView(ImageSlot $slot): array
    {
        $spec  = $slot->spec();
        $image = $this->config->image($slot);

        return [
            'key'     => $slot->value,
            'label'   => $slot->label(),
            'help'    => $slot->help(),
            'icon'    => $slot->icon(),
            'accept'  => implode(',', $spec->mimes),
            'is_photo' => in_array($slot, [ImageSlot::LoginBackground, ImageSlot::LoginBackgroundMob], true),
            'on_menu'  => in_array($slot, [ImageSlot::MenuLogo, ImageSlot::MenuLogoReduced], true),
            'specs'   => [
                ['label' => __('Recommended', 'custombranding'), 'value' => Formatter::dimensions(...$spec->recommended), 'strong' => true],
                ['label' => __('Minimum', 'custombranding'),     'value' => Formatter::dimensions(...$spec->min)],
                ['label' => __('Maximum', 'custombranding'),     'value' => Formatter::dimensions(...$spec->max)],
                ['label' => __('File size', 'custombranding'),   'value' => sprintf(__('up to %s', 'custombranding'), Formatter::bytes($spec->effectiveMaxBytes()))],
                ['label' => __('Formats', 'custombranding'),     'value' => Formatter::formats($spec->mimes)],
                ['label' => __('Displayed at', 'custombranding'), 'value' => Formatter::dimensions(...$spec->display)],
            ],
            'current' => $image === null ? null : [
                'url'        => Urls::image($slot, $image),
                'dimensions' => Formatter::dimensions($image->width, $image->height),
                'size'       => Formatter::bytes($image->bytes),
                'format'     => ImageFormat::labelFor($image->mime),
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function slotsForJs(): array
    {
        $slots = [];
        foreach (ImageSlot::cases() as $slot) {
            $image = $this->config->image($slot);
            $slots[$slot->value] = $slot->spec()->toArray() + [
                'label'   => $slot->label(),
                'current' => $image !== null ? Urls::image($slot, $image) : null,
            ];
        }
        return $slots;
    }

    /**
     * Imagens padrão do GLPI, usadas na prévia quando um slot está vazio.
     *
     * @return array<string, string>
     */
    private function glpiDefaults(): array
    {
        /** @var array{root_doc?: string} $CFG_GLPI */
        global $CFG_GLPI;
        $root = rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/');

        return [
            ImageSlot::LoginLogo->value       => $root . '/pics/logos/logo-GLPI-250-black.png',
            ImageSlot::MenuLogo->value        => $root . '/pics/logos/logo-GLPI-100-white.png',
            ImageSlot::MenuLogoReduced->value => $root . '/pics/logos/logo-G-100-white.png',
            ImageSlot::Favicon->value         => $root . '/pics/favicon.ico',
            'app_name'                        => 'GLPI',
        ];
    }

    /**
     * Mensagens traduzidas usadas pelo JavaScript (mesmas do servidor).
     *
     * @return array<string, string>
     */
    private function jsStrings(): array
    {
        return [
            'compatible'    => __('Compatible', 'custombranding'),
            'attention'     => __('Compatible, with warnings', 'custombranding'),
            'incompatible'  => __('Incompatible — will not be saved', 'custombranding'),
            'reading'       => __('Checking image…', 'custombranding'),
            'tooLarge'      => __('File too large: %1$s (maximum %2$s).', 'custombranding'),
            'format'        => __('Unsupported format (%1$s). Accepted formats: %2$s.', 'custombranding'),
            'corrupted'     => __('The file is not a valid image or is corrupted.', 'custombranding'),
            'tooSmall'      => __('Image too small: %1$s. Minimum: %2$s.', 'custombranding'),
            'tooBig'        => __('Image too large: %1$s. Maximum: %2$s. Reduce it before uploading to keep pages fast.', 'custombranding'),
            'belowRec'      => __('Below the recommended size (%1$s): it may look blurry on Full HD and high-density screens.', 'custombranding'),
            'squareReq'     => __('The image must be square (same width and height). Received: %s.', 'custombranding'),
            'squarePref'    => __('The image is not square: empty margins will appear around it.', 'custombranding'),
            'landscape'     => __('Landscape (horizontal) image expected: large parts will be cropped on wide screens.', 'custombranding'),
            'portrait'      => __('Portrait (vertical) image expected: large parts will be cropped on phones.', 'custombranding'),
            'ratio'         => __('Aspect ratio differs from the ideal %s: edges will be cropped to fill the screen.', 'custombranding'),
            'postTooLarge'  => __('The selected images total %1$s, above the server limit per save (%2$s). Save in steps.', 'custombranding'),
            'lowContrast'   => __('Low contrast (%s:1). WCAG recommends at least 4.5:1.', 'custombranding'),
            'goodContrast'  => __('Good contrast (%s:1).', 'custombranding'),
            'willRemove'    => __('Will be removed when saving (GLPI default restored).', 'custombranding'),
            'newSelected'   => __('New image selected — not saved yet.', 'custombranding'),
            'unknown'       => __('unknown', 'custombranding'),
        ];
    }
}
