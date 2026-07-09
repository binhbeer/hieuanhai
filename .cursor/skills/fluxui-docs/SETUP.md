# fluxui-docs - Setup

## Install

```bash
mkdir -p ~/.local/bin
ln -sf /home/chinhanh/.cursor/skills/fluxui-docs/fluxui-docs ~/.local/bin/fluxui-docs
chmod +x /home/chinhanh/.cursor/skills/fluxui-docs/fluxui-docs
```

## Verify

```bash
which fluxui-docs && fluxui-docs --version
fluxui-docs search button
```

## Source

Skill adapted from https://github.com/fgilio/fluxui-docs-skill.
The upstream binary is Git LFS-backed; this project skill uses a small Python wrapper over the same `data/` JSON files.
