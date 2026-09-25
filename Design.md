# Design Reference

## Kimai pages (plugin UI)

Everything the plugin shows inside Kimai follows the shared UI guidelines for Kimai plugins:
[kimai-plugin-ui](https://github.com/shrippen/kimai-plugin-ui) (`GUIDELINES.md`, `CHECKLIST.md`, kit in `Resources/views/_kit/`).
Kimai core components and Tabler classes only — no own colours, fonts or CSS; the DesignDefault system below does **not**
apply to Kimai pages.

## Landing page

The landing page (`docs/index.html`) and other web-facing assets outside Kimai follow the shared
[shrippen DesignDefault](https://github.com/shrippen/shrippen.github.io) design system.

## Quick Links

- **Full spec**: <https://github.com/shrippen/shrippen.github.io>
- **CSS tokens**: <https://github.com/shrippen/shrippen.github.io/blob/main/tokens/variables.css>
- **Landing page template**: <https://github.com/shrippen/shrippen.github.io/blob/main/templates/landing.html>

## Key Decisions

| Aspect | Choice |
|---|---|
| Palette | Gruvbox-inspired warm dark (`bg0: #282828`, `fg1: #ebdbb2`, accent cream `#e8dcc4`) |
| Headings font | [Rajdhani](https://fonts.google.com/specimen/Rajdhani) 600/700 |
| Body font | System sans stack |
| Code font | JetBrains Mono / Fira Code / Cascadia Code |
| Links / primary action | `--blue: #83a598` |
| Landing page layout | DesignDefault vertical rhythm: icon → name → tagline → badges → install card → CTA → features → prose → footer |
| Max content width | 860px |
| Badges | shields.io with `labelColor=1c1c20`, version `e8dcc4`, tech `83a598`, license `a89984` |
| No light mode | Dark-first only for landing pages |

When making visual changes to the landing page (`docs/index.html`) or any future web-facing assets, consult the DesignDefault README for the full rules.
