#!/usr/bin/env python3
"""
Gera locales/custombranding.pot com todas as strings traduzíveis do plugin.

Procura chamadas  __('texto', 'custombranding')  em PHP e Twig
(o xgettext não entende Twig, por isso este extrator simples).

Uso (na raiz do plugin):
    python3 tools/extract_locales.py
    msgmerge --update --backup=none locales/pt_BR.po locales/custombranding.pot
    # traduza as entradas novas/fuzzy em locales/pt_BR.po e compile:
    msgfmt -o locales/pt_BR.mo locales/pt_BR.po
"""
import glob
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PATTERN = re.compile(r"""__\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*,\s*['"]custombranding['"]\s*\)""", re.S)


def po_escape(text: str) -> str:
    return text.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')


def main() -> None:
    entries: dict[str, list[str]] = {}
    files = sorted(
        glob.glob(str(ROOT / 'src/**/*.php'), recursive=True)
        + glob.glob(str(ROOT / '*.php'))
        + glob.glob(str(ROOT / 'templates/**/*.twig'), recursive=True)
    )
    for file in files:
        content = Path(file).read_text(encoding='utf-8')
        for match in PATTERN.finditer(content):
            msgid = match.group(2).replace("\\'", "'")
            line = content.count('\n', 0, match.start()) + 1
            entries.setdefault(msgid, []).append(f"{Path(file).relative_to(ROOT)}:{line}")

    out = [
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: custombranding\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '',
    ]
    for msgid, refs in entries.items():
        out.append('#: ' + ' '.join(refs))
        if '%' in msgid:
            out.append('#, php-format')
        out += [f'msgid "{po_escape(msgid)}"', 'msgstr ""', '']

    target = ROOT / 'locales' / 'custombranding.pot'
    target.parent.mkdir(exist_ok=True)
    target.write_text('\n'.join(out), encoding='utf-8')
    print(f'{len(entries)} strings -> {target.relative_to(ROOT)}')


if __name__ == '__main__':
    main()
