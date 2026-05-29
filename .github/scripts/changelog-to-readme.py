#!/usr/bin/env python3
"""Convert release-please CHANGELOG.md into a readme.txt == Changelog == section.

Reads the source readme.txt, replaces the existing `== Changelog ==` section
(everything from that heading to the next `== Heading ==` or EOF) with a freshly
generated one based on CHANGELOG.md, and writes the result back.

CHANGELOG.md format produced by release-please looks like:

    # Changelog

    ## [1.2.0](https://github.com/.../compare/v1.1.0...v1.2.0) (2026-05-29)

    ### 🚀 Nové funkce

    * GitHub-driven auto-updates via Plugin Update Checker ([e29f487](url))

    ### 🐛 Opravy chyb

    ...

readme.txt expects the WordPress plugin format:

    == Changelog ==

    = 1.2.0 =
    * 🚀 Nové funkce: GitHub-driven auto-updates ...
    * 🐛 Opravy chyb: ...

    = 1.1.0 =
    ...
"""

from __future__ import annotations

import re
import sys
from pathlib import Path


VERSION_HEADING_RE = re.compile(r"^## \[?(?P<version>[0-9]+\.[0-9]+\.[0-9]+)\]?")
SECTION_HEADING_RE = re.compile(r"^### (?P<title>.+?)\s*$")
BULLET_RE = re.compile(r"^\* (?P<text>.+?)\s*$")
LINK_RE = re.compile(r"\s*\(\[[0-9a-f]+\]\([^)]+\)\)\s*$")


def parse_changelog(text: str) -> list[tuple[str, list[str]]]:
    """Return [(version, [bullet, ...]), ...] in source order (newest first)."""
    entries: list[tuple[str, list[str]]] = []
    current_version: str | None = None
    current_section: str | None = None
    current_bullets: list[str] = []

    def flush():
        nonlocal current_version, current_bullets
        if current_version is not None:
            entries.append((current_version, current_bullets))
        current_version = None
        current_bullets = []

    for raw in text.splitlines():
        m_ver = VERSION_HEADING_RE.match(raw)
        if m_ver:
            flush()
            current_version = m_ver.group("version")
            current_section = None
            current_bullets = []
            continue

        if current_version is None:
            continue

        m_sec = SECTION_HEADING_RE.match(raw)
        if m_sec:
            current_section = m_sec.group("title").strip()
            continue

        m_bul = BULLET_RE.match(raw)
        if m_bul:
            text = LINK_RE.sub("", m_bul.group("text"))
            prefix = f"{current_section}: " if current_section else ""
            current_bullets.append(prefix + text.strip())

    flush()
    return entries


def render_section(entries: list[tuple[str, list[str]]]) -> str:
    out: list[str] = ["== Changelog ==", ""]
    for version, bullets in entries:
        out.append(f"= {version} =")
        if not bullets:
            out.append("* (žádné záznamy)")
        else:
            out.extend(f"* {b}" for b in bullets)
        out.append("")
    return "\n".join(out).rstrip() + "\n"


def replace_changelog_section(readme: str, new_section: str) -> str:
    """Replace the `== Changelog ==` block (up to next `== Heading ==` or EOF)."""
    pattern = re.compile(
        r"^== Changelog ==\s*$.*?(?=^== |\Z)",
        re.MULTILINE | re.DOTALL,
    )
    if pattern.search(readme):
        return pattern.sub(new_section.rstrip() + "\n\n", readme, count=1).rstrip() + "\n"
    # Append at end if missing.
    sep = "" if readme.endswith("\n") else "\n"
    return f"{readme}{sep}\n{new_section}"


def main(argv: list[str]) -> int:
    if len(argv) != 3:
        print("usage: changelog-to-readme.py CHANGELOG.md readme.txt", file=sys.stderr)
        return 2

    changelog_path = Path(argv[1])
    readme_path = Path(argv[2])

    entries = parse_changelog(changelog_path.read_text(encoding="utf-8"))
    if not entries:
        print("warning: no version entries found in changelog", file=sys.stderr)
        return 0

    new_section = render_section(entries)
    readme = readme_path.read_text(encoding="utf-8")
    readme_path.write_text(replace_changelog_section(readme, new_section), encoding="utf-8")
    print(f"updated {readme_path} with {len(entries)} version(s)")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
