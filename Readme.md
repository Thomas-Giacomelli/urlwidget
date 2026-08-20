# URL Widget pour GLPI 11

Mini-plugin ajoutant un type de widget "Iframe / URL" utilisable dans les
tableaux de bord (dashboards) natifs de GLPI. Permet d'embarquer n'importe
quelle URL (ex: une question Metabase publiée en lien public) comme carte
de dashboard.

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
2. Renseigner un nom (ex: "DECT en stock"), l'URL (ex: le lien public
   Metabase de votre question, avec `?titled=false` en suffixe si vous
   voulez masquer le titre Metabase), et une hauteur en pixels.
3. Cliquer sur le bouton "+" pour l'ajouter.

## Utilisation

1. Aller sur le dashboard voulu (page Centrale, Assets, etc.).
2. Passer en mode édition (icône crayon).
3. Ajouter un widget, choisir la carte portant le nom que vous avez donné
   à l'étape précédente (elle apparaît dans la liste des cartes
   disponibles, catégorie widget "Iframe / URL").
4. Enregistrer.

## Limites connues

- Éditer une entrée existante n'est pas possible depuis l'interface :
  il faut la supprimer et la recréer.
- Le contenu de l'iframe distant doit accepter d'être affiché dans une
  frame (pas de header `X-Frame-Options: DENY` côté serveur distant) et
  être accessible en HTTPS sans authentification (lien public Metabase).
- Aucune vérification n'est faite sur l'URL saisie : ne collez que des
  liens de confiance.
