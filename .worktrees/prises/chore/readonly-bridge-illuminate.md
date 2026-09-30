# chore/readonly-bridge-illuminate

- **Scope**: mark `readonly` the classes of `src/Bridge/Illuminate/` that hold no state after construction, from the final/readonly audit of 2026-09-30. Static-only classes are left out, pending a decision.
- **Entries**: `src/Bridge/Illuminate/` class declarations only.
- **Overlap**: the nine sibling chore/readonly-* branches touch the other packages; none touches the same files.
- **State**: in progress.
