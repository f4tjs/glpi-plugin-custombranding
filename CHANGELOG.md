# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/);
versões seguem [SemVer](https://semver.org/lang/pt-BR/).

## [Unreleased]

## [1.0.0] — 2026-10-23

### Adicionado
- Logos personalizados: tela de login, menu lateral expandido e recolhido.
- Favicon e ícone de atalho (apple-touch-icon).
- Fundo da tela de login para computador (Full HD/4K) e para celular (retrato),
  com escurecimento ajustável.
- Cores da marca opcionais (cor principal, fundo e texto do menu) com aviso de contraste WCAG.
- Nome da aplicação (título da aba, e-mails, 2FA) e aviso curto na tela de login.
- Validação de imagens no navegador e no servidor com as mesmas regras
  (tipo real, peso, dimensões mínimas/máximas/recomendadas, proporção).
- Prévia ao vivo: login desktop, login celular, menu e aba do navegador.
- Tela de configuração responsiva, compatível com paletas claras e escuras.
- Traduções pt_BR (texto-fonte em inglês).

### Segurança
- Imagens fora da raiz web, rota com lista fechada de nomes, validação do tipo real,
  SVG bloqueado, `nosniff` + CSP `sandbox`, restrição a administradores, CSRF do núcleo.

[Unreleased]: https://github.com/f4tjs/glpi-plugin-custombranding/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/f4tjs/glpi-plugin-custombranding/releases/tag/v1.0.0
