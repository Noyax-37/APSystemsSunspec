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
        log::add('APSystemsSunspec', 'debug', 'ID reçu pour scan : ' . ($eqLogicId ? $eqLogicId : 'null'));
        if (!$eqLogicId) {
            throw new Exception(__('ID de l\'équipement non fourni', __FILE__));
        }
        $eqLogic = eqLogic::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu. Vérifier l\'ID : ', __FILE__) . $eqLogicId);
        }
        $eqLogic->scanMicroInverters($objectId, $ifchecked);
        ajax::success();
    }

    if (init('action') == 'refreshTout') {
        $eqLogicId = init('id');
        log::add('APSystemsSunspec', 'debug', 'ID reçu pour données : ' . ($eqLogicId ? $eqLogicId : 'null'));
        if (!$eqLogicId) {
            throw new Exception(__('ID de l\'équipement non fourni', __FILE__));
        }
        $eqLogic = eqLogic::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            throw new Exception(__('EqLogic inconnu. Vérifier l\'ID : ', __FILE__) . $eqLogicId);
        }
        $eqLogic->getECUData();
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