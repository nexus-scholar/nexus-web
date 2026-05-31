# Publishing

The documentation site is built with MkDocs and Material for MkDocs. The
repository includes a GitHub Actions workflow that builds the site and deploys
it to GitHub Pages from `master`.

## What Happens On Push

When docs change on `master`, the workflow:

1. checks out the repository;
2. installs the pinned Python documentation dependencies;
3. runs `mkdocs build --strict`;
4. uploads the generated `site` directory as a Pages artifact;
5. deploys it to GitHub Pages.

The workflow file is:

`.github/workflows/docs-pages.yml`

## First-Time Repository Setup

GitHub Pages must be enabled for the repository and configured to use GitHub
Actions as the source. After that, pushes to `master` that touch docs files will
publish automatically.

## Local Preview

From the repository root:

```powershell
python -m pip install -r requirements-docs.txt
mkdocs serve
```

Then open the local URL printed by MkDocs.

## Local Build Check

Before merging documentation changes:

```powershell
mkdocs build --strict
git diff --check
```

The strict build catches broken links, missing screenshots, and invalid
navigation entries before the public site is updated.
