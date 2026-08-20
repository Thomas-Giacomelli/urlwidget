# URL Widget pour GLPI 10/11

Mini-plugin ajoutant des cartes de tableau de bord (dashboard) affichant le
résultat texte/chiffre d'un appel HTTP GET, rendues avec le même style que
les cartes natives GLPI (carte "grand nombre" : titre + valeur). Cas d'usage
principal : afficher le résultat d'une question Metabase publiée en API
publique (titre + résultat), mais fonctionne avec n'importe quel endpoint
renvoyant du texte ou du JSON.

Le widget appelle l'URL **côté serveur GLPI** (pas depuis le navigateur) et
n'affiche que le texte extrait — pas d'iframe, pas de rendu de page externe.

## Installation

1. Copier le dossier `urlwidget` dans le répertoire `plugins/` de votre
   installation GLPI :
   ```
   glpi/
     plugins/
       urlwidget/   <-- ce dossier
   ```
2. Aller dans **Configuration > Plugins**, repérer "URL Widget" et cliquer
   sur **Installer** puis **Activer**.

## Configuration

1. Aller dans **Configuration > Générale**, onglet **Widgets URL**.
2. Renseigner :
   - **Nom** : titre affiché sur la carte (ex. "Tickets ouverts") ;
   - **Data URL** : l'endpoint appelé en HTTP GET ;
   - **JSON path** : chemin en notation pointée vers la valeur dans la
     réponse JSON (laisser vide si la réponse est déjà du texte brut) ;
   - **Préfixe / Suffixe** : optionnels, ex. suffixe `%` ou ` tickets` ;
   - **Cache (secondes)** : durée de réutilisation de la dernière valeur
     récupérée avant un nouvel appel HTTP.
3. Cliquer sur le bouton "+" pour l'ajouter.

### Exemple Metabase

Pour une question Metabase publiée publiquement, utilisez l'**API JSON
publique**, pas la page HTML publique (celle-ci est un rendu JavaScript
côté client et ne contient donc rien d'exploitable pour un appel serveur) :

- **Data URL** : `https://metabase.example.com/api/public/card/<uuid>/query`
- **JSON path** : `data.rows.0.0` (pour une question à une seule valeur :
  une ligne, une colonne)

## Utilisation

1. Aller sur le dashboard voulu (page Centrale, Assets, etc.).
2. Passer en mode édition (icône crayon).
3. Ajouter un widget, choisir la carte portant le nom que vous avez donné
   à l'étape précédente (elle apparaît dans la liste des cartes
   disponibles, rendue comme une carte "grand nombre" native).
4. Enregistrer.

Le widget fonctionne aussi sur un dashboard partagé via un lien "embed" :
chaque carte connaît sa propre configuration indépendamment de la manière
dont GLPI l'affiche (dashboard normal, actualisation ajax, ou lien public).

## Limites connues

- Éditer une entrée existante n'est pas possible depuis l'interface :
  il faut la supprimer et la recréer.
- Aucune vérification n'est faite sur l'URL saisie : ne collez que des
  liens de confiance.
- Le format "grand nombre" natif de GLPI est optimisé pour un nombre ;
  un résultat purement numérique (sans préfixe/suffixe) est affiché avec
  la mise en forme native (séparateurs de milliers, etc.), un résultat
  textuel est affiché tel quel.
