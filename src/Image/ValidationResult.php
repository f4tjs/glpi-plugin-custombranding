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
 * Resultado da validação de uma imagem.
 *
 *  - errors   → bloqueiam o salvamento;
 *  - warnings → a imagem é salva, mas o administrador é avisado
 *               (ex.: abaixo do tamanho recomendado, proporção diferente).
 */
final class ValidationResult
{
    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    public ?int $width = null;
    public ?int $height = null;
    public ?string $mime = null;
    public int $bytes = 0;

    public function addError(string $message): self
    {
        $this->errors[] = $message;
        return $this;
    }

    public function addWarning(string $message): self
    {
        $this->warnings[] = $message;
        return $this;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
