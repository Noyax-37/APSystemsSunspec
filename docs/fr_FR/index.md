# Plugin APSystemsSunspec

Ce plugin permet de lire les données des onduleurs APSystems raccordés à un ECU-S ou ECU-R via le protocole Sunspec.

Il est nécessaire de configurer le plugin pour qu'il puisse se connecter à l'ECU-S ou ECU-R. 


## Configuration

la configuration du plugin se fait dans le menu "Configuration" de Jeedom. Il faut ajouter un équipement de type "APSystemsSunspec" et renseigner les informations suivantes :
- **Nom** : Nom de l'équipement
- **Adresse IP** : Adresse IP de l'ECU-S ou ECU-R

Une fois d'ans l'équipement il faudra éventuellement adapter le timeout. Par défaut le timeout est de 3 secondes. Si vous avez des problèmes de communication avec l'ECU-S ou ECU-R, essayez d'augmenter le timeout.

Cliquez ensuite sur le bouton "Sauvegarder" pour enregistrer la configuration. 

Cliquez sur le bouton "Scan des micro-onduleurs" pour lancer la recherche des micro-onduleurs. Le plugin va scanner l'ECU et ajouter les micro-onduleurs trouvés comme équipements "fils" de l'ECU.

Si vous voulez mettre à jour les données depuis l'ECU, il faut cliquer sur le bouton "Refresh de tout l'ECU". Le plugin va alors lire les données de l'ECU et mettre à jour les équipements fils.

Fixez l'autoactualisation en complétant au format "cron" le champ "Autoactualisation". Par exemple, pour une actualisation toutes les 5 minutes, mettez `*/5 * * * *`. Si vous ne savez pas quoi mettre cliquez sur "?" situé à droite du champ.

Si vous voulez fixer des périodes pendant lesquelles l'autoactualisation ne doit pas se faire, complétez les champs "Heure de début d'arrêt de l'interrogation (HH:MM)" et "Heure de fin d'arrêt de l'interrogation (HH:MM)". Par exemple, pour ne pas interroger l'ECU entre 22h et 6h, mettez `22:00` dans le premier champ et `06:00` dans le second champ. Si vous ne voulez pas d'arrêt de l'interrogation, laissez ces champs vides.