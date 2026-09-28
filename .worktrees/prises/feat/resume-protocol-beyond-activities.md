# feat/resume-protocol-beyond-activities

- **Work item**: #584, DUR050's protocol applied to the other append-then-send pairs: signal and update
  delivery, a child's outcome reported to its parent, and timer wake-ups. Send first with the resume
  naming the fact it announces; a resume that arrives before that fact waits; send again after the
  append; keep append-then-send where the resume runs inline.
- **Entries**: `DeliverWorkflowSignalHandler`, `DeliverWorkflowUpdateHandler`,
  `ResumeWorkflowHandler::finalizeAsyncChildOnParentIfLinked()`, `FireWorkflowTimersHandler`,
  `ResumeWorkflowMessage` (the awaited fact generalises beyond an activity id).
- **Careful**: builds on #605 (#328), not merged yet; no code before it lands.
- **State**: taken by anna, 2026-09-28, design reading. Reviewer: sirius.
