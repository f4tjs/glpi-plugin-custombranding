<?php

/**
 * -------------------------------------------------------------------------
 * Custom Branding — plugin de personalização de marca para o GLPI 12
 * -------------------------------------------------------------------------
 *
 * Instalação e desinstalação (chamadas pelo GLPI em Configurar > Plugins).
 *
 * O plugin não cria tabelas próprias: usa a tabela de configuração do GLPI
 * (glpi_configs, contexto "plugin:custombranding") e guarda as imagens em
 * files/_plugins/custombranding/.
 *
 * @author    F4tjs <https://github.com/f4tjs>
 * @copyright 2026 F4tjs
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/f4tjs/glpi-plugin-custombranding
 * -------------------------------------------------------------------------
 */

use GlpiPlugin\Custombranding\Config\BrandingConfig;
use GlpiPlugin\Custombranding\Image\ImageStorage;

/**
 * Instalação: cria a pasta de imagens e grava os valores padrão
 * sem sobrescrever uma configuração existente (reinstalação/atualização).
 */
function plugin_custombranding_install(): bool
{
    (new ImageStorage())->ensureDirectory();

    $existing = Config::getConfigurationValues(BrandingConfig::CONTEXT);
    $missing  = array_diff_key(BrandingConfig::DEFAULTS, $existing);
    if ($missing !== []) {
        Config::setConfigurationValues(BrandingConfig::CONTEXT, $missing);
    }

    return true;
}

/**
 * Desinstalação: remove configuração e imagens. O GLPI volta exatamente ao
 * visual original, pois nenhum arquivo do núcleo foi alterado.
 */
function plugin_custombranding_uninstall(): bool
{
    BrandingConfig::purge();
    (new ImageStorage())->purge();

    return true;
}
