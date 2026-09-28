# Hugo documentation site

The static site under **`hugo-docs/`** publishes **user documentation** only: prose for people **using** the Durable component (guides, concepts, getting started). It is built with **[Hugo](https://gohugo.io/)** and the **[hugo-book](https://github.com/alex-shpak/hugo-book)** theme.

## What the site is *not*

- It is **not** a mirror of **ADRs** (`documentation/adr/`, prefix **DUR**) or **working agreements** (`documentation/wa/`). Those remain **contributor-facing** records in the repository.
- It does **not** replace `documentation/INDEX.md` or `LIFECYCLE.md`, which describe how the team manages documents.

## Source of the user site

| Role | Location |
|------|-----------|
| **User guide (Hugo)** | `documentation/user/` — edit Markdown here; the next `hugo` build updates the site. |
| **Architecture / process** | `documentation/adr/`, `documentation/wa/`, `INDEX.md`, `LIFECYCLE.md` — stay in Git; link from the repo or from prose in `documentation/user/` when users need pointers. |

## Mount

`hugo-docs/hugo.toml` mounts **`../documentation/user`** → **`content/docs/`** (single tree). Add new sections as subfolders under `documentation/user/` with `_index.md` files.

The guide is bilingual. Each page has a French sibling, `<page>.fr.md` next to `<page>.md` (21 of each today), which Hugo renders under **`/fr/docs/`**. The English stays at the root. Hugo does not fall back from one language to the other, so a page with no `.fr.md` sibling does not exist under `/fr/docs/`.

## Local build

Prerequisites: **Hugo Extended** (see CI version in `.github/workflows/docs-ovh.yml`).

```bash
cd hugo-docs
hugo server
```

Production build:

```bash
cd hugo-docs
hugo --minify --gc
```

Output: `hugo-docs/public/` (ignored by Git).

## `llms.txt`

The build also writes `/llms.txt`, an index of the user guide for coding agents and for Context7
(#253). `layouts/index.llms.txt` builds it from the guide's own sections, so a new page appears in
it without an edit. The French home has none: it is an index for coding agents, which read the English guide. The repository's
root `context7.json` tells Context7 which folder to read if the repository itself is submitted.

## Deployment configuration

In `hugo-docs/hugo.toml`, set **`baseURL`** to the real site URL and adjust **`params.BookRepo`** / **`BookEditPath`** if the default fork or branch differs.

## References

- [WA001 — English language for project documentation](wa/WA001-english-language-documentation.md)
- [documentation/INDEX.md](INDEX.md)
