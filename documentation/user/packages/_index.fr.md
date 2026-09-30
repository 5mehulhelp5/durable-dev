---
title: Paquets
weight: 5
---

# Paquets

Durable se compose d'une bibliothèque centrale, d'une intégration de framework facultative et d'un
backend à choisir. Le backend est l'endroit où vit le journal d'une exécution et ce qui planifie son
travail ; le journal est la suite d'événements, en ajout seul, qui enregistre chaque étape d'une
exécution (voir le [glossaire](../glossary/)). Installez ce dont vous avez besoin : la bibliothèque
seule suffit pour écrire un workflow et le tester unitairement, et les paquets qui s'ajoutent
au-dessus changent seulement l'endroit où l'exécution est enregistrée, jamais le code du workflow.

| Paquet | Apporte | Exige |
|---|---|---|
| `gplanchat/durable` | workflows, activités, minuteurs, journal d'événements, backend en mémoire | `psr/cache` |
| `gplanchat/durable-bundle` | câblage Symfony, transports Messenger, panneau du profileur | la bibliothèque et Symfony Messenger |
| `gplanchat/durable-bridge-temporal` | le pilote Temporal, en gRPC | la bibliothèque, `ext-grpc`, un cluster Temporal |
| `gplanchat/durable-bridge-dbal` | l'exécution durable sur une base SQL | la bibliothèque, Doctrine DBAL 3 ou 4, `symfony/lock` |
| `gplanchat/durable-bridge-illuminate` | la même chose, par la couche de base de données de Laravel | la bibliothèque, `illuminate/database` 11, 12 ou 13 |
| `gplanchat/durable-laravel` | le câblage Laravel : les ports liés depuis la configuration, le travail sur la file de l'application | la bibliothèque, le pont Illuminate, `illuminate/support` |
| `gplanchat/durable-magento` | un module Magento 2.4 / Mage-OS : déclaration, workers, écran d'administration | la bibliothèque ; Temporal pour tout ce qui doit survivre à un processus |
| `gplanchat/durable-plugin` | un tableau de bord Sylius pour les exécutions | le bundle, `knplabs/knp-menu` ; Sylius 2.x pour apparaître dans son menu |
| `gplanchat/durable-filament` | un tableau de bord des exécutions dans un panneau Filament | l'intégration Laravel, Filament 3 ou 4 |
| `gplanchat/durable-phpstan` | l'analyse statique des appels de stub face à leur contrat | la bibliothèque, `phpstan/phpstan` |
| `gplanchat/durable-rector` | la migration automatisée depuis le SDK PHP de Temporal | la bibliothèque, `rector/rector` |

Un workflow est la classe PHP qui décrit les étapes d'une exécution, et une activité est une unité
d'effet de bord qu'un workflow appelle, comme un appel HTTP ou une écriture en base.

Les trois ponts sont des **alternatives** : vous installez Temporal, DBAL ou Illuminate, jamais deux
d'entre eux.

Les deux derniers paquets sont des **outils de développement** et se placent en `require-dev` :

