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

use Config;
use GlpiPlugin\Custombranding\Image\ImageSlot;
use GlpiPlugin\Custombranding\Image\ImageStorage;
use GlpiPlugin\Custombranding\Image\StoredImage;

/**
 * Configuração do plugin, persistida na tabela glpi_configs
 * (contexto "plugin:custombranding") pela API oficial do GLPI.
 *
 * Todos os valores passam por normalização na entrada (fromInput) e na
 * leitura (load), então o restante do código pode confiar neles.
 */
final class BrandingConfig
{
    public const string CONTEXT = 'plugin:custombranding';

    /** Limites de texto. */
    public const int APP_NAME_MAX     = 60;
    public const int LOGIN_FOOTER_MAX = 255;

    /** Escurecimento máximo do fundo do login (%), para legibilidade. */
    public const int OVERLAY_MAX = 80;

    /** Valores padrão (iguais ao visual original do GLPI). */
    public const array DEFAULTS = [
        'app_name'       => '',
        'login_footer'   => '',
        'colors_enabled' => 0,
        'primary_color'  => '#2f6fb0',
        'menu_bg_color'  => '#2f3f64',
        'menu_fg_color'  => '#f4f6fa',
        'login_overlay'  => 25,
    ];

    /** Cache por requisição (init do plugin roda em toda página). */
    private static ?self $instance = null;

    /**
     * @param array<string, StoredImage|null> $images Indexado por ImageSlot::value
     */
    private function __construct(
        public string $appName,
        public string $loginFooter,
        public bool $colorsEnabled,
        public string $primaryColor,
        public string $menuBgColor,
        public string $menuFgColor,
        public int $loginOverlay,
        private array $images,
    ) {}

    /** Carrega a configuração salva (com cache por requisição). */
    public static function load(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $raw = array_merge(self::DEFAULTS, Config::getConfigurationValues(self::CONTEXT));

        // Imagem registrada mas ausente do disco (ex.: restauração só do
        // banco, ou outro nó sem a pasta files/ compartilhada) é tratada como
        // "sem imagem": o GLPI volta ao padrão em vez de exibir um logo quebrado.
        $storage = new ImageStorage();
        $images  = [];
        foreach (ImageSlot::cases() as $slot) {
            $image = StoredImage::fromJson($slot, $raw['image_' . $slot->value] ?? null);
            $images[$slot->value] = ($image !== null && $storage->pathFor($image) !== null) ? $image : null;
        }

        return self::$instance = new self(
            appName: self::cleanText((string) $raw['app_name'], self::APP_NAME_MAX),
            loginFooter: self::cleanText((string) $raw['login_footer'], self::LOGIN_FOOTER_MAX),
            colorsEnabled: (bool) $raw['colors_enabled'],
            primaryColor: Color::normalize((string) $raw['primary_color'], self::DEFAULTS['primary_color']),
            menuBgColor: Color::normalize((string) $raw['menu_bg_color'], self::DEFAULTS['menu_bg_color']),
            menuFgColor: Color::normalize((string) $raw['menu_fg_color'], self::DEFAULTS['menu_fg_color']),
            loginOverlay: self::clampInt($raw['login_overlay'], 0, self::OVERLAY_MAX),
            images: $images,
        );
    }

    /**
     * Aplica os campos de texto/cor vindos do formulário (já higienizados).
     *
     * @param array<string, mixed> $input
     */
    public function applyInput(array $input): void
    {
        $this->appName       = self::cleanText(self::scalar($input, 'app_name'), self::APP_NAME_MAX);
        $this->loginFooter   = self::cleanText(self::scalar($input, 'login_footer'), self::LOGIN_FOOTER_MAX);
        $this->colorsEnabled = self::scalar($input, 'colors_enabled') === '1';
        $this->primaryColor  = Color::normalize(self::scalar($input, 'primary_color'), $this->primaryColor);
        $this->menuBgColor   = Color::normalize(self::scalar($input, 'menu_bg_color'), $this->menuBgColor);
        $this->menuFgColor   = Color::normalize(self::scalar($input, 'menu_fg_color'), $this->menuFgColor);
        $this->loginOverlay  = self::clampInt(self::scalar($input, 'login_overlay', (string) $this->loginOverlay), 0, self::OVERLAY_MAX);
    }

    public function image(ImageSlot $slot): ?StoredImage
    {
        return $this->images[$slot->value] ?? null;
    }

    public function setImage(ImageSlot $slot, ?StoredImage $image): void
    {
        $this->images[$slot->value] = $image;
    }

    /** Há alguma personalização ativa? (evita servir CSS vazio) */
    public function hasCustomizations(): bool
    {
        return $this->colorsEnabled || $this->loginFooter !== '' || array_filter($this->images) !== [];
    }

    /**
     * Identificador da versão atual da configuração. Muda sempre que algo
     * muda e vai na URL do CSS (?v=...), invalidando o cache do navegador.
     */
    public function revision(): string
    {
        // Inclui a versão do plugin: uma atualização que mude o CSS gerado
        // também invalida o cache, mesmo sem alterar a configuração.
        $payload = json_encode($this->toStorage(), JSON_THROW_ON_ERROR) . PLUGIN_CUSTOMBRANDING_VERSION;
        return substr(hash('sha256', $payload), 0, 12);
    }

    /** Grava tudo no banco. */
    public function save(): void
    {
        Config::setConfigurationValues(self::CONTEXT, $this->toStorage());
        self::$instance = $this;
    }

    /** Remove toda a configuração (desinstalação). */
    public static function purge(): void
    {
        $names = array_keys(self::DEFAULTS);
        foreach (ImageSlot::cases() as $slot) {
            $names[] = 'image_' . $slot->value;
        }
        Config::deleteConfigurationValues(self::CONTEXT, $names);
        self::$instance = null;
    }

    /** @return array<string, string|int> */
    private function toStorage(): array
    {
        $values = [
            'app_name'       => $this->appName,
            'login_footer'   => $this->loginFooter,
            'colors_enabled' => (int) $this->colorsEnabled,
            'primary_color'  => $this->primaryColor,
            'menu_bg_color'  => $this->menuBgColor,
            'menu_fg_color'  => $this->menuFgColor,
            'login_overlay'  => $this->loginOverlay,
        ];
        foreach (ImageSlot::cases() as $slot) {
            $values['image_' . $slot->value] = $this->image($slot)?->toJson() ?? '';
        }
        return $values;
    }

    /**
     * Texto puro: remove tags e caracteres de controle, normaliza espaços
     * e limita o tamanho. A saída ainda é escapada ao ser exibida.
     */
    private static function cleanText(string $value, int $max): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        return mb_substr($value, 0, $max);
    }

    /**
     * Lê um campo do formulário como texto. Valores não escalares (ex.:
     * "app_name[]=x" num POST forjado) são ignorados em vez de gerar avisos.
     *
     * @param array<string, mixed> $input
     */
    private static function scalar(array $input, string $key, string $default = ''): string
    {
        $value = $input[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    private static function clampInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }
}
