# fix/sylius-bench-journal-connection

- **Chantier** : #851, the Sylius bench gives the journal its own Doctrine connection (DUR054), so a failing run is recorded as failed; its Messenger DSNs bound the PostgreSQL wait between an activity and the workflow's resumption (part of the epic #823)
- **Entrées** : `sylius/config/packages/` (doctrine, durable, messenger), a functional test and fixture under `sylius/tests/Functional/`
- **État** : en cours
