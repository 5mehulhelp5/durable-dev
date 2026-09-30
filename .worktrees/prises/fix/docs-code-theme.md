# fix/docs-code-theme

- **Scope**: code blocks of the docs follow the light and dark themes: Hugo emits Chroma classes instead of inline Monokai colours, so the `.chroma` rules of `_custom.scss`, built on the design tokens, apply; complete their token families.
- **Entries**: `hugo-docs/hugo.toml`, `hugo-docs/assets/_custom.scss`.
- **Overlap**: none found (no open PR touches hugo-docs/).
- **State**: in progress.
