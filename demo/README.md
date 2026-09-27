# Demo (internal)

Internal tool for automated screenshots, not part of any release. Uses the shared demo world of all shrippen projects (shrippen.github.io/demo).

`demo/start.sh [de|en] [default|knust]` starts a local Kimai (Docker) with this plugin and made-up data from
"Studio Weber", the demo world shared by all shrippen projects. The setup lives in the sibling checkout
`shrippen.github.io/demo/kimai/`; `demo/seed.php` adds the plugin's data, `demo/world.json` and
`demo/DemoWorld.php` are copies from there (`shrippen.github.io/demo/tools/sync-demo.py`, do not edit them here). Sign in as
`mara` / `demo-password-1`. `demo/shots.json` describes the screenshots in `docs/shots/`
(`shrippen.github.io/demo/tools/screenshots.py`).
