<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
function APSystemsSunspec_install() {
    APSystemsSunspec_update();
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function APSystemsSunspec_update() {
    $core_version = 'x.y';
    if (!file_exists(dirname(__FILE__) . '/info.json')) {
        log::add('APSystemsSunspec','warning','Pas de fichier info.json');
        goto step2;
    }
    $data = json_decode(file_get_contents(dirname(__FILE__) . '/info.json'), true);
    if (!is_array($data)) {
        log::add('APSystemsSunspec','warning',__('Impossible de décoder le fichier info.json', __FILE__));
        goto step2;
    }
    try {
        $core_version = $data['pluginVersion'];
        config::save('version', $core_version, 'APSystemsSunspec');
    } catch (\Exception $e) {

    }

    step2:

    message::removeAll('APSystemsSunspec');
    message::add('APSystemsSunspec', sprintf(__("Installation du plugin APSystemsSunspec terminée, vous êtes en version %s", __FILE__), $core_version));
    message::add('APSystemsSunspec', sprintf(__("Il est fortement conseillé de mettre à jour les paramètres de tous les équipements en cliquant sur « scan des micro-onduleurs » de chaque ECU (si vous en avez plusieurs)", __FILE__), $core_version));
}

// Fonction exécutée automatiquement après la suppression du plugin
function APSystemsSunspec_remove() {
    $eqLogicList = eqLogic::byType('APSystemsSunspec');
    foreach ($eqLogicList as $eqLogic) {
        $id= $eqLogic->getId();
        log::add('APSystemsSunspec', 'debug', 'Suppression de l\'équipement : ' . $eqLogic->getName() . ' (ID : ' . $id . ')');
        $cron = cron::byClassAndFunction('APSystemsSunspec', 'getCronECUData', $id);
        if (is_object($cron)) {
            $cron->remove();
        }
            $eqLogic->remove();
    }

}
