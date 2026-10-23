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
 * Regra de proporção (largura/altura) de um slot.
 *
 * "Required" gera ERRO (bloqueia o envio); as demais geram apenas AVISO,
 * pois o CSS ajusta a imagem (contain/cover) sem distorcer.
 */
enum AspectRule: string
{
    /** Qualquer proporção: a imagem é ajustada dentro da área sem distorção. */
    case Free = 'free';
    /** Quadrada de preferência; outras proporções ficam com bordas vazias. */
    case SquarePreferred = 'square_preferred';
    /** Precisa ser quadrada (ex.: favicon). */
    case SquareRequired = 'square_required';
    /** Paisagem (largura > altura); ideal 16:9. */
    case Landscape = 'landscape';
    /** Retrato (altura > largura); ideal 9:16. */
    case Portrait = 'portrait';

    /** Tolerância para considerar uma imagem "quadrada" (3%). */
    public const float SQUARE_TOLERANCE = 0.03;
}
