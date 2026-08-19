# Design Reference

All visual design decisions for this project follow the shared [shrippen DesignDefault](https://github.com/shrippen/DesignDefault) design system.

## Quick Links

- **Full spec**: <https://github.com/shrippen/DesignDefault>
- **CSS tokens**: <https://github.com/shrippen/DesignDefault/blob/main/tokens/variables.css>
- **Landing page template**: <https://github.com/shrippen/DesignDefault/blob/main/templates/landing.html>

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
