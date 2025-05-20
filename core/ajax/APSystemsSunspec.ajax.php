<?php
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

    if (init('action') == 'scanMicroInverters') {
        $eqLogicId = init('id');
        $objectId = init('obj');
        $ifchecked = init('check');
        $ifdisplaychecked = init('displaycheck');
        $uniqueId = init('uniqueId');
        $unique = init('unique') == 'false' ? false : true;
        log::add('APSystemsSunspec', 'debug', 'ID reçu pour scan : ' . ($eqLogicId ? $eqLogicId : 'null') . ' unique : ' . $unique);
        if ($unique) {
            log::add('APSystemsSunspec', 'debug', 'ID unique reçu pour scan : ' . ($uniqueId ? $uniqueId : 'null'));
        }
        if (!$eqLogicId) {
            throw new Exception(__('ID de l\'équipement non fourni', __FILE__));
        }
        $eqLogic = eqLogic::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu. Vérifier l\'ID : ', __FILE__) . $eqLogicId);
        }
        $eqLogic->scanMicroInverters($objectId, $ifchecked, $unique, $uniqueId, $ifdisplaychecked);
        ajax::success();
    }

    if (init('action') == 'refreshTout') {
        $eqLogicId = init('id');
        log::add('APSystemsSunspec', 'info', __("Mise à jour complète des données de l'ECU", __FILE__));
        log::add('APSystemsSunspec', 'debug', 'ID reçu pour données : ' . ($eqLogicId ? $eqLogicId : 'null'));
        if (!$eqLogicId) {
            throw new Exception(__('ID de l\'équipement non fourni', __FILE__));
        }
        $eqLogic = eqLogic::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu. Vérifier l\'ID : ', __FILE__) . $eqLogicId);
        }
        $eqLogic->getECUData(true); // true pour forcer la mise à jour complète des données MO de l'ECU
        ajax::success();
    }
    
    if (init('action') == 'razConfigInverter') {
        $eqLogicId = init('id');
        log::add('APSystemsSunspec', 'debug', 'ID reçu pour raz MO : ' . ($eqLogicId ? $eqLogicId : 'null'));
        if (!$eqLogicId) {
            throw new Exception(__('ID de l\'équipement non fourni', __FILE__));
        }
        $eqLogic = eqLogic::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu. Vérifier l\'ID : ', __FILE__) . $eqLogicId);
        }
        $eqLogic->razConfigInverter();
        ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}