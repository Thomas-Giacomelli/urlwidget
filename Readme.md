# URL Widget pour GLPI 11

Mini-plugin qui affiche le résultat d'une question Metabase (publiée en
lien public) comme un widget "Grand nombre" natif de GLPI, dans n'importe
quel tableau de bord (dashboard) — y compris les dashboards partagés en
mode "embed" (iframe public).

Plutôt que d'embarquer un iframe Metabase complet (peu fiable en mode
embed et difficile à redimensionner), le plugin va chercher directement
la valeur brute via l'export JSON public de la question Metabase, et
l'affiche avec le rendu natif GLPI (même style que "nombre
d'ordinateurs", "tickets ouverts", etc.).

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

1. Dans Metabase, ouvrez votre question (par exemple `SELECT COUNT(*) AS
   nb_dect_en_stock FROM ...`), publiez-la en **lien public**.
2. Dans GLPI, aller dans **Configuration > Générale**, onglet
   **Widgets URL**.
3. Renseigner un nom (ex: "DECT en stock") et coller le lien public
   Metabase de la question.
4. Cliquer sur le bouton "+" pour l'ajouter.

Le plugin transforme automatiquement ce lien en export JSON (en ajoutant
`.json` à la fin) pour aller chercher la valeur à chaque affichage du
widget.

## Utilisation

1. Aller sur le dashboard voulu (page Centrale, Assets, etc.).
2. Passer en mode édition (icône crayon).
3. Ajouter un widget, choisir la carte portant le nom que vous avez donné
   à l'étape précédente.
4. Choisir la visualisation **"Grand nombre"** (elle est proposée
   automatiquement, c'est le seul type compatible pour cette carte).
5. Enregistrer.

Ce même widget fonctionne aussi sur les dashboards exportés en "embed"
(lien public intégrable dans une page HTML), puisqu'il s'agit d'un type
de widget natif GLPI.

## Limites connues

- La question Metabase doit renvoyer une seule ligne avec une seule
  colonne (typiquement un `COUNT(*)`). Si elle renvoie plusieurs
  colonnes/lignes, seule la première valeur de la première ligne est
  utilisée.
- Éditer une entrée existante n'est pas possible depuis l'interface :
  il faut la supprimer et la recréer.
- Le lien Metabase doit être un **lien public** (pas besoin
  d'authentification) et son export JSON doit être accessible depuis le
  serveur GLPI (pas seulement depuis le navigateur de l'utilisateur).
- Aucune mise en cache : la question Metabase est réinterrogée à chaque
  affichage du widget.
