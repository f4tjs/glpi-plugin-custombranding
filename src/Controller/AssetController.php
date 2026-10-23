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

namespace GlpiPlugin\Custombranding\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Http\Firewall;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Custombranding\Config\BrandingConfig;
use GlpiPlugin\Custombranding\Image\ImageSlot;
use GlpiPlugin\Custombranding\Image\ImageStorage;
use GlpiPlugin\Custombranding\Theme\ThemeCssBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recursos PÚBLICOS (precisam funcionar na tela de login, sem sessão):
 *
 *   GET /plugins/custombranding/theme.css        → CSS gerado
 *   GET /plugins/custombranding/asset/{slot}     → imagem de um slot
 *
 * Por que é seguro expor sem autenticação:
 *  - o parâmetro {slot} só aceita nomes do enum ImageSlot (lista fechada);
 *    não existe nenhum caminho de arquivo vindo do usuário;
 *  - o nome do arquivo em disco é revalidado (StoredImage::fromJson);
 *  - o Content-Type vem da lista fixa de formatos, com "nosniff" e uma CSP
 *    que impede qualquer execução caso o arquivo seja aberto diretamente;
 *  - são apenas elementos visuais que já aparecem na tela de login.
 *
 * Cache: as URLs carregam a versão (?v= / ?rev=), então as respostas podem
 * ser guardadas por 1 ano ("immutable"); trocar a imagem muda a URL.
 */
final class AssetController extends AbstractController
{
    private const int CACHE_SECONDS = 31536000;

    #[Route('/asset/{slot}', name: 'asset', requirements: ['slot' => '[a-z_]{1,40}'], methods: ['GET', 'HEAD'])]
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    public function image(Request $request, string $slot): Response
    {
        $slotEnum = ImageSlot::tryFrom($slot) ?? throw new NotFoundHttpException();
        $image    = BrandingConfig::load()->image($slotEnum) ?? throw new NotFoundHttpException();
        $path     = (new ImageStorage())->pathFor($image) ?? throw new NotFoundHttpException();

        $response = new BinaryFileResponse($path, Response::HTTP_OK, [], false, ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('Content-Type', $image->mime);
        $response->setEtag($image->hash);
        $this->applySecurityAndCache($response, $request->query->get('v') === $image->hash);
        $response->isNotModified($request);

        return $response;
    }

    #[Route('/theme.css', name: 'theme_css', methods: ['GET', 'HEAD'])]
    #[SecurityStrategy(Firewall::STRATEGY_NO_CHECK)]
    public function stylesheet(Request $request): Response
    {
        $config   = BrandingConfig::load();
        $response = new Response((new ThemeCssBuilder($config))->build());
        $response->headers->set('Content-Type', 'text/css; charset=UTF-8');
        $response->setEtag($config->revision());

        // Só cacheia por longo prazo se a URL tiver a revisão ATUAL.
        $this->applySecurityAndCache($response, $request->query->get('rev') === $config->revision());
        $response->isNotModified($request);

        return $response;
    }

    private function applySecurityAndCache(Response $response, bool $versioned): void
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if ($versioned) {
            $response->setPublic();
            $response->setMaxAge(self::CACHE_SECONDS);
            $response->headers->addCacheControlDirective('immutable');
        } else {
            // Sem versão: revalida sempre (ETag → 304 barato).
            $response->setPublic();
            $response->headers->addCacheControlDirective('no-cache');
        }
    }
}
