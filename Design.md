# Design Reference

## Kimai pages (plugin UI)

Everything the plugin shows inside Kimai follows the shared UI guidelines for Kimai plugins:
[kimai-plugin-ui](https://github.com/shrippen/Kante/tree/main/kimai/kit) (`kimai/kit/` in Kante: `GUIDELINES.md`, `CHECKLIST.md`; vendored kit in `Resources/views/_kit/`).
Kimai core components and Tabler classes only — no own colours, fonts or CSS; Kante (below) does **not**
apply to Kimai pages. The shrippen look comes from [Knust](https://github.com/shrippen/Kante/tree/main/kimai/knust) (`kimai/knust/` in Kante, `PLUGINS.md`),
which styles Tabler, Kimai's classes and the kit's `kpu-*` markers; missing elements go to the kit or Knust first
(see `agent.md`).

## Landing page

The landing page (`docs/index.html`) and other web-facing assets outside Kimai are generated from
**Kante**, the shared shrippen design system: <https://github.com/shrippen/Kante> (checkout `../Kante`).
The page links `https://shrippen.github.io/v1/shrippen.css` and `shrippen.js`, follows Kante's
`templates/landing.html` and uses Kante's roles only (`--fg1`, `--primary`, `--link` …), never `#hex`.
Badges, layout and the dark-only rule for landing pages are in Kante's `README.md`; missing elements
go to Kante first. Kante does **not** apply to the pages inside Kimai.
