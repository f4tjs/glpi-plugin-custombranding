<?php

/**
 * -------------------------------------------------------------------------
 * Custom Branding — plugin de personalização de marca para o GLPI 12
 * -------------------------------------------------------------------------
 *
 * Ponto de entrada do plugin. O GLPI carrega este arquivo para:
 *  - descobrir nome, versão e requisitos (plugin_version_custombranding);
 *  - registrar os hooks a cada requisição (plugin_init_custombranding).
 *
 * Princípios adotados (diferenças em relação a plugins legados):
 *  - NENHUM arquivo do núcleo do GLPI é alterado: logos e cores são aplicados
 *    sobrescrevendo variáveis CSS do próprio GLPI em uma folha de estilo gerada;
 *  - imagens ficam fora da raiz web (files/_plugins/custombranding) e são
 *    servidas por rota própria que só aceita nomes de slot conhecidos;
 *  - toda a lógica fica em classes PSR-4 em src/ (namespace GlpiPlugin\Custombranding).
 *
 * @author    F4tjs <https://github.com/f4tjs>
 * @copyright 2026 F4tjs
 * @license   GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/f4tjs/glpi-plugin-custombranding
 * -------------------------------------------------------------------------
 */

use Glpi\Http\SessionManager;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Custombranding\Config\BrandingConfig;
use GlpiPlugin\Custombranding\Menu;
use GlpiPlugin\Custombranding\Theme\HookRegistrar;

/** Versão do plugin (manter em sincronia com plugin.xml e CHANGELOG.md). */
define('PLUGIN_CUSTOMBRANDING_VERSION', '1.0.0');

/** Faixa de versões do GLPI suportadas (min inclusiva, max exclusiva). */
define('PLUGIN_CUSTOMBRANDING_MIN_GLPI', '12.0.0');
define('PLUGIN_CUSTOMBRANDING_MAX_GLPI', '12.99.99');

/**
 * Boot: executado bem cedo, ANTES de a sessão PHP ser iniciada.
 * É o único momento válido para declarar caminhos "stateless".
 */
function plugin_custombranding_boot(): void
{
    // Folha de estilo dinâmica e imagens não usam sessão: ficam "stateless",
    // o que permite cache HTTP agressivo (sem cookie de sessão na resposta).
    SessionManager::registerPluginStatelessPath('custombranding', '#^/(theme\.css|asset/)#');
}

/**
 * Inicialização: executada em TODA requisição enquanto o plugin estiver ativo.
 * Mantenha leve — apenas registro de hooks, sem consultas pesadas.
 */
function plugin_init_custombranding(): void
{
    /** @var array<string, array<string, mixed>> $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    // Obs.: no GLPI 12 a proteção CSRF é feita pelo núcleo (cabeçalhos
    // Sec-Fetch-Site/Origin), não há mais o hook "csrf_compliant".

    if (!Plugin::isPluginActive('custombranding')) {
        return;
    }

    // Página de configuração (link no nome do plugin em Configurar > Plugins)
    // e entrada no menu Configurar.
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['custombranding'] = 'config';
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['custombranding']  = ['config' => [Menu::class]];

    // CSS, favicon, nome da aplicação e rodapé do login.
    HookRegistrar::register(BrandingConfig::load());
}

/**
 * Metadados exibidos em Configurar > Plugins e verificação de compatibilidade.
 *
 * @return array<string, mixed>
 */
function plugin_version_custombranding(): array
{
    return [
        'name'         => 'Custom Branding',
        'version'      => PLUGIN_CUSTOMBRANDING_VERSION,
        'author'       => 'F4tjs',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://github.com/f4tjs/glpi-plugin-custombranding',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_CUSTOMBRANDING_MIN_GLPI,
                'max' => PLUGIN_CUSTOMBRANDING_MAX_GLPI,
            ],
            'php' => [
                'min'  => '8.3',
                'exts' => [
                    // finfo: detecção do tipo real (MIME) das imagens enviadas.
                    'fileinfo' => ['required' => true, 'class' => 'finfo'],
                    // exif (opcional): corrige a orientação de fotos de celular.
                    'exif'     => ['required' => false, 'function' => 'exif_read_data'],
                ],
            ],
        ],
    ];
}

/**
 * Pré-requisitos adicionais (além do bloco "requirements" acima).
 */
function plugin_custombranding_check_prerequisites(): bool
{
    return true;
}

/**
 * Verificação de configuração antes da ativação.
 */
function plugin_custombranding_check_config(bool $verbose = false): bool
{
    return true;
}
