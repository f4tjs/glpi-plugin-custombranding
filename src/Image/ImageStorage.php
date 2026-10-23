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

use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Armazenamento das imagens em files/_plugins/custombranding/.
 *
 *  - Fora da raiz pública do GLPI: nada é executável nem acessível
 *    diretamente pela web; a entrega passa pela rota AssetController.
 *  - Nome do arquivo fixo por slot ("logo_login.png"), derivado do tipo real.
 *  - O ARQUIVO ORIGINAL é preservado byte a byte (sem redimensionar nem
 *    recomprimir) para manter a qualidade máxima; o controle de peso é feito
 *    pelos limites de dimensão/tamanho do slot.
 *  - Gravação atômica: grava em arquivo temporário e renomeia, evitando que
 *    uma requisição leia um arquivo pela metade.
 */
final class ImageStorage
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? GLPI_PLUGIN_DOC_DIR . '/custombranding';
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Grava uma imagem JÁ VALIDADA e devolve seus metadados.
     *
     * @param StoredImage|null $previous Imagem anterior do slot (removida se tiver outra extensão).
     */
    public function store(ImageSlot $slot, UploadedFile $file, ValidationResult $validation, ?StoredImage $previous): StoredImage
    {
        if (!$validation->isValid() || $validation->mime === null) {
            throw new RuntimeException('Refusing to store an image that did not pass validation.');
        }

        $this->ensureDirectory();

        $filename = $slot->value . '.' . ImageFormat::extensionFor($validation->mime);
        $target   = $this->directory . '/' . $filename;
        $temp     = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';

        // move() do Symfony usa move_uploaded_file() internamente.
        $file->move(dirname($temp), basename($temp));
        @chmod($temp, 0640);

        if (!rename($temp, $target)) {
            @unlink($temp);
            throw new RuntimeException("Unable to write $target");
        }

        // Troca de formato (ex.: .jpg → .webp): remove o arquivo antigo.
        if ($previous !== null && $previous->file !== $filename) {
            $this->deleteFile($previous->file);
        }

        $hash = hash_file('sha256', $target);
        if ($hash === false) {
            throw new RuntimeException("Unable to read $target");
        }

        return new StoredImage(
            file: $filename,
            mime: $validation->mime,
            width: (int) $validation->width,
            height: (int) $validation->height,
            bytes: $validation->bytes,
            hash: substr($hash, 0, 16),
        );
    }

    public function delete(StoredImage $image): void
    {
        $this->deleteFile($image->file);
    }

    /** Caminho absoluto do arquivo, ou null se não existir. */
    public function pathFor(StoredImage $image): ?string
    {
        $path = $this->directory . '/' . basename($image->file);
        return is_file($path) ? $path : null;
    }

    /** Remove todos os arquivos do plugin (usado na desinstalação). */
    public function purge(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->directory);
    }

    public function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new RuntimeException("Unable to create directory {$this->directory}");
        }
    }

    private function deleteFile(string $file): void
    {
        $path = $this->directory . '/' . basename($file);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
