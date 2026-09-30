# docs/activities-injection

- **Scope**: in `documentation/user/`, build activity stubs through `#[Activities]` method parameters instead of the constructor, EN and FR; one section on when to build a stub yourself; a `#[Activities]` workflow in the Laravel and Magento benches. Rector is #778, Nexus and child-workflow injection an OpenSpec change of its own.
- **Entries**: `documentation/user/{activities,cancellation,comparison,options,testing,workflows}/`, `laravel/`, `magento/app/code/Gplanchat/DurableProbe/`.
- **Overlap**: none found (open PRs #777, #773, #741, #723 touch other files).
- **State**: in progress.
