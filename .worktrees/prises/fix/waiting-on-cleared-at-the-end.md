# fix/waiting-on-cleared-at-the-end

- **Chantier** : #851 first item, the `waiting_on` column of a finished run keeps its last wait on the DBAL and Illuminate catalogs (part of the epic #823)
- **Entrées** : `src/Bridge/Dbal/Store/DbalWorkflowRunProjection.php`, `src/Bridge/Illuminate/Store/IlluminateWorkflowRunCatalog.php`, tests under `tests/unit/Bridge/`
- **État** : en cours
