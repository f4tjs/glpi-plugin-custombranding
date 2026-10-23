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

use Config;
use Glpi\Controller\AbstractController;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Http\Firewall;
use Glpi\Http\RedirectResponse;
use Glpi\Security\Attribute\SecurityStrategy;
use GlpiPlugin\Custombranding\Config\BrandingConfig;
use GlpiPlugin\Custombranding\Config\Color;
use GlpiPlugin\Custombranding\Image\Formatter;
use GlpiPlugin\Custombranding\Image\ImageSlot;
use GlpiPlugin\Custombranding\Image\ImageStorage;
use GlpiPlugin\Custombranding\Image\ImageValidator;
use GlpiPlugin\Custombranding\Image\UploadLimits;
use GlpiPlugin\Custombranding\Menu;
use GlpiPlugin\Custombranding\Urls;
use GlpiPlugin\Custombranding\View\ConfigViewModel;
use Session;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Tela de configuração: /plugins/custombranding/config
 *
 * Acesso: somente perfis com direito de ALTERAR a configuração geral
 * (Firewall ADMIN_ACCESS + verificação explícita de Config::canUpdate()).
 * CSRF: validado pelo núcleo do GLPI 12 em todo POST (Sec-Fetch-Site/Origin).
 */
final class ConfigController extends AbstractController
{
    public function __construct(
        private readonly ImageValidator $validator = new ImageValidator(),
        private readonly ImageStorage $storage = new ImageStorage(),
    ) {}

    #[Route('/config', name: 'config', methods: ['GET'])]
    #[SecurityStrategy(Firewall::STRATEGY_ADMIN_ACCESS)]
    public function show(): Response
    {
        $this->denyUnlessAllowed();

        return $this->render('@custombranding/config.html.twig', (new ConfigViewModel(BrandingConfig::load()))->toArray() + [
            'title' => Menu::getTypeName(),
            'menu'  => ['config', Menu::class],
        ]);
    }

    #[Route('/config', name: 'config_save', methods: ['POST'])]
    #[SecurityStrategy(Firewall::STRATEGY_ADMIN_ACCESS)]
    public function save(Request $request): Response
    {
        $this->denyUnlessAllowed();
        $redirect = new RedirectResponse(Urls::configPage());

        // Quando o envio excede post_max_size o PHP descarta TUDO em silêncio.
        if ($this->postWasTruncated($request)) {
            Session::addMessageAfterRedirect(sprintf(
                __('The images sent together exceed the server limit (%s). Send fewer images at a time.', 'custombranding'),
                Formatter::bytes(UploadLimits::maxPostBytes())
            ), false, ERROR);
            return $redirect;
        }

        $config = BrandingConfig::load();

        if ($request->request->getBoolean('reset_all')) {
            return $this->resetAll($config, $redirect);
        }

        // O formulário sempre envia "save=1"; um POST sem ele (vazio ou
        // incompleto) não pode apagar os campos de texto/cores.
        if (!$request->request->getBoolean('save')) {
            Session::addMessageAfterRedirect(__('Invalid request: nothing was changed.', 'custombranding'), false, ERROR);
            return $redirect;
        }

        $config->applyInput($request->request->all());
        $hadErrors = false;

        foreach (ImageSlot::cases() as $slot) {
            $hadErrors = !$this->processSlot($slot, $config, $request) || $hadErrors;
        }

        $config->save();

        if ($config->colorsEnabled && Color::contrast($config->menuBgColor, $config->menuFgColor) < Color::WCAG_AA) {
            Session::addMessageAfterRedirect(
                __('Low contrast between menu background and text colors: the menu may be hard to read.', 'custombranding'),
                false,
                WARNING
            );
        }

        Session::addMessageAfterRedirect(
            $hadErrors
                ? __('Settings saved, except for the images listed above.', 'custombranding')
                : __('Settings saved. Reload other open GLPI tabs to see the changes.', 'custombranding'),
            false,
            $hadErrors ? WARNING : INFO
        );

        return $redirect;
    }

    /**
     * Trata um slot: remoção, substituição ou nada.
     *
     * @return bool false se houve erro de validação/gravação
     */
    private function processSlot(ImageSlot $slot, BrandingConfig $config, Request $request): bool
    {
        $current = $config->image($slot);
        $file    = $request->files->get('image_' . $slot->value);

        if ($file instanceof UploadedFile) {
            $result = $this->validator->validate($slot, $file);

            if (!$result->isValid()) {
                $this->flash($slot, $result->errors(), ERROR);
                return false;
            }

            try {
                $config->setImage($slot, $this->storage->store($slot, $file, $result, $current));
            } catch (Throwable $e) {
                /** @var \Psr\Log\LoggerInterface $PHPLOGGER */
                global $PHPLOGGER;
                $PHPLOGGER->error('[custombranding] ' . $e->getMessage(), ['exception' => $e]);
                $this->flash($slot, [__('Could not save the file on the server. Check permissions of the files/_plugins directory.', 'custombranding')], ERROR);
                return false;
            }

            $this->flash($slot, $result->warnings(), WARNING);
            return true;
        }

        if ($current !== null && $request->request->getBoolean('remove_' . $slot->value)) {
            $this->storage->delete($current);
            $config->setImage($slot, null);
        }

        return true;
    }

    private function resetAll(BrandingConfig $config, RedirectResponse $redirect): RedirectResponse
    {
        foreach (ImageSlot::cases() as $slot) {
            if ($image = $config->image($slot)) {
                $this->storage->delete($image);
            }
        }
        BrandingConfig::purge();

        Session::addMessageAfterRedirect(__('All customizations were removed. GLPI default look restored.', 'custombranding'), false, INFO);
        return $redirect;
    }

    /** @param list<string> $messages */
    private function flash(ImageSlot $slot, array $messages, int $level): void
    {
        foreach ($messages as $message) {
            Session::addMessageAfterRedirect(sprintf('%s: %s', $slot->label(), $message), false, $level);
        }
    }

    private function postWasTruncated(Request $request): bool
    {
        $length = (int) $request->server->get('CONTENT_LENGTH', 0);
        return $length > UploadLimits::maxPostBytes()
            && $request->request->count() === 0
            && $request->files->count() === 0;
    }

    private function denyUnlessAllowed(): void
    {
        if (!Config::canUpdate()) {
            throw new AccessDeniedHttpException();
        }
    }
}