- **`gplanchat/durable-phpstan`** résout les appels d'`activityStub()` et de `childWorkflowStub()`
  face à l'interface de contrat. Une activité mal nommée ou un mauvais argument apparaît alors comme
  une erreur d'analyse, au lieu d'un échec de sérialisation à l'exécution. Il vérifie aussi qu'un
  [paramètre `#[Activities]`](../workflows/#arguments-durable-supplies) et son docblock
  `@param ActivityStub<Contrat>` nomment le même contrat.
- **`gplanchat/durable-rector`** migre un projet depuis le SDK PHP officiel de Temporal. Il réécrit
  les attributs et change le modèle d'exécution, en conservant les noms de type de workflow et
  d'activité qu'un serveur en cours d'exécution a déjà enregistrés. Il pose un commentaire sur
  chaque construction qu'il ne peut pas convertir, pour que vous les voyiez avant de commencer. Voir
  [la page de comparaison](../comparison/#choisir).

---

## `gplanchat/durable`, la bibliothèque {#gplanchatdurable--la-bibliothèque}

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require gplanchat/durable
```

La bibliothèque contient le moteur et tout le domaine : `WorkflowEnvironment`, activités,
minuteurs, effets de bord, signaux, requêtes, mises à jour, workflows enfants, journal
d'événements, et les objets valeur qui décrivent les options de planification.

Elle a une seule dépendance d'exécution, `psr/cache`, et le pool de cache lui-même est facultatif :
il mémorise la résolution des contrats d'activité, et `ActivityContractResolver` fonctionne sans.
La bibliothèque n'exige **aucun framework**. Vous pouvez la piloter depuis un simple script PHP, une
application Laminas, un outil en ligne de commande ou un test.

Elle inclut un **backend en mémoire** qui fait tout tourner dans un seul processus. Vos tests
unitaires l'utilisent, et il ne demande rien d'autre à installer.

> [!NOTE]
> Le backend en mémoire ne garde aucun état d'un processus à l'autre. Utilisez-le pour les tests et
> l'exploration locale ; un workflow qui doit survivre à un déploiement a besoin d'un autre backend.
> Voir [Backends](../backends/).

---

## `gplanchat/durable-bundle`, l'intégration Symfony {#gplanchatdurable-bundle--lintégration-symfony}

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require gplanchat/durable-bundle
```

Le bundle prend en charge ce que vous écririez sinon à la main :

- **L'autoconfiguration.** Le bundle enregistre chaque classe qui porte `#[AsWorkflow]` ou
  `#[AsActivityHandler]`. Vous ne listez ces classes dans aucun fichier de conteneur, et vous ne les
  balisez pas. `#[AsActivity]` nomme le contrat et n'enregistre rien.
- **Le câblage Messenger.** Les reprises de workflow et les envois d'activité partent vers les
  transports que vous nommez dans `durable.yaml` : un workflow qui se suspend reprend donc par vos
  files existantes.
- **Une commande de console.** `durable:execution:diagnose <executionId>` affiche ce que le moteur
  détient d'une exécution : ses métadonnées de workflow, ses liens parent/enfant et son journal
  d'événements. Le bundle n'ajoute aucune commande de worker ; le worker est le `messenger:consume`
  de Messenger sur les transports ci-dessus.
- **Le panneau du profileur.** Dans la barre d'outils Symfony, le panneau montre chaque exécution,
  son journal et la chronologie de ses activités, avec la tentative qui a échoué et la raison.

Toute la configuration tient dans un fichier, documenté clé par clé dans la
[référence de configuration](../configuration/).

---

## `gplanchat/durable-bridge-temporal`, le pilote Temporal {#gplanchatdurable-bridge-temporal--le-pilote-temporal}

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require gplanchat/durable-bridge-temporal
```

Le pont parle à un cluster Temporal **directement en gRPC**. L'arbre de dépendances ne contient ni
le SDK PHP officiel de Temporal ni RoadRunner : les définitions protobuf sont embarquées et les
workers sont de simples processus PHP.

Par rapport au backend en mémoire, il apporte :

- des exécutions qui survivent aux redémarrages de processus, aux déploiements et aux plantages ;
- des politiques de réessai côté serveur : une activité en échec est réessayée même si le worker a
  disparu ;
- les planifications cron, les attributs de recherche, et la visibilité entre processus dans
  l'interface Temporal ;
- un stockage d'événements en lecture traversante, pour que le profileur montre l'historique d'une
  vraie exécution.

Il exige `ext-grpc` et un cluster joignable. En local, une commande démarre un serveur de
développement :

```bash
temporal server start-dev --namespace durable-test --port 7233
```

> [!NOTE]
> Les planifications cron et les attributs de recherche sont des capacités de Temporal sans
> équivalent en processus. Le backend en mémoire les rejette avec une erreur explicite au lieu de
> les ignorer en silence.

---

## `gplanchat/durable-bridge-dbal`, le backend SQL {#gplanchatdurable-bridge-dbal--le-backend-sql}

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require gplanchat/durable-bridge-dbal
```

Le pont fournit l'exécution durable sur **une seule base SQL**, sans cluster d'orchestration et sans
`ext-grpc`. [**DUR030**](https://github.com/gplanchat/durable-dev/blob/main/documentation/adr/DUR030-dbal-backend-simplified-durable-execution.md)
consigne la décision qui le fonde.

Le pont laisse intacts l'interpréteur de rejeu, les ports de workflow et le tampon de commandes. Le
rejeu est la façon dont une exécution reprend : le code du workflow tourne à nouveau depuis sa
première ligne, et chaque étape enregistrée renvoie son résultat depuis le journal. Le pont rend
seulement persistants trois stockages locaux au processus : le journal d'événements, les
métadonnées de workflow, et les liens parents des workflows enfants. Le code de workflows et
d'activités est octet pour octet celui qui tourne sur Temporal ou en mémoire.

| Conservé | Abandonné par rapport à Temporal |
|---|---|
| Classes de workflow, activités, `WorkflowEnvironment` | Les files de tâches distribuées ; les reprises passent par Symfony Messenger |
| Signaux, requêtes, mises à jour | La planification côté serveur ; les minuteurs passent par le `DelayStamp` de Messenger |
| La sémantique d'annulation et de compensation | La sérialisation des tâches côté serveur, remplacée par un verrou applicatif |
| Le déterminisme du rejeu et le journal d'événements | La rétention d'historique, l'API de visibilité, l'interface Temporal |

Choisissez-le quand vous avez besoin de durabilité sans opérer de cluster. Il demande une base que
vous sauvegardez déjà, une migration, et aucune extension à compiler.

---

## `gplanchat/durable-bridge-illuminate`, le backend Laravel {#gplanchatdurable-bridge-illuminate--le-backend-laravel}

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require gplanchat/durable gplanchat/durable-bridge-illuminate
php artisan migrate
```

Ce pont fournit les mêmes quatre stockages que le pont DBAL, avec les mêmes compromis face à
Temporal : le tableau ci-dessus s'applique mot pour mot. La connexion change. Ces stockages
utilisent `Illuminate\Database\Connection` et son constructeur de requêtes, pas Eloquent.

Donnez aux stockages leur propre connexion dans `config/database.php`, distincte de la connexion par
défaut de l'application (DUR054). Sur une connexion partagée, les transactions propres à Durable
s'imbriquent dans celles de l'application : un rollback métier efface des événements du journal, et
une prise de main reste invisible aux autres workers tant que le code métier n'a pas validé. Pour
traiter une activité qui écrit puis meurt, rendez l'activité idempotente. Ne partagez jamais une
transaction avec le code métier à cette fin.

Les quatre tables sont livrées en migration, chargée directement depuis le paquet : `migrate`
suffit. Pour les modifier, publiez-les avec `vendor:publish --tag=durable-migrations` ; à partir de
là, vous maintenez la copie publiée. **Gardez le nom du fichier publié.** Laravel indexe les
migrations par leur nom de base et donne la priorité à `database/migrations` quand deux noms
coïncident, ce qui fait de votre copie celle qui s'exécute. Si vous la renommez, les deux migrations
s'exécutent, et la seconde échoue sur une table qui existe déjà.

`Queue\ResumeLock` couvre ce qu'aucun choix de stockage ne fournit. Quand deux workers reprennent la
**même** exécution, tous deux la rejouent, tous deux traitent les commandes qu'elle produit comme
nouvelles, et ces commandes partent en double. Le journal ne l'empêche pas, car il enregistre tout
ce qu'il reçoit, doublons compris. `ResumeLock` prend une fermeture : un job en file, une commande
artisan ou un worker écrit à la main peuvent tous s'en servir.

> [!NOTE]
> **Ce pont fournit seulement le stockage.** Il ne lie aucun port et ne livre ni commande de worker
> ni job ; `DurableIlluminateServiceProvider` enregistre seulement l'emplacement des migrations.
> [`gplanchat/durable-laravel`](#gplanchatdurable-laravel--lintégration-laravel), décrit dans la
> section suivante, lie les stockages. Si vous installez le pont seul, vous câblez les stockages
> vous-même, comme le fait une application sans framework.

---

## `gplanchat/durable-laravel`, l'intégration Laravel {#gplanchatdurable-laravel--lintégration-laravel}

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require gplanchat/durable-laravel
php artisan migrate
php artisan vendor:publish --tag=durable-config
```

L'auto-discovery enregistre le provider. Il lie les quatre ports de stockage, les jobs d'activité et
de reprise, et le verrou par exécution, depuis un seul `config/durable.php` publié.

**Un choix de backend lie tous les ports.** Un journal sur un backend sous un catalogue sur un autre
n'est pas une configuration, c'est une panne : `backend` est une seule valeur, et un backend que ce
paquet ne sert pas est refusé **par son nom** à l'enregistrement, en nommant les deux qu'il sert :
`illuminate` et `memory`.

**Les workflows sont déclarés, pas scannés.** Laravel n'a pas d'équivalent de l'autoconfiguration
par attribut de Symfony, donc la clé `workflows` nomme les classes. Mesuré : les nommer coûte
0,14 ms et ne grandit pas avec l'application, là où un scan par réflexion coûte 15 ms à mille classes
**et les charge toutes dans chaque processus** pour en trouver cinq. Pas de `durable:cache` pour la
même raison : `config:cache` met déjà en cache le fichier qu'il dupliquerait.

**Le travail voyage sur la file que l'application draine déjà**, avec `php artisan queue:work` pour
seul worker. Activités et reprises sont des jobs ; un minuteur est un job de déclenchement différé sur le délai
natif de la file.

### Ce n'est pas un moteur durable pour Laravel, et ce carré est pris

[`durable-workflow/workflow`](https://github.com/durable-workflow/workflow), anciennement
`laravel-workflow/laravel-workflow`, c'est de l'exécution durable **sur les files de Laravel**, avec
son propre stockage, explicitement inspiré de Temporal et d'Azure Durable Functions, plus de mille
étoiles. Depuis la 2.0, les workflows s'y écrivent en méthodes linéaires portées par des Fibers, et
il tourne au choix intégré à votre application, sur son propre serveur autonome ou sur son Cloud
géré, avec des SDK PHP, Python et Rust. Il livre une interface de suivi, Waterline. Il est bon à ce
qu'il fait, et si un moteur pensé d'abord pour Laravel est ce que vous cherchez, prenez-le.

Ce que ce paquet vend, c'est un **autre choix de backend** : le même code de workflow contre un
cluster Temporal — Temporal Cloud et Nexus compris, avec un historique que l'interface de Temporal
sait lire — *ou* contre une base SQL, sans cluster à opérer. Et un parc mixte Symfony / Sylius /
Laravel partage un seul moteur : une classe de workflow écrite pour `gplanchat/durable-bundle`
tourne ici sans modification. C'est toute la promesse, et c'est celle que l'autre paquet ne fait
pas.

Deux noms voisins sur Packagist méritent la phrase plutôt que l'espoir que personne ne remarque.

### Démarrer une exécution

`WorkflowResumeDispatcher::dispatchNewWorkflowRun()` démarre une exécution sur chaque backend :

- sur `illuminate`, il met en file la première reprise pour `queue:work` ;
- sur `temporal`, il démarre le workflow sur le cluster, qui livre tout ce qui suit ;
- sur `memory`, il mène l'exécution **dans le processus appelant** : l'appel rend la main une fois
  l'exécution terminée, ou quand elle attend un signal ou une échéance au-delà du budget de dix
  secondes. Le journal de ce backend vit dans le processus : rien d'autre ne pourrait la faire
  avancer.

### Nexus, sur le backend qui sait le router

Servir une opération Nexus, c'est répondre à un appel venu d'un autre espace de noms, et seul le
cluster route ces appels-là. La clé `nexus.handlers` nomme les gestionnaires et les contrats qu'ils
servent :

```php
'nexus' => ['handlers' => [App\Nexus\BillingHandler::class => App\Contracts\BillingService::class]],
```

Ce qu'un gestionnaire ne sert pas, un workflow le remplit : il porte `#[FulfilsNexusOperation]`, et
il suffit qu'il soit dans la liste `workflows` ci-dessus. Un contrat se sépare en deux interfaces
parce que PHP ne sait pas dire « implémente partiellement », et le registre est ce qui recolle les
deux moitiés.

**En déclarer un sous un backend qui ne route pas est refusé à l'enregistrement**, pas au premier
appel, et le backend est nommé. Appeler une opération Nexus ne déclare rien ici : c'est l'affaire
du workflow, et c'est le cas le plus courant.

`php artisan durable:nexus-worker` draine les opérations que le cluster route vers cette
application.

### Trois réglages refusés plutôt que tolérés

| réglage | refusé | pourquoi |
|---|---|---|
| `lock.store: null` | toujours | il accorde tous les verrous, dans tous les déploiements |
| `lock.store: array` | sous `illuminate` | une reprise tourne dans un worker séparé de celui qui l'a dispatchée, donc deux verrous `array` ne se voient jamais : quinze sections critiques chevauchées sur vingt, mesurées |
| la connexion de file `sync` | sous `illuminate` | elle exécute les jobs sur place : une reprise qui en dispatche une autre récurse jusqu'à la pile |

`array` reste accepté sous `memory` : c'est le cache de test par défaut de Laravel, et exclure dans
un seul processus est exactement ce qu'un test veut.

### Deux choses qui ressemblent à des bugs et n'en sont pas

**Le driver `sqlite` ne peut pas héberger plus d'un worker.** Quatre workers qui dépilent la table
`jobs` donnent `SQLSTATE[HY000]: General error: 5 database is locked`, et trois sur quatre meurent à
leur premier job, WAL activé et `busy_timeout` à 60 s. Passez à MySQL, PostgreSQL ou Redis dès qu'il
y a un second worker.

**Le job d'un worker tué reste réservé jusqu'à `retry_after`**, 90 secondes par défaut. Un worker
lancé avec `--stop-when-empty` dans cette fenêtre voit une file vide et sort **sans rien faire**, ce
qui ressemble trait pour trait à une reprise qui a échoué. C'en est une qui n'a pas encore reçu le
job : un worker supervisé, qui survit à la fenêtre, le reprend et l'exécution se termine.

### Pas dans ce paquet

**Rien concernant Temporal**, qui est servi. `backend: 'temporal'` met le journal et le catalogue
d'exécutions dans le cluster, et deux workers drainent ce que la file de l'application ne peut pas porter :
`php artisan durable:temporal-worker` les tâches de workflow, et
`php artisan durable:temporal-worker --role=activity` les tâches d'activité.

`gplanchat/durable-bridge-temporal` est **suggéré et non exigé** : il installe huit paquets, dont cinq
composants Symfony qu'une application Laravel ne charge jamais, pour quelque 36 Mo. Une application
qui ne choisit pas ce backend ne le paie jamais, et celle qui le choisit s'entend nommer le paquet à
installer. Scinder le pont, dont la partie couplée à Symfony fait huit fichiers sur 774, retirerait le
poids, et c'est un change à part.

**Un tableau de bord.** [`gplanchat/durable-filament`](#gplanchatdurable-filament--le-tableau-de-bord-filament)
exige ce paquet, et ce paquet n'exige, ne suggère ni ne détecte jamais Filament.

---

## `gplanchat/durable-plugin`, le tableau de bord Sylius {#gplanchatdurable-plugin--le-tableau-de-bord-sylius}

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require gplanchat/durable-plugin
```

L'habillage Sylius du [tableau de bord](../dashboard/) : une entrée dans le menu d'administration, la
liste des exécutions sur des cartes Tabler avec pagination par curseur, et le détail à côté. Les
panneaux, le regroupement et les mots viennent de `gplanchat/durable` lui-même : la même exécution se
lit donc pareil ici et sur l'écran Magento ; ce que ce paquet possède, c'est l'habillage autour d'eux.

Les libellés privilégient l'`ActivityType.name` lisible et ne retombent sur les identifiants
techniques que faute de mieux.

Il **observe** ; il n'exécute pas. Il exige `gplanchat/durable-bundle`, qui câble le catalogue
d'exécutions qu'il lit : la commande ci-dessus est donc toute l'installation.

> [!NOTE]
> Les données vivantes viennent du backend installé, quel qu'il soit. Aucun des trois ponts n'est un
> `require` ici : le backend est suggéré par `gplanchat/durable`, une fois, pour toutes les
> intégrations. Sans backend, le plugin s'installe quand même, la route et l'entrée de menu
> fonctionnent, et le tableau de bord affiche son état dégradé au lieu d'exécutions vivantes.

## `gplanchat/durable-filament`, le tableau de bord Filament {#gplanchatdurable-filament--le-tableau-de-bord-filament}

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require gplanchat/durable-filament
```

```php
// app/Providers/Filament/AdminPanelProvider.php
use Gplanchat\Durable\Filament\DurableFilamentPlugin;

return $panel
    // ...
    ->plugin(DurableFilamentPlugin::make());
```

L'habillage Filament du [tableau de bord](../dashboard/), sur Filament 3 ou 4 : une entrée
**Exécutions Durable** dans la navigation du panneau, la liste des exécutions avec pagination par
curseur et les filtres par nom et par identifiant d'exécution que le backend sait appliquer, et une
page par exécution avec son état, ce qu'elle attend, ses opérations Nexus et son historique. En
anglais et en français.

Il **observe** ; il n'exécute pas. Il exige `gplanchat/durable-laravel` et lit le catalogue
d'exécutions que ce paquet lie pour son backend : en mémoire, Illuminate ou Temporal. Rien en lui ne
nomme un backend, et rien dans `gplanchat/durable-laravel` ne nomme Filament.

> [!NOTE]
> La page d'une exécution liste ses opérations Nexus quand le catalogue les rapporte, ce que seul
> celui de Temporal sait faire : un journal ne peut pas tenir d'opération Nexus, donc en mémoire et
> sur Illuminate la section n'apparaît jamais.

## `gplanchat/durable-magento`, l'intégration Magento {#gplanchatdurable-magento--lintégration-magento}

```bash
composer config minimum-stability beta
composer config prefer-stable true
composer require gplanchat/durable-magento
```

Un module Magento 2.4 / Mage-OS, `Gplanchat_DurableModule` dans `bin/magento module:status`. Il déclare
les classes de workflow et d'activité au moteur, l'assemble pour un processus Magento, livre les
workers en commandes `bin/magento`, et ajoute un écran d'administration en lecture seule sous
**System > Durable processes > Process history**.

L'habillage est celui de Magento : une grille standard (pagination, signets, choix des colonnes,
export, et un filtre d'état multi-select dont les options viennent de l'énumération elle-même) avec
l'état du backend et les compteurs par issue au-dessus. Ce que l'écran *montre* n'appartient ni à
Magento ni à ce paquet : voir [le tableau de bord](../dashboard/), que chaque hôte rend dans son
propre habillage.

Le conteneur de Magento n'a pas d'équivalent de l'autoconfiguration par tag de Symfony : la
déclaration est explicite, deux tableaux dans `di.xml` :

```xml
<type name="Gplanchat\DurableModule\Runtime\RuntimeFactory">
    <arguments>
        <argument name="workflowClasses" xsi:type="array">
            <item name="place_order" xsi:type="string">Acme\Shop\Workflow\PlaceOrder</item>
        </argument>
        <argument name="activityHandlers" xsi:type="array">
            <item name="order" xsi:type="object">Acme\Shop\Activity\OrderActivities</item>
        </argument>
    </arguments>
</type>
```

Ce qui ne se déclare **pas**, c'est le contrat : la fabrique lit les interfaces de chaque
gestionnaire et garde celles qui portent `#[AsActivityMethod]`. Une déclaration de moins à écrire de
travers, et les noms d'activité restent ceux des attributs.

Deux autres arguments de la même fabrique bornent une exécution, et `di.xml` est le seul endroit où
les régler :

```xml
<argument name="maxActivityRetries" xsi:type="number">3</argument>
<argument name="budgetSeconds" xsi:type="number">30</argument>
```

- `maxActivityRetries` est le plafond de tentatives des activités que `MagentoRuntime::run()` exécute
  dans le processus appelant, le [`max_activity_retries`](../configuration/#max_activity_retries) du
  bundle Symfony. `0`, la valeur par défaut, ne plafonne rien. Les workers Temporal ne le lisent
  jamais : là, c'est la grappe qui relance, d'après la `RetryLimit` propre à l'activité.
- `budgetSeconds` borne `MagentoRuntime::run()`, qui mène un workflow à son terme dans le processus
  appelant : au-delà, l'appel lève `WorkflowStuckException` au lieu d'attendre encore. Par défaut
  `10`. Il existe à cause du premier : sans plafond, une activité qui échoue sans cesse occuperait
  ce processus pour toujours. Les workers et `workflowClient()` ne lisent ni l'un ni l'autre.

**Deux backends, et c'est Composer qui l'impose.** Magento atteint la mémoire et Temporal, et le
module déclare un `conflict` sur les deux ponts SQL : `Magento\Framework\App\ResourceConnection`
n'est ni une connexion Doctrine DBAL ni celle d'Illuminate. Lequel des deux vous obtenez se décide
par un DSN dans `app/etc/env.php`, pas par un réglage :

```php
'durable' => [
    'temporal' => ['dsn' => 'temporal://temporal:7233?namespace=default&tls=0'],
],
```

Sans lui, le journal vit dans le processus qui l'écrit et meurt avec lui, ce qui est acceptable pour une
commande en ligne, ruineux pour le reste.

**Les workers sont des commandes, pas des consommateurs de file**, et un exploitant les supervise
comme n'importe quel processus long :

```bash
bin/magento durable:worker --role=journal   --time-limit=3600
bin/magento durable:worker --role=activity  --time-limit=3600
```

Un processus, une file, un rôle : ce sont deux files Temporal distinctes, dont le parallélisme se
règle séparément. Rien ne circule sur le `MessageQueue` de Magento : sur Temporal une activité est
une commande Temporal et une reprise une tâche de workflow, donc un topic ici serait une seconde
file à superviser, pour rien.

**Deux processus, et en oublier un ne coûte pas la même chose des deux côtés.** Sans
`--role=journal`, rien n'avance : les exécutions démarrent, leur historique se remplit, et personne
ne répond à leurs tâches de workflow. Sans `--role=activity`, c'est pire, parce que ça a l'air de
marcher : une exécution avance **jusqu'à sa première activité** et s'y arrête, la commande débitée
et le stock non, et c'est le client qui vous l'apprend. C'est la panne que cette intégration existe
pour supprimer, remise en place à la main.

Les bornes `--time-limit` et `--max-tasks` sont pour le superviseur : elles font finir le processus
pour que ce qui le relance puisse le relancer. Et les reprises sont l'affaire de la grappe ; les
tentatives d'une activité sont ordonnancées que quelqu'un écoute ou non, donc une exécution dont
l'activité « a échoué après 3 tentatives » en quelques secondes signale un worker absent, pas un
code qui se trompe trois fois de suite.

⚠ **Les réglages de file de Magento n'entrent pas là-dedans.** `retry_inprogress_after`, les tâches
cron `messagequeue_*`, `queue_lock` : aucun ne porte quoi que ce soit de Durable, puisque rien de
Durable ne circule sur `MessageQueue`. Réglez-les pour vos propres consommateurs.

> [!NOTE]
> Démarrez les exécutions **sur la grappe**, pas dans la requête qui les déclenche. Un observateur
> sur `sales_order_place_after` qui appelle `RuntimeFactory::workflowClient()->startAsync()` confie
> l'exécution à Temporal et rend la main (`workflowClient()` exige le cluster : `startAsync()` n'existe
> que sur Temporal) ; la démarrer en ligne la tuerait avec la requête, ce qui
> est précisément la panne que cette intégration existe pour retirer.

---

## Qu'est-ce que j'installe ?

Chaque commande ci-dessous est celle que le sélecteur de la [page d'accueil](/fr/) vous donne,
écrite en toutes lettres.

Le sélecteur lit son état dans l'URL : un lien peut donc ouvrir la page avec une situation déjà
choisie, ce qui est pratique dans un ticket, un README ou une réponse de support :

```
https://durable.rocks/fr/?fw=magento&be=temporal#install
```

`fw` est le framework (`none`, `symfony`, `laravel`, `sylius`, `apiplatform`, `magento`), `be`
l'endroit où vit l'état (`memory`, `temporal`, `dbal`, `illuminate`), et `dist` la base sous une
distribution (`none`, `symfony`, `laravel`). Chaque axe est facultatif. Une valeur que le sélecteur
refuse (un framework qui n'est pas publié, un backend que l'appariement interdit) est **ignorée
plutôt que forcée** : un vieux lien retombe sur le choix par défaut au lieu d'afficher une
combinaison qui n'existe pas. Et choisir dans la page réécrit la barre d'adresse, donc le lien à
partager est celui qu'on a déjà sous les yeux.

Chaque commande du tableau suppose que le projet accepte d'abord la ligne bêta :

```bash
composer config minimum-stability beta
composer config prefer-stable true
```

| Votre situation | Commande |
|---|---|
| Découverte, ou tests unitaires seulement | `composer require gplanchat/durable` |
| Sans framework, une base SQL | `composer require gplanchat/durable gplanchat/durable-bridge-dbal` |
| Sans framework, un cluster Temporal | `composer require gplanchat/durable gplanchat/durable-bridge-temporal` |
| Symfony, tests seulement | `composer require gplanchat/durable-bundle` |
| Symfony, une base SQL | `composer require gplanchat/durable-bundle gplanchat/durable-bridge-dbal` |
| Symfony, un cluster Temporal | `composer require gplanchat/durable-bundle gplanchat/durable-bridge-temporal` |
| Sylius, tests seulement | `composer require gplanchat/durable-plugin` |
| Sylius, une base SQL | `composer require gplanchat/durable-plugin gplanchat/durable-bridge-dbal` |
| Sylius, un cluster Temporal | `composer require gplanchat/durable-plugin gplanchat/durable-bridge-temporal` |
| Laravel, une base SQL | `composer require gplanchat/durable gplanchat/durable-bridge-illuminate` |
| Magento, grappe Temporal | `composer require gplanchat/durable-magento gplanchat/durable-bridge-temporal` |

Chaque ligne ne nomme que l'intégration : le bundle tire la bibliothèque, et le plugin tire le
bundle. Sans framework, vous nommez la bibliothèque vous-même, et vous câblez aussi les workers
vous-même.

La ligne Laravel nomme la bibliothèque plutôt qu'une intégration, et c'est désormais un *choix* et
non un manque. `gplanchat/durable-laravel` existe : un service provider qui lie les quatre ports de
stockage, des workflows déclarés dans `config/durable.php`, le travail sur la file que l'application
draine déjà. Tant qu'il n'est pas tagué, le pont s'installe seul et vous le câblez vous-même ; la
section ci-dessus dit ce que l'intégration vous retire des mains.

---

## Un seul code, un seul comportement

Tous les backends font tourner le **même pilote à fibres** et le **même chemin d'exécution des
activités**. Un workflow que vous avez testé en mémoire se comporte de la même façon contre DBAL ou
contre Temporal : décompte des réessais, classification des échecs, annulation et compensation
compris.

Là où une capacité n'a réellement pas d'équivalent, le backend **échoue avec un message explicite**
plutôt que de faire semblant. Les différences sont listées dans
[Backends](../backends/#capability-matrix).

---

## Monorepo et publications

Le développement se fait dans un seul dépôt, `gplanchat/durable-dev`. Chaque paquet est publié dans
son propre dépôt en lecture seule par une scission, si bien qu'un `composer require` tire un petit
paquet plutôt que tout l'arbre.
