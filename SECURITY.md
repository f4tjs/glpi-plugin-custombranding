# Política de segurança / Security policy

## Versões suportadas / Supported versions

| Versão / Version | GLPI | Suporte / Supported |
|---|---|---|
| 1.x | 12.0.x | ✅ |

## Como reportar / Reporting a vulnerability

**Não abra uma issue pública.** Use o recurso
[Report a vulnerability](https://github.com/f4tjs/glpi-plugin-custombranding/security/advisories/new)
(GitHub Security Advisories) com a descrição, os passos para reproduzir e o impacto.

**Do not open a public issue.** Please use
[Report a vulnerability](https://github.com/f4tjs/glpi-plugin-custombranding/security/advisories/new)
with a description, reproduction steps and impact.

Resposta inicial em até 7 dias. / Initial response within 7 days.

## Modelo de segurança / Security model

- Nenhum arquivo do núcleo do GLPI é alterado. / No GLPI core file is modified.
- Imagens ficam fora da raiz web e são servidas por uma rota que aceita apenas nomes de uma lista fechada.
  / Images are stored outside the web root and served by a route that only accepts a closed list of names.
- Tipo real do arquivo validado por conteúdo (`finfo` + `getimagesize`); SVG não é aceito.
  / Real file type is checked by content; SVG is rejected.
- Configuração restrita a perfis com direito de alterar a Configuração geral; CSRF validado pelo núcleo do GLPI 12.
  / Settings restricted to profiles allowed to update general setup; CSRF enforced by GLPI 12 core.
