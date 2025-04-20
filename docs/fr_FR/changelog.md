# Changelog plugin APSystemsSunspec

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
