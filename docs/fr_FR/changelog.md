# Changelog plugin APSystemsSunspec

# V1.6

- ajout des commande permettant la mise en standby de tous les MO et de la limitation de la puissance de sortie des MO
- amélioration de la période de stop de la tâche de mise à jour des données, maintenant vous pouvez saisir une heure au format HH:MM ou utiliser les variables #sunset# et #sunrise# de Jeedom ou sélectionner une commande qui dispose des heures de coucher et de lever du soleil. De plus pour #sun*# ou pour une commande de type heure, vous pouvez ajouter un décalage en minutes (ex: #sunrise#-30 ou #sunset#+60) pour que la tâche s'arrête 30 minutes avant le lever du soleil ou 1 heure après le coucher du soleil.
- amélioration de la saisie des commandes permettant d'alimenter le widget. Maintenant vous pouvez toujours saisir directement la fonction à utiliser dans le widget si vous la connaissez ou sélectionner dans la liste déroulante la fonction que vous souhaitez.
- affichage de la tuile en tableau avec uniquement les commandes essentielles (pour moi), vous pouvez toujours demander à afficher les autres si vous le souhaitez.
- pas mal de corrections à gauche ou à droite... 

# V1.5

- Possiblité d'inclure un MO lorsque la numérotation des ID modbus n'est pas suivie (demande de @vmath54)
- Amélioration de la gestion du template (widget) (encore merci à @Phpvarious pour son super boulot)
- Calcul de la puissance d'un panneau raccordé à un MO lorsque cette puissance = 0 alors que les U et I ne le sont pas (bug? constaté chez @rennais35000 pour le PV4 d'un YC1000-3)
- Gestion des erreurs associées au registre 40110 'événement 1' en bitfield32
- Possibilité de saisir dans la configuration d'un MO les puissances de chaque panneau raccordé => le total alimente aussi le pvMaxPower du MO + celui du MO de l'ECU (permet de gérer certaines animations du widget)
 

# V1.4

- Si mise à jour des commandes présence d'une case à cocher pour conserver le nom que vous aviez donné


# V1.3

- Version fonctionnelle
