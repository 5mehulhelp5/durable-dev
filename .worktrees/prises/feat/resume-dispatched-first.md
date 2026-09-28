# feat/resume-dispatched-first

- **Work item**: #328, option (b), the user's decision of 2026-09-28: the activity worker dispatches
  the resume before it appends the outcome, and a resume that arrives before the append waits for it
  rather than concluding. At-least-once, documented.
- **Entries**: an ADR draft, DUR050 (`documentation/adr/` is supervised: the user approves it on the
  PR); `src/Durable/Worker/ActivityMessageProcessor.php`; `src/Durable/Handler/ResumeWorkflowHandler.php`;
  `src/DurableBundle/DependencyInjection/Configuration.php` (the dead `activity_transport.table_name`
  node); the kill-between-the-two-steps test.
- **Careful**: #505 (the DBAL journal's optimistic concurrency) builds on this; #331 is next, DUR051.
- **State**: taken by anna, 2026-09-28. Reviewer: sirius (jack if sirius is loaded).
