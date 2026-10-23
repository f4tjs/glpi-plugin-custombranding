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
 * Regras de um slot de imagem (objeto imutável).
 *
 * Dimensões são pares [largura, altura] em pixels.
 */
final readonly class ImageSpec
{
    /**
     * @param array{int, int} $display     Tamanho exibido pelo GLPI (px CSS).
     * @param array{int, int} $recommended Tamanho ideal para nitidez em telas HiDPI.
     * @param array{int, int} $min         Mínimo aceito (abaixo: recusado).
     * @param array{int, int} $max         Máximo aceito (acima: recusado).
     * @param int             $maxBytes    Tamanho máximo do arquivo (bytes).
     * @param list<string>    $mimes       Tipos MIME aceitos, em ordem de preferência.
     * @param AspectRule      $aspect      Regra de proporção.
     */
    public function __construct(
        public array $display,
        public array $recommended,
        public array $min,
        public array $max,
        public int $maxBytes,
        public array $mimes,
        public AspectRule $aspect,
    ) {}

    /**
     * Limite efetivo de bytes: o menor entre a regra do slot e os limites
     * de upload do PHP (upload_max_filesize / post_max_size).
     */
    public function effectiveMaxBytes(): int
    {
        return min($this->maxBytes, UploadLimits::maxUploadBytes());
    }

    /**
     * Extensões correspondentes aos MIME aceitos.
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        return array_values(array_unique(array_map(
            static fn(string $mime): string => ImageFormat::extensionFor($mime),
            $this->mimes
        )));
    }

    /**
     * Exporta as regras para o JavaScript da tela de configuração, garantindo
     * que navegador e servidor validem exatamente com os mesmos números.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'display'     => $this->display,
            'recommended' => $this->recommended,
            'min'         => $this->min,
            'max'         => $this->max,
            'maxBytes'    => $this->effectiveMaxBytes(),
            'mimes'       => $this->mimes,
            'extensions'  => $this->extensions(),
            'aspect'      => $this->aspect->value,
            'squareTolerance' => AspectRule::SQUARE_TOLERANCE,
        ];
    }
}
