# fix/exhausted-early-resume

- **Scope**: #606. On Messenger, an early resume (DUR050) that keeps finding its outcome missing is
  retried as recoverable up to a ceiling, then acknowledged with an info log instead of reaching the
  failure transport: the resume sent after the append carries the run. Operator docs say so.
- **Entries**: a middleware under `src/DurableBundle/Messenger/`, its registration in
  `src/DurableBundle/DependencyInjection/Loader/MessengerServices.php`, tests, the container snapshot,
  `documentation/user/failures/_index.md` and `_index.fr.md`.
- **State**: taken by bob, reviewer sirius.
