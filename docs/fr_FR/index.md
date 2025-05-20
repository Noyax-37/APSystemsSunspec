# Plugin APSystemsSunspec

Ce plugin permet de lire les données des onduleurs APSystems raccordés à un ECU-S ou ECU-R via le protocole Sunspec.

Il est nécessaire de configurer le plugin pour qu'il puisse se connecter à l'ECU-S ou ECU-R. 


## pré-requis

- Un ECU-S ou ECU-R raccordé à votre réseau local
- Un ou plusieurs micro-onduleurs APSystems raccordés à l'ECU-S ou ECU-R
- protocole Modbus TCP/IP activé sur l'ECU-S ou ECU-R, pour activer le modbus c'est soit par l'application mobile (voir la doc de l'application) soit par l'interface web de l'ECU-S ou ECU-R.:
    - Utiliser ce menu caché : http://IP de votre ECU/index.php/management/modbus
    - Si besoin pour info, tous les menus cachés sont là : http://IP de votre ECU/index.php/hidden
    - login/mdp qui vous sera demandé pour accéder à ces pages: admin/admin
    - Fonctionne aussi très bien sur ECU-R mais uniquement ceux dont le numéro de série commence par 2162xxxxx.

## Configuration

la configuration du plugin se fait dans le menu "Configuration" de Jeedom. Il faut ajouter un équipement de type "APSystemsSunspec" et renseigner les informations suivantes :
- **Nom** : Nom de l'équipement
- **Adresse IP** : Adresse IP de l'ECU-S ou ECU-R

Une fois d'ans l'équipement il faudra éventuellement adapter le timeout. Par défaut le timeout est de 3 secondes. Si vous avez des problèmes de communication avec l'ECU-S ou ECU-R, essayez d'augmenter le timeout.

Cliquez ensuite sur le bouton "Sauvegarder" pour enregistrer la configuration. 

Cliquez sur le bouton "Scan des micro-onduleurs" pour lancer la recherche des micro-onduleurs. Le plugin va scanner l'ECU et ajouter les micro-onduleurs trouvés comme équipements "fils" de l'ECU.

Si vous utilisez le bouton de scan pour modifier la configuration de vos MO, revenir à l'affichage initial, ... Vous avez deux cases à cocher avant de lancer le scan :
- **conserver le nom des commandes** : Si vous avez déjà configuré vos micro-onduleurs et que vous ne voulez pas perdre les noms que vous avez donnés aux commandes, cochez cette case. Sinon, le plugin va renommer les commandes avec les noms par défaut.
- **conserver l'affichage actuel des tuiles** : Si vous avez déjà configuré l'affichage des tuiles et que vous ne voulez pas perdre cette configuration, ne cochez pas cette case. Sinon, le plugin va réinitialiser l'affichage des tuiles avec les valeurs par défaut.

Si vous n'avez pas attribué les ID modbus à vos micro-onduleurs en une suite continue (1, 2, 3, 4, etc.), vous pouvez les ajouter manuellement en cliquant sur le bouton "Ajouter un micro-onduleur". Il faut alors renseigner l'ID modbus que vous voulez ajouter

Si vous voulez mettre à jour les données depuis l'ECU, il faut cliquer sur le bouton "Refresh de tout l'ECU". Le plugin va alors lire les données de l'ECU et mettre à jour les équipements fils.

Fixez l'autoactualisation en complétant au format "cron" le champ "Autoactualisation". Par exemple, pour une actualisation toutes les 5 minutes, mettez `*/5 * * * *`. Si vous ne savez pas quoi mettre cliquez sur "?" situé à droite du champ.

Si vous voulez fixer des périodes pendant lesquelles l'autoactualisation ne doit pas se faire, complétez les champs "Heure de début d'arrêt de l'interrogation (HH:MM)" et "Heure de fin d'arrêt de l'interrogation (HH:MM)". Par exemple, pour ne pas interroger l'ECU entre 22h et 6h, mettez `22:00` dans le premier champ et `06:00` dans le second champ. Si vous ne voulez pas d'arrêt de l'interrogation, laissez ces champs vides. Vous pouvez aussi utiliser les variables Jeedom #sunrise# et #sunset# pour définir les heures de début et de fin d'arrêt de l'interrogation. Par exemple, pour ne pas interroger l'ECU entre le coucher et le lever du soleil, mettez `#sunset#` dans le premier champ et `#sunrise#` dans le second champ. Vous pouvez aussi ajouter un décalage en minutes (ex: #sunrise#-30 ou #sunset#+60) pour que la tâche s'arrête 30 minutes avant le lever du soleil ou 1 heure après le coucher du soleil.

Pour l'utilisation du widget vous pouvez compléter le champs "utilisation dans widget" de chaque commande qui peut soit être saisi manuellement si vous connaissez les fonctions à utiliser ou sélectionné dans la liste déroulante. Pour personnaliser l'affichage du widget cliquez sur la roue dentée à droite de la commande widget puis dans l'onglet "affichage" presque tous les paramètres sont prèsaisis, pour les visualiser il faut descendre en dessous du texte affiché sous "Paramètres optionnel du widget". Pour plus d'infos sur le widget, voir la doc de @phpvarious à l'adresse https://phpvarious.github.io/documentation/widget/fr_FR/widget_scenario/distribution_onduleur/ 

## la tuile:

La tuile est affichée sous forme de tableau avec les commandes essentielles (pour moi), vous pouvez toujours demander à afficher les autres si vous le souhaitez.

plusieurs boutons sont disponibles sur la tuile:
- **Refresh** : Met à jour les données de l'ECU-S ou ECU-R
- **standby MO** : Met en veille tous les micro-onduleurs, le nom du bouton passe en **waheup MO** et permet de réveiller tous les micro-onduleurs. Le registre 40188 de chaque MO passe à 0 pour mettre en veille le MO et à 1 pour le réveiller.
- **Appliqcation réduction puissance** et **arrêt réduction puissance** : Ces deux boutons permettent de réduire la puissance de sortie des micro-onduleurs. Associés au slider **réduction puissance de** qui affiche en % la réduction à appliquer pour limiter la puissance de sortie de l'ECU avec en dessous un calcul très théorique de la puissance attendue en sortie (attention, à ne pas prendre au pied de la lettre). Le registre 40193 de chaque MO passe à 1 pour réduire la puissance et à 0 pour arrêter la réduction de puissance et le registre 40189 prend la valeur en % de la réduction.