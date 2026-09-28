# feat/resume-dispatched-first

- **Work item**: #328, option (b), the user's decision of 2026-09-28: the activity worker dispatches
  the resume before it appends the outcome, and a resume that arrives before the append waits for it
  rather than concluding. At-least-once, documented.
- **Entries**: an ADR draft, DUR050 (`documentation/adr/` is supervised: the user approves it on the
  PR); `src/Durable/Worker/ActivityMessageProcessor.php`; `src/Durable/Handler/ResumeWorkflowHandler.php`;
  `src/DurableBundle/DependencyInjection/Configuration.php` (the dead `activity_transport.table_name`
  node); the kill-between-the-two-steps test.
- **Careful**: #505 (the DBAL journal's optimistic concurrency) builds on this. The other append-then-send
  pairs (signals, updates, child-to-parent, timers) are #584, not this prise.
- **State**: anna. DUR050 draft is #582: the user's three choices recorded, waiting for the user's approval
  of the text. The independent fix, a failed send is not a failed activity, is #583. The protocol
  itself starts once #582 is approved. Reviewer: sirius.
