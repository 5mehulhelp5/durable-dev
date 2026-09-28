# feat/resume-protocol-beyond-activities

- **Work item**: #584, DUR050's protocol applied to the other append-then-send pairs: signal and update
  delivery, a child's outcome reported to its parent, and timer wake-ups. Send first with the resume
  naming the fact it announces; a resume that arrives before that fact waits; send again after the
  append; keep append-then-send where the resume runs inline.
- **Entries**: `DeliverWorkflowSignalHandler`, `DeliverWorkflowUpdateHandler`,
  `ResumeWorkflowHandler::finalizeAsyncChildOnParentIfLinked()`, `FireWorkflowTimersHandler`,
  `ResumeWorkflowMessage` (the awaited fact generalises beyond an activity id).
- **Careful**: DUR052 (#607, merged) is the decision; #605 (#328, merged) is what it reshapes.
- **State**: anna. In review as PR #622 (sirius).
