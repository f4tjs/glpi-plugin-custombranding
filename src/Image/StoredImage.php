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
 * Metadados de uma imagem já gravada (persistidos como JSON na configuração).
 *
 * O "hash" do conteúdo é usado na URL (?v=hash) para invalidar o cache do
 * navegador apenas quando a imagem realmente muda.
 */
final readonly class StoredImage
{
    public function __construct(
        public string $file,
        public string $mime,
        public int $width,
        public int $height,
        public int $bytes,
        public string $hash,
    ) {}

    public function toJson(): string
    {
        return json_encode(get_object_vars($this), JSON_THROW_ON_ERROR);
    }

    /**
     * Reconstrói a partir do JSON salvo. Retorna null se o valor estiver
     * ausente ou adulterado (nome de arquivo fora do padrão esperado).
     */
    public static function fromJson(ImageSlot $slot, ?string $json): ?self
    {
        if ($json === null || $json === '') {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        // Só aceita valores escalares (JSON adulterado com arrays é descartado).
        $field = static fn(string $key): string => is_scalar($data[$key] ?? null) ? (string) $data[$key] : '';

        $image = new self(
            file: $field('file'),
            mime: $field('mime'),
            width: (int) $field('width'),
            height: (int) $field('height'),
            bytes: (int) $field('bytes'),
            hash: $field('hash'),
        );

        return $image->isConsistentWith($slot) ? $image : null;
    }

    /**
     * Defesa em profundidade: o nome do arquivo precisa ser exatamente
     * "<slot>.<extensão do MIME>" — impede path traversal mesmo que o banco
     * seja alterado manualmente.
     */
    private function isConsistentWith(ImageSlot $slot): bool
    {
        return ImageFormat::isKnown($this->mime)
            && $this->file === $slot->value . '.' . ImageFormat::extensionFor($this->mime)
            && preg_match('/^[a-f0-9]{8,64}$/', $this->hash) === 1;
    }
}
