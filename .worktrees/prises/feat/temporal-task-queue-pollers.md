# feat/temporal-task-queue-pollers

- **Chantier** : absent worker detection (partner feedback on durable-magento, point 4), bridge slice
- **Entrées** : `DescribeTaskQueue` RPC and JSON route, a per-queue poller probe in `src/Bridge/Temporal/` (workflow, activity, nexus when set), UPGRADE entry; host wiring left for later
- **État** : en cours
