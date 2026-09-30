---
title: Cas d'usage
weight: 45
---

# Cas d'usage

Le reste de ce guide est de la référence : une page par fonctionnalité, `await` ici, les signaux
là, Nexus plus loin. Cette section prend le chemin inverse. Chaque entrée est **une chose entière** :
plusieurs applications, plusieurs mécanismes, un problème qui existe en dehors de Durable, avec
son code dans le dépôt et de quoi la lancer.

Les entrées ne sont pas des exercices avancés, et aucune n'est difficile. Ce qui distingue une
entrée d'un exemple de la section [Écrire un workflow](../workflows/), c'est qu'elle est *complète*.

| | |
|---|---|
| [Quatre applications qui s'appellent](nexus-demo/) | trois frameworks, quatre namespaces Temporal, un contrat partagé, et une exécution qui sert une opération pendant qu'elle en appelle une autre |

Une seconde entrée, un agent IA interruptible dont la boucle est pilotée depuis du code de
workflow, attend que son prototype se stabilise. Elle arrivera avec de quoi la lancer, parce
qu'une entrée doit renvoyer vers quelque chose que vous pouvez démarrer.

## Ce qu'une entrée doit contenir

Pour que la section reste lisible à mesure qu'elle grandit, chaque page dit, dans cet ordre :

1. **le problème**, formulé sans le mot « Durable » ;
2. **ce qui est construit** : les fichiers, où ils sont ;
3. **ce que Durable apporte**, et surtout **ce qu'il n'apporte pas** ;
4. **comment la lancer** ;
5. **ce qui n'est pas prouvé.** Une entrée sans cette partie ne liste que des avantages.
