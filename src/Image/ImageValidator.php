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

use finfo;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validação AUTORITATIVA das imagens enviadas (lado servidor).
 *
 * O navegador faz as mesmas verificações para dar retorno imediato
 * (public/js/config.js), mas a decisão final é sempre tomada aqui.
 *
 * Camadas de segurança, na ordem:
 *  1. upload legítimo do PHP (is_uploaded_file) e sem erro de transferência;
 *  2. tamanho do arquivo dentro do limite do slot;
 *  3. tipo REAL detectado pelo conteúdo (finfo), nunca pela extensão/nome;
 *  4. cabeçalho de imagem válido (getimagesize) e coerente com o tipo;
 *  5. dimensões e proporção dentro das regras do slot.
 *
 * Nenhuma etapa decodifica a imagem inteira em memória: getimagesize lê
 * apenas o cabeçalho, o que evita ataques de "bomba de pixels".
 */
final class ImageValidator
{
    public function validate(ImageSlot $slot, UploadedFile $file): ValidationResult
    {
        $spec   = $slot->spec();
        $result = new ValidationResult();

        // 1) Transferência
        if (!$file->isValid()) {
            return $result->addError($this->uploadErrorMessage($file->getError(), $spec));
        }

        $path          = $file->getPathname();
        $result->bytes = (int) $file->getSize();

        // 2) Tamanho do arquivo
        if ($result->bytes <= 0) {
            return $result->addError(__('The file is empty.', 'custombranding'));
        }
        if ($result->bytes > $spec->effectiveMaxBytes()) {
            return $result->addError(sprintf(
                __('File too large: %1$s (maximum %2$s).', 'custombranding'),
                Formatter::bytes($result->bytes),
                Formatter::bytes($spec->effectiveMaxBytes())
            ));
        }

        // 3) Tipo real pelo conteúdo
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (!in_array($mime, $spec->mimes, true) || !ImageFormat::isKnown($mime)) {
            return $result->addError(sprintf(
                __('Unsupported format (%1$s). Accepted formats: %2$s.', 'custombranding'),
                $mime !== '' ? $mime : __('unknown', 'custombranding'),
                Formatter::formats($spec->mimes)
            ));
        }
        $result->mime = $mime;

        // 4) Cabeçalho de imagem coerente com o tipo detectado
        $info = @getimagesize($path);
        if ($info === false || $info[2] !== ImageFormat::imageTypeFor($mime) || $info[0] < 1 || $info[1] < 1) {
            return $result->addError(__('The file is not a valid image or is corrupted.', 'custombranding'));
        }
        [$width, $height]                 = $this->displayedSize($path, $mime, (int) $info[0], (int) $info[1]);
        [$result->width, $result->height] = [$width, $height];

        // 5) Dimensões e proporção
        $this->checkDimensions($spec, $result, $width, $height);
        $this->checkAspect($spec, $result, $width, $height);

        return $result;
    }

    /**
     * Tamanho como o navegador EXIBE a imagem. Fotos de celular costumam ser
     * gravadas "deitadas" com a tag EXIF Orientation (5 a 8 = girada 90°);
     * navegadores aplicam a rotação, getimagesize() não. Sem esta correção uma
     * foto em retrato seria validada como paisagem.
     * Requer a extensão "exif" do PHP; sem ela, usa o tamanho bruto.
     *
     * @return array{int, int}
     */
    private function displayedSize(string $path, string $mime, int $width, int $height): array
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return [$width, $height];
        }
        $exif        = @exif_read_data($path, 'IFD0');
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return in_array($orientation, [5, 6, 7, 8], true) ? [$height, $width] : [$width, $height];
    }

    private function checkDimensions(ImageSpec $spec, ValidationResult $result, int $w, int $h): void
    {
        [$minW, $minH] = $spec->min;
        [$maxW, $maxH] = $spec->max;
        [$recW, $recH] = $spec->recommended;

        if ($w < $minW || $h < $minH) {
            $result->addError(sprintf(
                __('Image too small: %1$s. Minimum: %2$s.', 'custombranding'),
                Formatter::dimensions($w, $h),
                Formatter::dimensions($minW, $minH)
            ));
            return;
        }

        if ($w > $maxW || $h > $maxH) {
            $result->addError(sprintf(
                __('Image too large: %1$s. Maximum: %2$s. Reduce it before uploading to keep pages fast.', 'custombranding'),
                Formatter::dimensions($w, $h),
                Formatter::dimensions($maxW, $maxH)
            ));
            return;
        }

        // Aceita, mas pode perder nitidez em telas Full HD / HiDPI.
        // Para imagens "ajustadas" (contain/cover) compara-se o lado que
        // efetivamente preenche a área.
        if ($w < $recW && $h < $recH) {
            $result->addWarning(sprintf(
                __('Below the recommended size (%1$s): it may look blurry on Full HD and high-density screens.', 'custombranding'),
                Formatter::dimensions($recW, $recH)
            ));
        }
    }

    private function checkAspect(ImageSpec $spec, ValidationResult $result, int $w, int $h): void
    {
        if (!$result->isValid()) {
            return;
        }

        $ratio    = $w / $h;
        $isSquare = abs($ratio - 1) <= AspectRule::SQUARE_TOLERANCE;

        match ($spec->aspect) {
            AspectRule::SquareRequired => $isSquare ?: $result->addError(sprintf(
                __('The image must be square (same width and height). Received: %s.', 'custombranding'),
                Formatter::dimensions($w, $h)
            )),
            AspectRule::SquarePreferred => $isSquare ?: $result->addWarning(
                __('The image is not square: empty margins will appear around it.', 'custombranding')
            ),
            AspectRule::Landscape => $ratio > 1 ?: $result->addWarning(
                __('Landscape (horizontal) image expected: large parts will be cropped on wide screens.', 'custombranding')
            ),
            AspectRule::Portrait => $ratio < 1 ?: $result->addWarning(
                __('Portrait (vertical) image expected: large parts will be cropped on phones.', 'custombranding')
            ),
            AspectRule::Free => null,
        };

        // Fundos: avisa quando a proporção foge muito da ideal (16:9 / 9:16),
        // pois "cover" vai recortar bordas.
        if (in_array($spec->aspect, [AspectRule::Landscape, AspectRule::Portrait], true)) {
            $ideal = $spec->recommended[0] / $spec->recommended[1];
            if (abs($ratio - $ideal) / $ideal > 0.15) {
                $result->addWarning(sprintf(
                    __('Aspect ratio differs from the ideal %s: edges will be cropped to fill the screen.', 'custombranding'),
                    Formatter::ratio($spec->recommended[0], $spec->recommended[1])
                ));
            }
        }
    }

    private function uploadErrorMessage(int $code, ImageSpec $spec): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                __('File exceeds the server upload limit (%s).', 'custombranding'),
                Formatter::bytes($spec->effectiveMaxBytes())
            ),
            UPLOAD_ERR_PARTIAL    => __('The upload was interrupted. Please try again.', 'custombranding'),
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE,
            UPLOAD_ERR_EXTENSION  => __('The server could not store the uploaded file. Contact the administrator.', 'custombranding'),
            default               => __('Upload failed.', 'custombranding'),
        };
    }
}
