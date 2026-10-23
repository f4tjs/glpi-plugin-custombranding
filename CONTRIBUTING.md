# Contribuindo / Contributing

Obrigado pelo interesse! / Thanks for your interest!

## Ambiente / Setup

1. Instale um GLPI 12 de desenvolvimento (ou use a imagem Docker `glpi/glpi`).
2. Clone este repositório em `<glpi>/plugins/custombranding` (o nome da pasta precisa ser `custombranding`).
3. Instale e ative: `php bin/console plugin:install custombranding -u <admin>` e `php bin/console plugin:activate custombranding`.

## Padrões / Standards

- PHP 8.3+, tipos declarados em parâmetros, propriedades e retornos, classes `final` quando possível, PSR-4 (`GlpiPlugin\Custombranding\` → `src/`).
- Regras de imagem ficam **somente** em `src/Image/ImageSlot.php` (o JavaScript recebe os mesmos números em JSON).
- Textos de interface em inglês com `__('texto', 'custombranding')` e tradução em `locales/pt_BR.po`.
- Comentários de código em português, explicando o **porquê**.
- Nada de alterar arquivos do núcleo do GLPI.

## Antes de abrir o PR / Before opening a PR

```bash
phpstan analyse                 # nível 8, sem erros (com o plugin dentro de um GLPI 12)
composer locales                # extrai strings novas, atualiza e compila pt_BR
```

- Teste em telas grandes e no celular (a tela de configuração e o login).
- Atualize o `CHANGELOG.md` na seção `[Unreleased]`.
- Mensagens de commit no padrão [Conventional Commits](https://www.conventionalcommits.org/pt-br/) (`feat:`, `fix:`, `docs:`…).

## Publicando uma versão / Releasing

1. Atualize `PLUGIN_CUSTOMBRANDING_VERSION` em `setup.php`, a versão em `plugin.xml` e o `CHANGELOG.md`.
2. Crie a tag: `git tag v1.1.0 && git push --tags`.
3. O workflow **Release** gera `custombranding-1.1.0.zip` e publica na página de Releases.
