---
title: Guide utilisateur
weight: 1
bookFlatSection: false
---

# Guide utilisateur

Comment penser Durable, et comment s'en servir. Commencez par [Pourquoi Durable](why/) si vous
hésitez encore ; la [page d'accueil](/fr/) plaide la même chose, de façon interactive.

| | |
|---|---|
| [Pourquoi Durable](why/) | le problème qu'il résout, ce qu'il remplace, et quand vous n'en avez pas besoin |
| [Paquets](packages/) | la bibliothèque, le bundle, le pilote Temporal : quoi installer, et quand |
| [Premiers pas](getting-started/) | installation, configuration Symfony, un premier workflow, les commandes du worker |
| [Concepts](concepts/) | workflows, activités, rejeu et backends, en français courant |
| [Backends](backends/) | en mémoire, SQL (DBAL ou Illuminate) ou Temporal, et ce que chacun sait faire |
| [gRPC dans votre image de conteneur](container-images/) | les images `php-grpc`, et comment ajouter `ext-grpc` aux vôtres |
| [Le tableau de bord](dashboard/) | la liste des runs et l'historique d'un run, les mêmes panneaux sur chaque hôte |
| [Durable et le SDK PHP de Temporal](comparison/) | ce qui correspond à quoi, et le jeu Rector qui migre un projet |
| [Écrire un workflow](workflows/) | `WorkflowEnvironment`, signaux, requêtes, mises à jour, workflows enfants |
| [Écrire des activités](activities/) | contrats d'activité, injection de dépendances, le stub typé |
| [Échecs et réessais](failures/) | ce que le journal enregistre, et pourquoi une activité a cessé de réessayer |
| [Annulation](cancellation/) | lever l'annulation dans le workflow pour qu'il puisse compenser |
| [Modifier un workflow en cours](deploying/) | des points de changement déclarés, pour qu'un déploiement ne casse pas une exécution en vol |
| [Opérations Nexus](nexus/) | appeler une opération servie par une autre équipe, et en servir une |
| [Options et objets valeur](options/) | limites de réessai, délais, planifications cron, attributs de recherche |
| [Tester des workflows](testing/) | des tests unitaires sans serveur, et la suite qui tourne contre un vrai |
| [Référence de configuration](configuration/) | chaque clé de `durable.yaml` |
| [Cas d'usage](use-cases/) | une chose entière construite de bout en bout : la démonstration Nexus à quatre applications |
| [Glossaire](glossary/) | la douzaine de mots que ce guide emploie dans un sens précis |

Les décisions d'architecture (**DUR**) et les conventions de travail (**WA**) vivent dans le
dépôt, à destination des contributeurs, sous `documentation/adr/` et `documentation/wa/`.
