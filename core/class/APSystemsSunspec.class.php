<?php

// 
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../lib/ModbusClient.php';

class APSystemsSunspec extends eqLogic {

    public static function getConfigForCommunity() {
        $hw = jeedom::getHardwareName();
        if ($hw == 'diy')
            $hw = trim(shell_exec('systemd-detect-virt'));
        if ($hw == 'none')
            $hw = 'diy';
        $distrib = trim(shell_exec('. /etc/*-release && echo $ID $VERSION_ID'));
        $res = 'OS: ' . $distrib . ' on ' . $hw;
        $res .= ' ; PHP: ' . phpversion();
        $res .= '<br/>APSystemsSunspec: v ' . config::byKey('version', 'APSystemsSunspec', 'unknown', true);
        return $res;
      }
     

    public function preSave() {
        if (empty($this->getLogicalId())) {
            throw new Exception('L\'adresse IP (logicalId) ne peut pas être vide.');
        }
    }

    public function preRemove() {
        $cron = cron::byClassAndFunction('APSystemsSunspec', 'getCronECUData', array('eqLogicId' => $this->getId()));
        if (is_object($cron)) {
            $cron->remove();
            log::add('APSystemsSunspec', 'info', "Cron supprimé pour l'équipement {$this->getName()} (ID : {$this->getId()}).");
        }
    }


    public function postInsert() {
        // Appeler la création des commandes pour l'ECU
        if (strpos($this->getLogicalId(), '_ID') !== false) {
            $this->checkAndCreateCommands();
        }
    }
            
    public function postSave() {
        // Appeler la création des commandes pour l'ECU
        log::add('APSystemsSunspec', 'info', 'PostSave : ' . $this->getName() . " sur l'IP: " . $this->getLogicalId() . ' sunset ' . jeedom::evaluateExpression('#sunset#'));
        $this->checkAndCreateCommands();

    
        $autorefresh = $this->getConfiguration('autorefresh', '');
        $id = $this->getId();
    
        $isEnable = false;
        try {
            $isEnable = $this->getIsEnable();
        } catch (Exception $e) {
            log::add('APSystemsSunspec', 'error', 'Erreur lors de l\'appel à getIsEnable : ' . $e->getMessage());
            return;
        }

        // Vérifier si l'équipement n'est pas un micro-onduleur
        if (strpos($this->getLogicalId(), '_ID') !== false) {
            $modbusID = $this->getConfiguration('modbus_id');
            $cron = cron::byClassAndFunction('APSystemsSunspec', 'getCronECUData', array('eqLogicId' => $id));
            if (is_object($cron)) {
                $cron->remove();
                log::add('APSystemsSunspec', 'info', "Cron supprimé pour l'équipement ID $id (micro-onduleur)");
            }
            // vérifier si les max power pv ont été paramétrés et si oui les sauvegarder dans le display parameters
            $maxmaxPower = 0;
            $cmdWidget = $this->getCmd(null, 'widget');
            if (is_object($cmdWidget)) {
                $parameters = $cmdWidget->getDisplay('parameters');
                for ($i = 1; $i <= 8; $i++) {
                    $maxPower = $this->getConfiguration('pvMaxPower' . $i, '');
                    if ($maxPower != '') {
                        //log::add('APSystemsSunspec', 'debug', "Max Power PV$i : $maxPower");
                        $maxmaxPower += $maxPower;
                        $parameters['pv' . $i . 'MaxPower'] = $maxPower;
                        $cmdWidget->setDisplay('parameters', $parameters);
                    }
                }
                if ($maxmaxPower != 0) {
                    $parent = eqLogic::byId($this->getConfiguration('parent_id'))->getCmd(null, 'widget');
                    if (is_object($parent)) {
                        $paramParent = $parent-> getDisplay('parameters');
                        if (is_array($paramParent)) {
                            $paramParent['pv' . $modbusID . 'MaxPower'] = $maxmaxPower;
                            //log::add('APSystemsSunspec', 'debug', 'Paramparent : ' . json_encode($paramParent));
                            $parent->setDisplay('parameters', $paramParent);
                            $parent->save();
                        }
                    }
                    $parameters['pvMaxPower'] = $maxmaxPower;
                    $cmdWidget->setDisplay('parameters', $parameters);
                }
                $cmdWidget->save();
            }
            return;
        }
    
        $cron = cron::byClassAndFunction('APSystemsSunspec', 'getCronECUData', array('eqLogicId' => $id));
        if (!is_object($cron)) {
            if ($isEnable && $autorefresh != '' && $autorefresh !== '0') {
                if (!$this->isValidCronSchedule($autorefresh)) {
                    log::add('APSystemsSunspec', 'error', "Format de schedule invalide pour autorefresh : $autorefresh");
                    return;
                }
                $cron = new cron();
                $cron->setClass('APSystemsSunspec');
                $cron->setFunction('getCronECUData');
                $cron->setOption(array('eqLogicId' => $id));
                $cron->setEnable(1);
                $cron->setDeamon(0);
                $cron->setTimeout(10);
                $cron->setSchedule($autorefresh);
                $cron->save();
                log::add('APSystemsSunspec', 'info', "Cron créé pour l'équipement ID $id avec schedule : $autorefresh");
            }
        } else {
            if ($isEnable && $autorefresh != '' && $autorefresh !== '0') {
                if (!$this->isValidCronSchedule($autorefresh)) {
                    log::add('APSystemsSunspec', 'error', "Format de schedule invalide pour autorefresh : $autorefresh");
                    return;
                }
                $cron->setSchedule($autorefresh);
                $cron->setEnable(1);
                $cron->save();
                log::add('APSystemsSunspec', 'info', "Cron mis à jour pour l'équipement ID $id avec schedule : $autorefresh");
            } else {
                $cron->setEnable(0);
                $cron->save();
                log::add('APSystemsSunspec', 'info', "Cron suspendu pour l'équipement ID $id (autorefresh désactivé ou équipement désactivé)");
            }
        }


    }
    
    private function isValidCronSchedule($schedule) {
        $parts = explode(' ', trim($schedule));
        if (count($parts) !== 5) {
            return false;
        }
        foreach ($parts as $part) {
            if ($part === '*') {
                continue;
            }
            if (preg_match('/^(\d+|\*\/\d+|\d+-\d+|(\d+,)*\d+)$/', $part)) {
                continue;
            }
            return false;
        }
        return true;
    }
    
    private function displayTable($layout, $nbLine, $cmdId, $title = '') {
        //log::add('APSystemsSunspec', 'debug', 'DisplayTable : ' . $nbLine . ' cmdId : ' . $cmdId . ' title : ' . $title);
        $layout['layout::dashboard::table::nbLine'] = $nbLine;
        $layout['layout::dashboard::table::parameters']["text::td::$nbLine::1"] = $title;
        $layout['layout::dashboard::table::parameters']["style::td::$nbLine::1"] = '';
        $layout["layout::dashboard::table::cmd::$cmdId::line"] = $nbLine;
        $layout["layout::dashboard::table::cmd::$cmdId::column"] = 1;
        return $layout;
    }
    
    public function checkAndCreateCommands() {
        
        $checked = $GLOBALS['checked'] ?? false; // Initialize $checked if not already set
        $displayChecked =  $GLOBALS['displayChecked'] ?? false; // Initialize $displayChecked if not already set
        $displayParam = displayParamsAPS();
        
        if (strpos($this->getLogicalId(), '_ID') === false) { // Si c'est un ECU

            //récupérer dans display l'info "layout::dashboard" si elle existe
            $displaylayout = $this->getDisplay();
            $displayTable = 0;
            if (!$displayChecked) {
                $nbLine = 0;
                $layout['layout::dashboard'] = 'table';
                $layout['layout::dashboard::table::nbLine'] = $nbLine;
                $layout['layout::dashboard::table::nbColumn'] = '1';
                $layout['layout::dashboard::table::parameters'] = array(
                        'center' => '1',
                        'styletable' => 'width: 100%',
                        'styletd' => '',
                );
                $displayTable = 1;
            } else {
                if ($displaylayout["layout::dashboard"] == '') {
                    $nbLine = 0;
                    $layout['layout::dashboard'] = 'table';
                    $layout['layout::dashboard::table::nbLine'] = $nbLine;
                    $layout['layout::dashboard::table::nbColumn'] = '1';
                    $layout['layout::dashboard::table::parameters'] = array(
                            'center' => '1',
                            'styletable' => 'width: 100%',
                            'styletd' => '',
                    );
                    $displayTable = 1;
                }
            }

            // Commande pour le rafraîchissement manuel
            $order2 = 1000;
            $newCmd = false;
            $refreshCmd = $this->getCmd(null, 'refresh');
            if (!is_object($refreshCmd)) {
                $refreshCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $refreshCmd->setName(__('Rafraîchir', __FILE__));
            }
            $refreshCmd->setEqLogic_id($this->getId());
            $refreshCmd->setLogicalId('refresh');
            $refreshCmd->setType('action');
            $refreshCmd->setSubType('other');
            $refreshCmd->setConfiguration('widget', '');
            $refreshCmd->setOrder($order2);
            $refreshCmd->save();

            // gestion display table
            $nbLine=0;
            $title = '';
            $layout = $this->displayTable($layout, $nbLine, $refreshCmd->getId(), $title);

            // Commande pour l'application du widget'
            $order = 1;
            $widgetCmd = $this->getCmd(null, 'widget');
            $newCmd = false;
            if (!is_object($widgetCmd)) {
                $widgetCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }

            if ($newCmd || !$checked) {
                $widgetCmd->setName(__('Widget', __FILE__));
            }
            $parameters = $widgetCmd->getDisplay('parameters');
            if (is_array($parameters)) {
                foreach ($parameters as $key => $value) {
                    if ($value !=''){
                        //log::add('APSystemsSunspec', 'info', 'Commandes $key valeur $value');
                        $displayParam[$key] = $value;
                    }
                    //log::add('APSystemsSunspec', 'info',"recopie $key ($value) dans displayparam");
                }
            }
            $widgetCmd->setEqLogic_id($this->getId());
            $widgetCmd->setLogicalId('widget');
            $widgetCmd->setType('info');
            $widgetCmd->setSubType('string');
            $widgetCmd->setConfiguration('widget', 'Aide  : https://phpvarious.github.io/documentation/widget/fr_FR/widget_scenario/distribution_onduleur/');
            $widgetCmd->setTemplate('dashboard', 'APSystemsSunspec::distribution_onduleur_APSystemsSunspec');
            $widgetCmd->setDisplay('parameters', $displayParam);
            $widgetCmd->setOrder($order);
            $widgetCmd->save();

            // gestion display table Widget
            $nbLine++;
            $title = '';
            $layout = $this->displayTable($layout, $nbLine, $widgetCmd->getId(), $title);

            // Commande pour la puissance totale
            $order++;
            $newCmd = false;
            $powerCmd = $this->getCmd(null, 'power');
            if (!is_object($powerCmd)) {
                $powerCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $powerCmd->setName(__('Puissance totale', __FILE__));
            }
            $powerCmd->setEqLogic_id($this->getId());
            $powerCmd->setLogicalId('power');
            $powerCmd->setType('info');
            $powerCmd->setSubType('numeric');
            $powerCmd->setConfiguration('widget', 'pv_power');
            $powerCmd->setUnite('W');
            $powerCmd->setOrder($order);
            $powerCmd->save();

            // gestion display table
            $nbLine++;
            //$title = 'Production globale';
            $layout = $this->displayTable($layout, $nbLine, $powerCmd->getId(), $title);

            // Commande pour l'énergie totale
            $order++;
            $newCmd = false;
            $energyCmd = $this->getCmd(null, 'totalEnergy');
            if (!is_object($energyCmd)) {
                $energyCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $energyCmd->setName(__('Énergie totale', __FILE__));
            }
            $energyCmd->setEqLogic_id($this->getId());
            $energyCmd->setLogicalId('totalEnergy');
            $energyCmd->setType('info');
            $energyCmd->setSubType('numeric');
            $energyCmd->setConfiguration('widget', 'daily_solar');
            $energyCmd->setUnite('Wh');
            $energyCmd->setOrder($order);
            $energyCmd->save();

            // gestion display table
            //$nbLine++; // Commenté pour éviter d'ajouter une ligne supplémentaire
            //$title = 'Production globale'; // le titre ne change pas
            $layout = $this->displayTable($layout, $nbLine, $energyCmd->getId(), $title);

            // Commande pour l'état global de l'ECU
            $order++;
            $newCmd = false;
            $stateCmd = $this->getCmd(null, 'state');
            if (!is_object($stateCmd)) {
                $stateCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $stateCmd->setName(__('Production MO', __FILE__));
            }
            $stateCmd->setEqLogic_id($this->getId());
            $stateCmd->setLogicalId('state');
            $stateCmd->setType('info');
            $stateCmd->setSubType('binary');
            $stateCmd->setConfiguration('widget', '');
            $stateCmd->setOrder($order);
            $stateCmd->save();

            // gestion display table
            $nbLine++;
            //$title = 'Etat';
            $layout = $this->displayTable($layout, $nbLine, $stateCmd->getId(), $title);

            // commande pour le champs info
            $order++;
            $newCmd = false;
            $infoCmd = $this->getCmd(null, 'info');
            if (!is_object($infoCmd)) {
                $infoCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $infoCmd->setName(__('Information Modbus', __FILE__));
            }
            $infoCmd->setEqLogic_id($this->getId());
            $infoCmd->setLogicalId('info');
            $infoCmd->setType('info');
            $infoCmd->setSubType('other');
            $infoCmd->setConfiguration('widget', '');
            $infoCmd->setOrder($order);
            $infoCmd->save();
            
            // gestion display table
            $nbLine++;
            //$title = 'Etat';
            $layout = $this->displayTable($layout, $nbLine, $infoCmd->getId(), $title);

            // commande pour standby tous MO
            $order++;
            $newCmd = false;
            $btnStandbyMO = $this->getCmd(null, 'btnStandbyMO');
            if (!is_object($btnStandbyMO)) {
                $btnStandbyMO = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $btnStandbyMO->setName(__('Standby MO', __FILE__));
            }
            $btnStandbyMO->setEqLogic_id($this->getId());
            $btnStandbyMO->setLogicalId('btnStandbyMO');
            $btnStandbyMO->setType('action');
            $btnStandbyMO->setSubType('other');
            $btnStandbyMO->setConfiguration('widget', '');
            $btnStandbyMO->setConfiguration('cmdRegistreLimit', 40188);
            $btnStandbyMO->setOrder($order);
            $btnStandbyMO->save();
            
            // gestion display table
            $nbLine++;
            //$title = 'StandBy MO';
            $layout = $this->displayTable($layout, $nbLine, $btnStandbyMO->getId(), $title);

            // commande pour le champs réduction puissance
            $order++;
            $newCmd = false;
            $validPowerCmd = $this->getCmd(null, 'valideLimitPower');
            if (!is_object($validPowerCmd)) {
                $validPowerCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $validPowerCmd->setName(__('Réduction Puissance', __FILE__));
            }
            $validPowerCmd->setEqLogic_id($this->getId());
            $validPowerCmd->setLogicalId('valideLimitPower');
            $validPowerCmd->setType('info');
            $validPowerCmd->setSubType('binary');
            $validPowerCmd->setConfiguration('widget', '');
            $validPowerCmd->setOrder($order);
            $validPowerCmd->save();
            
            // gestion display table
            $nbLine++;
            //$title = 'Réduction Puissance';
            $layout = $this->displayTable($layout, $nbLine, $validPowerCmd->getId(), $title);

            // commande pour application réduction Puissance
            $order++;
            $newCmd = false;
            $btnValidPowerCmd = $this->getCmd(null, 'btnValideLimitPower');
            if (!is_object($btnValidPowerCmd)) {
                $btnValidPowerCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $btnValidPowerCmd->setName(__('Application réduction Puissance', __FILE__));
            }
            $btnValidPowerCmd->setEqLogic_id($this->getId());
            $btnValidPowerCmd->setLogicalId('btnValideLimitPower');
            $btnValidPowerCmd->setType('action');
            $btnValidPowerCmd->setSubType('other');
            $btnValidPowerCmd->setConfiguration('widget', '');
            $btnValidPowerCmd->setConfiguration('cmdRegistreLimit', 40193);
            $btnValidPowerCmd->setValue($validPowerCmd->getId());
            $btnValidPowerCmd->setOrder($order);
            $btnValidPowerCmd->save();
            
            // gestion display table
            $nbLine++;
            //$title = 'Etat';
            $layout = $this->displayTable($layout, $nbLine, $btnValidPowerCmd->getId(), $title);

            // commande pour levée réduction Puissance
            $order++;
            $newCmd = false;
            $btnDevalidPowerCmd = $this->getCmd(null, 'btnDevalideLimitPower');
            if (!is_object($btnDevalidPowerCmd)) {
                $btnDevalidPowerCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $btnDevalidPowerCmd->setName(__('Arrêt réduction Puissance', __FILE__));
            }
            $btnDevalidPowerCmd->setEqLogic_id($this->getId());
            $btnDevalidPowerCmd->setLogicalId('btnDevalideLimitPower');
            $btnDevalidPowerCmd->setType('action');
            $btnDevalidPowerCmd->setSubType('other');
            $btnDevalidPowerCmd->setConfiguration('widget', '');
            $btnDevalidPowerCmd->setConfiguration('cmdRegistreLimit', 40193);
            $btnDevalidPowerCmd->setValue($validPowerCmd->getId());
            $btnDevalidPowerCmd->setOrder($order);
            $btnDevalidPowerCmd->save();
            
            // gestion display table
            $nbLine++;
            //$title = 'Etat';
            $layout = $this->displayTable($layout, $nbLine, $btnDevalidPowerCmd->getId(), $title);

            // commande pour afficher et enregistrer réduction prod des MO
            $order++;
            $newCmd = false;
            $powerLimitCmd = $this->getCmd(null, 'limitPower');
            if (!is_object($powerLimitCmd)) {
                $powerLimitCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $powerLimitCmd->setName(__('Réduction Puissance MO', __FILE__));
            }
            $powerLimitCmd->setEqLogic_id($this->getId());
            $powerLimitCmd->setLogicalId('limitPower');
            $powerLimitCmd->setType('info');
            $powerLimitCmd->setSubType('numeric');
            $powerLimitCmd->setConfiguration('widget', '');
            $powerLimitCmd->setUnite('%');
            $powerLimitCmd->setIsVisible(0);
            $powerLimitCmd->setOrder($order);
            $powerLimitCmd->save();
            
            // gestion display table
            //$nbLine++;
            //$title = 'Etat';
            //$layout = $this->displayTable($layout, $nbLine, $powerLimitCmd->getId(), $title);

            // commande pour le champs slider réduction Puissance
            $order++;
            $newCmd = false;
            $sliderCmd = $this->getCmd(null, 'sliderPower');
            $powerLimitCmd = $this->getCmd(null, 'limitPower');
            if (!is_object($sliderCmd)) {
                $sliderCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            //if ($newCmd || !$checked) {
                $sliderCmd->setName(__('Réduction puissance de', __FILE__));
            //}
            $sliderCmd->setEqLogic_id($this->getId());
            $sliderCmd->setLogicalId('sliderPower');
            $sliderCmd->setType('action');
            $sliderCmd->setSubType('slider');
            $sliderCmd->setConfiguration('widget', '');
            $sliderCmd->setConfiguration('minValue', 0);
            $sliderCmd->setConfiguration('maxValue', 100);
            $sliderCmd->setConfiguration('value', "#slider#");
            $sliderCmd->setTemplate('dashboard', "APSystemsSunspec::limitPuissance_APSystemsSunspec");
            $sliderCmd->setValue($powerLimitCmd->getId());
            $sliderCmd->setOrder($order);
            $sliderCmd->save();
            
            // gestion display table
            $nbLine++;
            //$title = 'Etat';
            $layout = $this->displayTable($layout, $nbLine, $sliderCmd->getId(), $title);

            // commande pour calcul théorique réduction prod des MO
            $order++;
            $newCmd = false;
            $limitTheoPower = $this->getCmd(null, 'limitTheoPower');
            if (!is_object($limitTheoPower)) {
                $limitTheoPower = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $limitTheoPower->setName(__('Calcul théorique puissance limite', __FILE__));
            }
            $limitTheoPower->setEqLogic_id($this->getId());
            $limitTheoPower->setLogicalId('limitTheoPower');
            $limitTheoPower->setType('info');
            $limitTheoPower->setSubType('numeric');
            $limitTheoPower->setConfiguration('widget', '');
            $limitTheoPower->setUnite('W');
            $limitTheoPower->setIsVisible(1);
            $limitTheoPower->setOrder($order);
            $limitTheoPower->save();
            
            // gestion display table
            $nbLine++;
            //$title = 'Etat';
            $layout = $this->displayTable($layout, $nbLine, $limitTheoPower->getId(), $title);

            $order++; // Commencer après les commandes principales (state, power, totalEnergy)
            $maxmaxPower = 0;

            // Récupérer tous les équipements de type APSystemsSunspec
            $allEqLogics = eqLogic::byType('APSystemsSunspec', true); // Inclut les désactivés

            // Filtrer manuellement les enfants
            $parentId = (string)$this->getId(); // Forcer en chaîne pour correspondre au JSON
            foreach ($allEqLogics as $child) {
                $config = $child->getConfiguration();
                if (isset($config['parent_id']) && (string)$config['parent_id'] === $parentId) {
                    $config = $child->getConfiguration();
                    $modbusId = $config['modbus_id'];
                    $modbusChild[]= $modbusId;
                    $moMaxPower = 0;
                    if (!is_numeric($modbusId)) {
                        log::add('APSystemsSunspec', 'error', "ID Modbus invalide pour l'enfant avec logicalId : " . $child->getLogicalId());
                        continue;
                    }
                    foreach ($config as $key => $value){
                        //log::add('APSystemsSunspec', 'info', "la clé : $key");
                        if (is_numeric($value)){
                            //log::add('APSystemsSunspec', 'info', "la clé est numérique : $key");
                            if (strpos($key, 'pvMax') !== false) {
                                $moMaxPower += $value;
                                //log::add('APSystemsSunspec', 'info', "PvMaxPower pour le MO $modbusId");
                            }
                        }
                        //log::add('APSystemsSunspec', 'info', "position pvMax : " . strpos($key, 'pvMax') . " pour la clé $key");
                    }
                    // mise à jour du paramètre puissance max
                    if ($moMaxPower == 0) {
                        $moMaxPower = 1;
                    } elseif ($moMaxPower > 1) {
                        $maxmaxPower += $moMaxPower;
                    }
                    //log::add('APSystemsSunspec', 'info', "PvMaxPower pour l'ECU : " . $maxmaxPower . " PvMaxPower pour le MO $modbusId : " . $moMaxPower); 
                    $cmdWidget = $this->getCmd(null,'widget');
                    $parameters = $cmdWidget->getDisplay('parameters');
                    $parameters["pvMaxPower$modbusId"] = $moMaxPower;
                    $parameters['pvMaxPower'] = $maxmaxPower;

                    $cmdWidget->setDisplay('parameters' , $parameters);
                    $cmdWidget->save();

/*                    $defaults = [
                        'logicalId' => '',
                        'name' => '',
                        'type' => 'info',
                        'subType' => 'numeric',
                        'unit' => '',
                        'registre' => 0,
                        'calcul' => '',
                        'size' => 1,
                        'order' => 1,
                        'coef' => 0,
                        'isVisible' => 1,
                        'widget' => ''
                    ];
*/            
                    // Commande pour la puissance du micro-onduleur
                    $this->createCommand([
                        'logicalId' => "power_mo_$modbusId",
                        'name' => "Puissance MO $modbusId",
                        'type' => 'info',
                        'subType' => 'numeric',
                        'unit' => 'W',
                        'size' => 1,
                        'order' => $order,
                        'widget' => 'pv' . $modbusId . '_power'] // Widget spécifique
                    );
                    $createCmdP = $this->getCmd(null, "power_mo_$modbusId");
                    $order++;

                    // Commande pour l'énergie du micro-onduleur
                    $createCmdE = $this->createCommand([
                        'logicalId' => "energy_mo_$modbusId",
                        'name' => "Énergie MO $modbusId",
                        'type' => 'info',
                        'subType' => 'numeric',
                        'unit' => 'Wh',
                        'size' => 1,
                        'order' => $order,
                        'widget' => 'pv' . $modbusId . '_energy'] // Widget spécifique
                    );
                    $createCmdE = $this->getCmd(null, "energy_mo_$modbusId");

                    $order++;

                    // Commande pour l'état du micro-onduleur
                    $createCmdS = $this->createCommand([
                        'logicalId' => "state_mo_$modbusId",
                        'name' =>  __('État MO', __FILE__) . " $modbusId",
                        'type' => 'info',
                        'subType' => 'binary',
                        'size' => 1,
                        'order' => $order]
                    );
                    $createCmdS = $this->getCmd(null, "state_mo_$modbusId");

                    $order++;
                    // gestion display table
                    $nbLine++;
                    // $title = 'MO ' . $modbusId;
                    $test = $createCmdP->getid();
                    $layout = $this->displayTable($layout, $nbLine, $createCmdP->getid() , $title);
                    $layout = $this->displayTable($layout, $nbLine, $createCmdE->getid(), $title);
                    $layout = $this->displayTable($layout, $nbLine, $createCmdS->getid(), $title);
                }
            }

            if (is_array($modbusChild)){
                $modbusChild = array_map('intval', $modbusChild); // Convertit toutes les valeurs en entiers
                $this->setConfiguration('modbus_id', min($modbusChild));
                $this->setConfiguration('nb_pv', count($modbusChild));
                $this->setConfiguration('min_maxPV', array('min' => min($modbusChild), 'max' => max($modbusChild)));
                $this->save(true);
            }

            if ($displayTable == 1) {
                foreach ($layout as $key => $value) {
                    $this->setDisplay($key, $value);
                }
            }
            $this->save(true);

            log::add('APSystemsSunspec', 'info', 'Commandes (re)créées pour l\'équipement ECU : ' . $this->getName() . " sur l'IP: " . $this->getLogicalId() . " adresse modbus la plus petite: " . 
                            min($modbusChild) . " et la plus grande: " . max($modbusChild) . ' toutes les adresses: ' . json_encode($modbusChild));
        } else {
            // Si c'est un micro-onduleur
            // Commande pour l'état
            $stateCmdWid = $this->getCmd(null, 'widget');
            if (!is_object($stateCmdWid)) {
                $stateCmdWid = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $stateCmdWid->setName(__('Widget', __FILE__));
            }
            $parameters = $stateCmdWid->getDisplay('parameters');
            if (is_array($parameters)) {
                foreach ($parameters as $key => $value) {
                    if ($value !=''){
                        $displayParam[$key] = $value;
                        if (strpos($key, 'pvMaxPower') === true && $value === 0) {
                            $displayParam[$key] = 1;
                        }
                    }
                }
            }
            $stateCmdWid->setEqLogic_id($this->getId());
            $stateCmdWid->setLogicalId('widget');
            $stateCmdWid->setType('info');
            $stateCmdWid->setSubType('string');
            $stateCmdWid->setConfiguration('widget', '');
            $stateCmdWid->setTemplate('dashboard', 'APSystemsSunspec::distribution_onduleur_APSystemsSunspec');
            $stateCmdWid->setDisplay('parameters', $displayParam);
            $stateCmdWid->setOrder(1);
            $stateCmdWid->save();

            $newCmd = false;
            $stateCmd = $this->getCmd(null, 'state');
            if (!is_object($stateCmd)) {
                $stateCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $stateCmd->setName(__('État', __FILE__));
            }
            $stateCmd->setEqLogic_id($this->getId());
            $stateCmd->setLogicalId('state');
            $stateCmd->setType('info');
            $stateCmd->setSubType('binary');
            $stateCmd->setOrder(2);
            $stateCmd->save();

            $newCmd = false;

            log::add('APSystemsSunspec', 'info', 'Commandes créées pour le micro-onduleur : ' . $this->getName() . " logicalID : " . $this->getLogicalId());
        }
    }

    public function createCommand(array $config): int {
        $defaults = [
            'logicalId' => '',
            'name' => '',
            'type' => 'info',
            'subType' => 'numeric',
            'unit' => '',
            'registre' => 0,
            'calcul' => '',
            'size' => 1,
            'order' => 1,
            'coef' => 0,
            'isVisible' => 0,
            'widget' => '',
            'all' => 0
        ];
        $config = array_merge($defaults, $config);

        $new = false;

        $cmd = $this->getCmd(null, $config['logicalId']);
        if (!is_object($cmd)) {
            $cmd = new APSystemsSunspecCmd();
            $cmd->setLogicalId($config['logicalId']);
            $new = true;
        }

        if (!($GLOBALS['checked'] ?? false) || $new) {
            $cmd->setName($config['name']);
        }

        $cmd->setEqLogic_id($this->getId())
            ->setType($config['type'])
            ->setSubType($config['subType'])
            ->setConfiguration('registre', $config['registre'])
            ->setConfiguration('calcul', $config['calcul'])
            ->setConfiguration('size', $config['size'])
            ->setConfiguration('widget', $config['widget'])
            ->setConfiguration('coef', $config['coef'])
            ->setConfiguration('all', $config['all'])
            ->setIsVisible($config['isVisible'])
            ->setOrder($config['order']);

        if ($config['unit']) {
            $cmd->setUnite($config['unit']);
        }

        $cmd->save();
        return $cmd->getId();
    }

    public function majCoef(): array {
        log::add('APSystemsSunspec', 'info', 'Mise à jour des coefficients');
        $ip = $this->getConfiguration('ip');
        $timeout = $this->getConfiguration('timeout', 3);
        $modbusId = $this->getConfiguration('modbus_id', 1);
    
        $registers = [
            'coefA' => ['reg' => 40076, 'index' => 0],
            'coefV' => ['reg' => 40083, 'index' => 7],
            'coefP' => ['reg' => 40085, 'index' => 9],
            'coefF' => ['reg' => 40087, 'index' => 11],
            'coefVA' => ['reg' => 40089, 'index' => 13],
            'coefVAR' => ['reg' => 40091, 'index' => 15],
            'coefPF' => ['reg' => 40093, 'index' => 17],
            'coefE' => ['reg' => 40096, 'index' => 20],
            'coefTemp' => ['reg' => 40107, 'index' => 31]
        ];
    
        $client = null;
        try {
            $client = new ModbusClient($ip, 502, $timeout);
            $client->setSlave($modbusId);
    
            // Lire le bloc de registres de 40076 à 40107 (32 registres)
            $startReg = 40076;
            $count = 32; // 40107 - 40076 + 1
            $block = $client->readHoldingRegisters($startReg, $count);
            if ($block === false || empty($block)) {
                throw new Exception("Aucune donnée reçue pour le bloc de registres $startReg-$count");
            }
            log::add('APSystemsSunspec', 'debug', "Bloc de registres $startReg-$count : " . json_encode($block));
    
            $result = [];
            foreach ($registers as $coef => $info) {
                $index = $info['index'];
                if (!array_key_exists($index, $block)) {
                    log::add('APSystemsSunspec', 'warning', "Index $index hors limites pour $coef (registre {$info['reg']})");
                    $result[$coef] = 0;
                    continue;
                }
                $value = $block[$index];
                $result[$coef] = $value > 32767 ? $value - 65536 : $value;
                log::add('APSystemsSunspec', 'debug', "Coefficient $coef (registre {$info['reg']}, index $index) : {$result[$coef]}");
            }
    
            $client->close();
            return $result;
        } catch (Exception $e) {
            if ($client) {
                $client->close();
            }
            log::add('APSystemsSunspec', 'error', "Erreur lors de la lecture des coefficients : " . $e->getMessage());
            return array_fill_keys(array_keys($registers), 0);
        }
    }

    public function checkAndCreateCommandsMO(string $type, int $pv = 4): void {

        $commands = [];
        $order2 = 500;
    
        // Commandes de coefficients (order2 à partir de 500)
        $commands[] = ['logicalId' => 'coefA', 'name' => 'Coefficient Intensité', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40076, 'calcul' => 'int16', 'size' => 1, 'order' => $order2++, 'all' => 1];
        $commands[] = ['logicalId' => 'coefV', 'name' => 'Coefficient Tension', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40083, 'calcul' => 'int16', 'size' => 1, 'order' => $order2++, 'all' => 1];
        $commands[] = ['logicalId' => 'coefP', 'name' => 'Coefficient Puissance', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40085, 'calcul' => 'int16', 'size' => 1, 'order' => $order2++, 'all' => 1];
        $commands[] = ['logicalId' => 'coefF', 'name' => 'Coefficient Fréquence', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40087, 'calcul' => 'int16', 'size' => 1, 'order' => $order2++, 'all' => 1];
        $commands[] = ['logicalId' => 'coefVA', 'name' => 'Coefficient VA', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40089, 'calcul' => 'int16', 'size' => 1, 'order' => $order2++, 'all' => 1];
        $commands[] = ['logicalId' => 'coefVAR', 'name' => 'Coefficient VAR', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40091, 'calcul' => 'int16', 'size' => 1, 'order' => $order2++, 'all' => 1];
        $commands[] = ['logicalId' => 'coefPF', 'name' => 'Coefficient Facteur de Puissance', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40093, 'calcul' => 'int16', 'size' => 1, 'order' => $order2++, 'all' => 1];
        $commands[] = ['logicalId' => 'coefE', 'name' => 'Coefficient Énergie', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40096, 'calcul' => 'int16', 'size' => 1, 'order' => $order2++, 'all' => 1];
        $commands[] = ['logicalId' => 'coefTemp', 'name' => 'Coefficient Température', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40107, 'calcul' => 'int16', 'size' => 1, 'order' => $order2++, 'all' => 1];
    
        $coef = $this->majCoef() ?: array_fill_keys(['coefA', 'coefV', 'coefP', 'coefF', 'coefVA', 'coefVAR', 'coefPF', 'coefE', 'coefTemp'], 0);
        log::add('APSystemsSunspec', 'info', "Coefficients mis à jour : A={$coef['coefA']}, V={$coef['coefV']}, P={$coef['coefP']}, F={$coef['coefF']}, VA={$coef['coefVA']}, VAR={$coef['coefVAR']}, PF={$coef['coefPF']}, E={$coef['coefE']},
                                                     Temp={$coef['coefTemp']}");
    
        $order = 3;

    
        // Commandes générales (order à partir de 3)
        $commands[] = ['logicalId' => 'ID', 'name' => 'id', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40002, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1];
        $commands[] = ['logicalId' => 'length', 'name' => 'Model Length', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40003, 'calcul' => 'uint16', 'size' => 2, 'order' => $order++, 'all' => 1];
        $commands[] = ['logicalId' => 'manufacturer', 'name' => 'Constructeur', 'type' => 'info', 'subType' => 'string', 'unit' => '', 'registre' => 40004, 'calcul' => 'string', 'size' => 16, 'order' => $order++, 'all' => 1, 'isVisible' => 1,
                            'colspan' => 1, 'line' => 2, 'column' => 1];
        $commands[] = ['logicalId' => 'model', 'name' => 'Modèle', 'type' => 'info', 'subType' => 'string', 'unit' => '', 'registre' => 40020, 'calcul' => 'string', 'size' => 16, 'order' => $order++, 'all' => 1, 'isVisible' => 1,
                            'colspan' => 1, 'line' => 2, 'column' => 2];
        $commands[] = ['logicalId' => 'version', 'name' => 'Version', 'type' => 'info', 'subType' => 'string', 'unit' => '', 'registre' => 40044, 'calcul' => 'string', 'size' => 8, 'order' => $order++, 'all' => 1, 'isVisible' => 1,
                            'colspan' => 1, 'line' => 2, 'column' => 3];
        $commands[] = ['logicalId' => 'serial', 'name' => 'Numéro de série', 'type' => 'info', 'subType' => 'string', 'unit' => '', 'registre' => 40052, 'calcul' => 'string', 'size' => 16, 'order' => $order++, 'all' => 1, 'isVisible' => 1,
                            'colspan' => 1, 'line' => 3, 'column' => 1];
        $commands[] = ['logicalId' => 'device_address', 'name' => 'Adresse Modbus', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40068, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1, 'isVisible' => 1,
                            'colspan' => 1, 'line' => 3, 'column' => 3];
        $commands[] = ['logicalId' => 'id_ph', 'name' => 'Modèle (101 = mono, 103 = tri)', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40070, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1];
        $commands[] = ['logicalId' => 'nb_reg_ph', 'name' => 'Nombre de registres', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40071, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1];
        $commands[] = ['logicalId' => 'amps', 'name' => 'Courant', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40072, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefA'], 'isVisible' => 1,
                            'colspan' => 1, 'line' => 5, 'column' => 2];
    
        // Commandes triphasées (1er bloc)
        if ($type === 'triphasé') {
            $colspan = 1;
            $commands[] = ['logicalId' => 'ampsph1', 'name' => 'Courant phase 1', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40073, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefA'], 'isVisible' => 1,
                            'colspan' => $colspan, 'line' => 7, 'column' => 1];
            $commands[] = ['logicalId' => 'ampsph2', 'name' => 'Courant phase 2', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40074, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefA'], 'isVisible' => 1,
                            'colspan' => $colspan, 'line' => 7, 'column' => 2];
            $commands[] = ['logicalId' => 'ampsph3', 'name' => 'Courant phase 3', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40075, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefA'], 'isVisible' => 1,
                            'colspan' => $colspan, 'line' => 7, 'column' => 3];
            $commands[] = ['logicalId' => 'vph1ph2', 'name' => 'Tension ph1/ph2', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40077, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefV']];
            $commands[] = ['logicalId' => 'vph2ph3', 'name' => 'Tension ph2/ph3', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40078, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefV']];
            $commands[] = ['logicalId' => 'vph3ph1', 'name' => 'Tension ph3/ph1', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40079, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefV']];
        } else {
            $colspan = 3;
        }
    
        $commands[] = ['logicalId' => 'vph1', 'name' => 'Tension ph1', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40080, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefV'], 'isVisible' => 1,
                            'colspan' => $colspan, 'line' => 6, 'column' => 1];
    
        // Commandes triphasées (2ème bloc)
        if ($type === 'triphasé') {
            $commands[] = ['logicalId' => 'vph2', 'name' => 'Tension ph2', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40081, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefV'], 'isVisible' => 1,
                            'colspan' => $colspan, 'line' => 6, 'column' => 2];
            $commands[] = ['logicalId' => 'vph3', 'name' => 'Tension ph3', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40082, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefV'], 'isVisible' => 1,
                            'colspan' => $colspan, 'line' => 6, 'column' => 3];
        }
    
        $commands[] = ['logicalId' => 'power', 'name' => 'Puissance', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40084, 'calcul' => 'int16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefP'], 'isVisible' => 1,
                            'widget' => 'pv_power', 'colspan' => 1, 'line' => 5, 'column' => 1];
        $commands[] = ['logicalId' => 'frequency', 'name' => 'Fréquence', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'Hz', 'registre' => 40086, 'calcul' => 'int16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefF'], 'isVisible' => 1,
                            'colspan' => 1, 'line' => 5, 'column' => 3];
        $commands[] = ['logicalId' => 'va', 'name' => 'Puissance apparente', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'VA', 'registre' => 40088, 'calcul' => 'int16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefVA']];
        $commands[] = ['logicalId' => 'var', 'name' => 'Puissance réactive', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'VAR', 'registre' => 40090, 'calcul' => 'int16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefVAR']];
        $commands[] = ['logicalId' => 'power_factor', 'name' => 'Facteur de puissance', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40092, 'calcul' => 'int16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefPF']];
        $commands[] = ['logicalId' => 'energy', 'name' => 'Énergie', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'Wh', 'registre' => 40094, 'calcul' => 'acc32', 'size' => 2, 'order' => $order++, 'coef' => $coef['coefE'], 'isVisible' => 1,
                            'widget' => 'daily_solar', 'colspan' => 1, 'line' => 4, 'column' => 1];
        $commands[] = ['logicalId' => 'cabinet_temp', 'name' => 'Température du boîtier', 'type' => 'info', 'subType' => 'numeric', 'unit' => '°C', 'registre' => 40103, 'calcul' => 'int16', 'size' => 1, 'order' => $order++, 'coef' => $coef['coefTemp'], 'isVisible' => 1,
                            'colspan' => 1, 'line' => 4, 'column' => 3];  
        $commands[] = ['logicalId' => 'operating_state', 'name' => 'État de fonctionnement', 'type' => 'info', 'subType' => 'string', 'unit' => '', 'registre' => 40108, 'calcul' => 'enum16', 'size' => 1, 'order' => $order++, 'isVisible' => 1,
                            'colspan' => 3, 'line' => 8, 'column' => 1];
        $commands[] = ['logicalId' => 'event1', 'name' => 'Événement 1', 'type' => 'info', 'subType' => 'string', 'unit' => '', 'registre' => 40110, 'calcul' => 'bitfield32', 'size' => 2, 'order' => $order++, 'isVisible' => 1,
                            'colspan' => 3, 'line' => 9, 'column' => 1];
        $commands[] = ['logicalId' => 'id_float', 'name' => 'ID float (111 = mono, 113 = tri)', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40122, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1];
        $commands[] = ['logicalId' => 'nb_reg_float', 'name' => 'Nombre de registres pour float32', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40123, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1];
        $commands[] = ['logicalId' => 'amps_float', 'name' => 'Courant float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40124, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        $commands[] = ['logicalId' => 'ampsph1_float', 'name' => 'Courant phase 1 float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40126, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
    
        // Commandes triphasées (3ème bloc)
        if ($type === 'triphasé') {
            $commands[] = ['logicalId' => 'ampsph2_float', 'name' => 'Courant phase 2 float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40128, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
            $commands[] = ['logicalId' => 'ampsph3_float', 'name' => 'Courant phase 3 float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40130, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
            $commands[] = ['logicalId' => 'vph1ph2_float', 'name' => 'Tension ph1/ph2 float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40132, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
            $commands[] = ['logicalId' => 'vph2ph3_float', 'name' => 'Tension ph2/ph3 float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40134, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
            $commands[] = ['logicalId' => 'vph3ph1_float', 'name' => 'Tension ph3/ph1 float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40136, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        }
    
        $commands[] = ['logicalId' => 'vph1_float', 'name' => 'Tension ph1 float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40138, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
    
        // Commandes triphasées (4ème bloc)
        if ($type === 'triphasé') {
            $commands[] = ['logicalId' => 'vph2_float', 'name' => 'Tension ph2 float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40140, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
            $commands[] = ['logicalId' => 'vph3_float', 'name' => 'Tension ph3 float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40142, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        }
    
        $commands[] = ['logicalId' => 'power_float', 'name' => 'Puissance float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40144, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        $commands[] = ['logicalId' => 'frequency_float', 'name' => 'Fréquence float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'Hz', 'registre' => 40146, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        $commands[] = ['logicalId' => 'va_float', 'name' => 'Puissance apparente float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'VA', 'registre' => 40148, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        $commands[] = ['logicalId' => 'var_float', 'name' => 'Puissance réactive float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'VAR', 'registre' => 40150, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        $commands[] = ['logicalId' => 'power_factor_float', 'name' => 'Facteur de puissance float', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40152, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        $commands[] = ['logicalId' => 'energy_float', 'name' => 'Énergie float', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'Wh', 'registre' => 40154, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        $commands[] = ['logicalId' => 'cabinet_temp_float', 'name' => 'Température du boîtier float', 'type' => 'info', 'subType' => 'numeric', 'unit' => '°C', 'registre' => 40162, 'calcul' => 'float32', 'size' => 2, 'order' => $order++];
        $commands[] = ['logicalId' => 'id_ic', 'name' => 'ID contrôles', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40184, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1];
        $commands[] = ['logicalId' => 'nb_reg_ic', 'name' => 'Nombre de registres pour IC', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40185, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1];
        $commands[] = ['logicalId' => 'connection_state', 'name' => 'État de connexion', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40188, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'isVisible' => 1,
                            'colspan' => 3, 'line' => 10, 'column' => 1];
        $commands[] = ['logicalId' => 'wmaxlimpct', 'name' => 'Puissance max limite (en %)', 'type' => 'info', 'subType' => 'numeric', 'unit' => '%', 'registre' => 40189, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'coef' => -1, 'isVisible' => 1,
                            'colspan' => 3, 'line' => 11, 'column' => 1];
        $commands[] = ['logicalId' => 'wmaxlim_enabled', 'name' => 'Réduction puissance activée (1= activée)', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40193, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'isVisible' => 1,
                            'colspan' => 3, 'line' => 12, 'column' => 1];
        $commands[] = ['logicalId' => 'id_dcdata', 'name' => 'ID DC data', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40212, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1];
        $commands[] = ['logicalId' => 'nb_reg_dcdata', 'name' => 'Nombre de registres pour DC data', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'registre' => 40213, 'calcul' => 'uint16', 'size' => 1, 'order' => $order++, 'all' => 1];
    
        // Commandes PV
        if ($pv >= 1) {
            $commands[] = ['logicalId' => 'dc_voltage_dcv1', 'name' => 'Tension DC PV1', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40214, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv1_voltage',
                            'colspan' => 1, 'line' => 13, 'column' => 1];
            $commands[] = ['logicalId' => 'dc_current_dcv1', 'name' => 'Courant DC PV1', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40230, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv1_current',
                            'colspan' => 1, 'line' => 13, 'column' => 2];
            $commands[] = ['logicalId' => 'dc_power_dcv1', 'name' => 'Puissance DC PV1', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40246, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv1_power',
                            'colspan' => 1, 'line' => 13, 'column' => 3];
        }
        if ($pv >= 2) {
            $commands[] = ['logicalId' => 'dc_voltage_dcv2', 'name' => 'Tension DC PV2', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40216, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv2_voltage',
                            'colspan' => 1, 'line' => 14, 'column' => 1];
            $commands[] = ['logicalId' => 'dc_current_dcv2', 'name' => 'Courant DC PV2', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40232, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv2_current',
                            'colspan' => 1, 'line' => 14, 'column' => 2];
            $commands[] = ['logicalId' => 'dc_power_dcv2', 'name' => 'Puissance DC PV2', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40248, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv2_power',
                            'colspan' => 1, 'line' => 14, 'column' => 3];
        }
        if ($pv >= 3) {
            $commands[] = ['logicalId' => 'dc_voltage_dcv3', 'name' => 'Tension DC PV3', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40218, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv3_voltage',
                            'colspan' => 1, 'line' => 15, 'column' => 1];
            $commands[] = ['logicalId' => 'dc_current_dcv3', 'name' => 'Courant DC PV3', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40234, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv3_current',
                            'colspan' => 1, 'line' => 15, 'column' => 2];
            $commands[] = ['logicalId' => 'dc_power_dcv3', 'name' => 'Puissance DC PV3', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40250, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv3_power',
                            'colspan' => 1, 'line' => 15, 'column' => 3];
        }
        if ($pv >= 4) {
            $commands[] = ['logicalId' => 'dc_voltage_dcv4', 'name' => 'Tension DC PV4', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40220, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv4_voltage',
                            'colspan' => 1, 'line' => 16, 'column' => 1];
            $commands[] = ['logicalId' => 'dc_current_dcv4', 'name' => 'Courant DC PV4', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40236, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv4_current',
                            'colspan' => 1, 'line' => 16, 'column' => 2];
            $commands[] = ['logicalId' => 'dc_power_dcv4', 'name' => 'Puissance DC PV4', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40252, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv4_power',
                            'colspan' => 1, 'line' => 16, 'column' => 3];
        }
        if ($pv >= 5) {
            $commands[] = ['logicalId' => 'dc_voltage_dcv5', 'name' => 'Tension DC PV5', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40222, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv5_voltage',
                            'colspan' => 1, 'line' => 17, 'column' => 1];
            $commands[] = ['logicalId' => 'dc_current_dcv5', 'name' => 'Courant DC PV5', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40238, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv5_current',
                            'colspan' => 1, 'line' => 17, 'column' => 2];
            $commands[] = ['logicalId' => 'dc_power_dcv5', 'name' => 'Puissance DC PV5', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40254, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv5_power',
                            'colspan' => 1, 'line' => 17, 'column' => 3];
        }
        if ($pv >= 6) {
            $commands[] = ['logicalId' => 'dc_voltage_dcv6', 'name' => 'Tension DC PV6', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40224, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv6_voltage',
                            'colspan' => 1, 'line' => 18, 'column' => 1];
            $commands[] = ['logicalId' => 'dc_current_dcv6', 'name' => 'Courant DC PV6', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40240, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv6_current',
                            'colspan' => 1, 'line' => 18, 'column' => 2];
            $commands[] = ['logicalId' => 'dc_power_dcv6', 'name' => 'Puissance DC PV6', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40256, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv6_power',
                            'colspan' => 1, 'line' => 18, 'column' => 3];
        }
        if ($pv >= 7) {
            $commands[] = ['logicalId' => 'dc_voltage_dcv7', 'name' => 'Tension DC PV7', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40226, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv7_voltage',
                            'colspan' => 1, 'line' => 19, 'column' => 1];
            $commands[] = ['logicalId' => 'dc_current_dcv7', 'name' => 'Courant DC PV7', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40242, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv7_current',
                            'colspan' => 1, 'line' => 19, 'column' => 2];
            $commands[] = ['logicalId' => 'dc_power_dcv7', 'name' => 'Puissance DC PV7', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40258, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv7_power',
                            'colspan' => 1, 'line' => 19, 'column' => 3];
        }
        if ($pv >= 8) {
            $commands[] = ['logicalId' => 'dc_voltage_dcv8', 'name' => 'Tension DC PV8', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'V', 'registre' => 40228, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv8_voltage',
                            'colspan' => 1, 'line' => 20, 'column' => 1];
            $commands[] = ['logicalId' => 'dc_current_dcv8', 'name' => 'Courant DC PV8', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'A', 'registre' => 40244, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv8_current',
                            'colspan' => 1, 'line' => 20, 'column' => 2];
            $commands[] = ['logicalId' => 'dc_power_dcv8', 'name' => 'Puissance DC PV8', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'W', 'registre' => 40260, 'calcul' => 'float32', 'size' => 2, 'order' => $order++, 'coef' => 0, 'isVisible' => 1, 'widget' => 'pv8_power',
                            'colspan' => 1, 'line' => 20, 'column' => 3];
        }
    
        //récupérer dans display l'info "layout::dashboard" si elle existe
        $displayChecked =  $GLOBALS['displayChecked'] ?? false; // Initialize $displayChecked if not already set
        log::add('APSystemsSunspec', 'info', "display check : " . $displayChecked);
        $displaylayout = $this->getDisplay();
        $displayTable = false;
        if ($displayChecked) {
            foreach ($this->getCmd() as $cmd) {
                if ($cmd->getLogicalId() == 'widget') {
                    $cmdWidget = $cmd->getId();
                    break;
                }
            }
            $layout['layout::dashboard'] = 'table';
            $layout['layout::dashboard::table::nbLine'] = 21;
            $layout['layout::dashboard::table::nbColumn'] = 3;
            $layout['layout::dashboard::table::parameters'] = array(
                    'center' => '1',
                    'styletable' => 'width: 100%',
                    'styletd' => '',
            );
            $layout["layout::dashboard::table::parameters"]["style::td::1::1"] = "colspan = 3";
            $layout["layout::dashboard::table::parameters"]["style::td::21::1"] = "colspan = 3";
            $layout["layout::dashboard::table::parameters"]["style::td::1::2"] = "display: none;";
            $layout["layout::dashboard::table::parameters"]["style::td::21::2"] = "display: none;";
            $layout["layout::dashboard::table::parameters"]["style::td::1::3"] = "display: none;";
            $layout["layout::dashboard::table::parameters"]["style::td::21::3"] = "display: none;";
            $displayTable = true;
        } else {
            if ($displaylayout["layout::dashboard"] == '') {
                foreach ($this->getCmd() as $cmd) {
                    if ($cmd->getLogicalId() == 'widget') {
                        $cmdWidget = $cmd->getId();
                        break;
                    }
                }
                $layout['layout::dashboard'] = 'table';
                $layout['layout::dashboard::table::nbLine'] = 21;
                $layout['layout::dashboard::table::nbColumn'] = 3;
                $layout['layout::dashboard::table::parameters'] = array(
                        'center' => '1',
                        'styletable' => 'width: 100%',
                        'styletd' => '',
                );
                $layout["layout::dashboard::table::parameters"]["style::td::1::1"] = "colspan = 3";
                $layout["layout::dashboard::table::parameters"]["style::td::21::1"] = "colspan = 3";
                $layout["layout::dashboard::table::parameters"]["style::td::1::2"] = "display: none;";
                $layout["layout::dashboard::table::parameters"]["style::td::21::2"] = "display: none;";
                $layout["layout::dashboard::table::parameters"]["style::td::1::3"] = "display: none;";
                $layout["layout::dashboard::table::parameters"]["style::td::21::3"] = "display: none;";
                $displayTable = true;
            }
        }


        // Créer les commandes
        foreach ($commands as $cmdConfig) {
            $cmdId = $this->createCommand($cmdConfig);
            $cmd = $this->getCmd(null, $cmdId);
            if ($displayTable) {
                if (array_key_exists('line', $cmdConfig)) {
                    $line = $cmdConfig['line'];
                    $column = $cmdConfig['column'];
                    $colspan = $cmdConfig['colspan'];
                    $title = $cmdConfig['title'];
                    $layout['layout::dashboard::table::parameters']["text::td::$line::$column"] = $title;
                    $layout["layout::dashboard::table::cmd::$cmdId::line"] = $line;
                    $layout["layout::dashboard::table::cmd::$cmdId::column"] = $column;
                    if ($colspan >= 1) {
                        $layout["layout::dashboard::table::parameters"]["style::td::$line::$column"] = "colspan = $colspan";
                        for ($i = 1; $i < $colspan; $i++) {
                            $layout["layout::dashboard::table::parameters"]["style::td::$line::" . ($column + $i)] = "display: none;";
                        }
                    } else {
                        $layout["layout::dashboard::table::parameters"]["style::td::$line::$column"] = '';
                    } 
                } else {
                    $layout["layout::dashboard::table::cmd::$cmdId::line"] = 21;
                    $layout["layout::dashboard::table::cmd::$cmdId::column"] = 1;
                }
            }
        }
        // Enregistrer le layout
        if ($displayTable) {
            log::add('APSystemsSunspec', 'info', "Enregistrement du layout pour l'équipement : " . $this->getName() . " (ID : " . $this->getId() . ") - " . json_encode($layout));
            foreach ($layout as $key => $value) {
                $this->setDisplay($key, $value);
            }
            $this->save(true);
        }
    }


    public function refreshData() {
        log::add('APSystemsSunspec', 'info', "Rafraîchissement manuel des données pour l'équipement : " . $this->getName() . " (ID : " . $this->getId() . ")");
        $this->getECUData(false); // false pour n'interroger que les registres nécessaires
    }

    private function decodeFloat32($high, $low) {
        $bin = sprintf("%016b%016b", $high, $low);
        $sign = substr($bin, 0, 1) == '1' ? -1 : 1;
        $exp = bindec(substr($bin, 1, 8)) - 127;
        $mantissa = 1 + bindec(substr($bin, 9)) / pow(2, 23);
        $return = round($sign * $mantissa * pow(2, $exp), 2);
        return $return;
    }

    private function decodeBitfield32($decode, $bitfield) {
        $return = '';
        if ($decode == 1) {
            switch ($bitfield) {
                case 0:
                    $return = 'Défaut à la terre';
                    break;
                case 1:
                    $return = 'Survoltage DC';
                    break;
                case 2:
                    $return = 'AC déconnecté';
                    break;
                case 3:
                    $return = 'DC déconnecté';
                    break;
                case 4:
                    $return = 'GRID déconnecté';
                    break;
                case 5:
                    $return = 'Boitier ouvert';
                    break;
                case 6:
                    $return = 'Extinction manuelle';
                    break;
                case 7:
                    $return = 'Surchauffe';
                    break;
                case 8:
                    $return = 'Fréquence trop élevée';
                    break;
                case 9:
                    $return = 'Fréquence trop basse';
                    break;
                case 10:
                    $return = 'Tension AC trop élevée';
                    break;
                case 11:
                    $return = 'Tension AC trop basse';
                    break;
                case 12:
                    $return = "Fusible de chaîne grillé à l'entrée";
                    break;
                case 13:    
                    $return = 'Température trop basse';
                    break;
                case 14:
                    $return = "Mémoire 'perdue'";
                    break;
                case 15:
                    $return = 'Test hardware défectueux';
                    break;
                case 16:
                    $return = '16 ';
                    break;
                case 17:
                    $return = '17 ';
                    break;
                case 18:
                    $return = '18 ';
                    break;
                case 19:
                    $return = '19 ';
                    break;
                case 20:
                    $return = '20 ';
                    break;
                case 21:
                    $return = '21 ';
                    break;
                case 22:
                    $return = '22 ';
                    break;
                case 23:
                    $return = '23 ';
                    break;
                case 24:
                    $return = '24 ';
                    break;
                case 25:
                    $return = '25 ';
                    break;
                case 26:
                    $return = '26 ';
                    break;
                case 27:
                    $return = '27 ';
                    break;
                case 28:
                    $return = '28 ';
                    break;
                case 29:
                    $return = '29 ';
                    break;
                case 30:
                    $return = '30 ';
                    break;
                case 31:
                    $return = '31 ';
                    break;
                default:
                    $return = '';
            }
        } else {
            $return = '';
        }
        return $return;
    }

    public function scanMicroInverters($objectId = null, $ifChecked = false, $unique = false, $uniqueId = null, $ifDisplayChecked = false) {
        $ip = $this->getLogicalId();
        $timeout = $this->getConfiguration('timeout', 3);
        $modbusId = 1;
        $maxAttempts = 247;
        $order = $this->getOrder();
        $GLOBALS['checked'] = $ifChecked;
        $GLOBALS['displayChecked'] = $ifDisplayChecked;
        //$unique = $uniques == 'true' ?  false : true;
        if ($unique) {
            $modbusId = (int)$uniqueId;
            $maxAttempts = (int)$uniqueId;
            log::add('APSystemsSunspec', 'info', "Scan unique pour IP : $ip (ID équipement : " . $this->getId() . ")" . " - Modbus ID : $modbusId");
        } else {
            log::add('APSystemsSunspec', 'info', "Début du scan pour IP : $ip (ID équipement : " . $this->getId() . ")");
        }
        while ($modbusId <= $maxAttempts) {
            try {
                // Premier appel à queryModbus pour le registre 40070
                $response = $this->queryModbus($ip, $modbusId, 40070, $timeout);
                //log::add('APSystemsSunspec', 'debug', "Valeur exacte de \$response pour Modbus ID $modbusId (registre 40070) : " . var_export($response, true));

                if ($response === false) {
                    if ($unique == true) {
                        log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId (registre 40070), fin du scan");
                        break;
                    } else {
                        log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId (registre 40070), vérification de 'trou' dans la suite des MO déjà enregistrés");
                        $eqLogics = eqLogic::byType('APSystemsSunspec');
                        $founded = false;
                        foreach ($eqLogics as $eqLogic) {
                            $logicalId = $eqLogic->getLogicalId();
                            $modbusIdToScan = (int)$eqLogic->getConfiguration('modbus_id');
                            if (is_int(strpos($logicalId, $ip)) && is_int(strpos($logicalId, '_ID')) && ($modbusIdToScan > $modbusId)) {
                                $modbusId = $modbusIdToScan;
                                log::add('APSystemsSunspec', 'debug', "'Trou' trouvé pour Modbus ID $modbusId, on continue le scan");
                                $founded = true;
                                break;
                            }
                        }
                        if ($founded) {
                            continue;
                        }
                        log::add('APSystemsSunspec', 'debug', "Pas de 'trou' trouvé, on arrête le scan");
                        break;
                    }
                } else {
                    // Lire les 8 registres pour le modèle (40020 à 40027, pour une chaîne de 16 caractères)
                    $client = new ModbusClient($ip, 502, $timeout);
                    $client->setSlave($modbusId);
                    $modelData = [];
                    $modelData = $client->readHoldingRegisters(40020, 16);
                    if (isset($client)) {
                        $client->close();
                    }

                    //log::add('APSystemsSunspec', 'debug', "Valeur exacte de \$modelData pour Modbus ID $modbusId (registre 40020) : " . var_export($modelData, true));
        
                    if ($modelData === false) {
                        if ($unique == true) {
                            log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId (registre 40020), fin du scan");
                            break;
                        } else {
                            log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId (registre 40020), vérification de 'trou' dans la suite des MO déjà enregistrés");
                            $eqLogics = eqLogic::byType('APSystemsSunspec');
                            $founded = false;
                            foreach ($eqLogics as $eqLogic) {
                                $logicalId = $eqLogic->getLogicalId();
                                $modbusIdToScan = $eqLogic->getConfiguration('modbus_id');
                                if (is_int(strpos($logicalId, $ip)) && is_int(strpos($logicalId, '_ID')) && ($modbusIdToScan > $modbusId)) {
                                    $modbusId = $modbusIdToScan;
                                    log::add('APSystemsSunspec', 'debug', "'Trou' trouvé pour Modbus ID $modbusId, on continue le scan");
                                    $founded = true;
                                    break;
                                }
                            }
                            if ($founded) {
                                continue;
                            }
                            log::add('APSystemsSunspec', 'debug', "Pas de 'trou' trouvé, on arrête le scan");
                            break;
                        }
                    } else {
        
                        // Convertir les données en une chaîne
                        $responsemodel = '';
                        foreach ($modelData as $value) {
                            $responsemodel .= pack('n', $value);
                        }
                        $responsemodel = trim($responsemodel); // Supprimer les caractères nuls ou espaces
                        log::add('APSystemsSunspec', 'debug', "Réponse Modbus ID $modbusId : $response - Modèle : $responsemodel");
            
                        $type = null;
                        if ($response == 101) {
                            $type = 'monophasé';
                        } elseif ($response == 103) {
                            $type = 'triphasé';
                        }
            
                        // Vérifier le modèle pour déterminer le nombre de PV (par défaut = 2)
                        $pv = 2;
                        if (strpos($responsemodel, 'YC1000') !== false || strpos($responsemodel, 'QS1') !== false || strpos($responsemodel, 'QT2') !== false) {
                            $pv = 4;
                        }
            
                        if ($type !== null) {
                            log::add('APSystemsSunspec', 'info', "Micro-onduleur détecté : ID $modbusId ($type) - Modèle : $responsemodel - PV : $pv");
                            $order+= $modbusId;
                            $this->createChildEquipment($ip, $modbusId, $type, $timeout, $objectId, $order, $pv);
                        }
            
                        $modbusId++;
                    }
                }
            } catch (Exception $e) {
                log::add('APSystemsSunspec', 'debug', "Erreur Modbus ID $modbusId : " . $e->getMessage());
                if (isset($client)) {
                    $client->close();
                }
                $this->checkAndCreateCommands();
                break;
            }
        }
        if ($unique == true) {
            log::add('APSystemsSunspec', 'info', "Scan terminé pour Modbus ID $modbusId sur l'IP $ip");
        } else {
            log::add('APSystemsSunspec', 'info', "Scan terminé pour Modbus ID $modbusId (max $maxAttempts)");
        }
        // Mettre à jour les commandes de l'ECU parent après le scan
        $this->checkAndCreateCommands();
    }

    private function queryModbus($ip, $modbusId, $register, $timeout) {
        try {
            log::add('APSystemsSunspec', 'debug', "Query Modbus : IP $ip, ID $modbusId, Registre $register");
            $client = new ModbusClient($ip, 502, $timeout);
            $client->setSlave($modbusId);
            $response = $client->readHoldingRegisters($register, 1);

            if ($response === false || empty($response)) {
                log::add('APSystemsSunspec', 'debug', "Aucune donnée reçue pour ID $modbusId");
                return false;
            }
            log::add('APSystemsSunspec', 'debug', "Valeur lue : " . $response[0]);
            $client->close();
            return $response[0];
        } catch (Exception $e) {
            if (isset($client)) {
                $client->close();
            }
            log::add('APSystemsSunspec', 'debug', "Aucune réponse pour ID $modbusId : " . $e->getMessage());
            return false;
        }
    }

    public static function getCronECUData($options) {
        $eqLogicId = $options['eqLogicId'];
        $eqLogic = self::byId($eqLogicId);
        if (!is_object($eqLogic)) {
            log::add('APSystemsSunspec', 'error', "Équipement ID $eqLogicId introuvable pour le cron.");
            return;
        }

        $name = $eqLogic->getName();
        log::add('APSystemsSunspec', 'debug', "getCronECUData appelé pour l'équipement : $name (ID : $eqLogicId)");

        // Cette vérification est redondante avec celle dans postSave(), mais on la garde pour plus de sécurité
        if (strpos($eqLogic->getLogicalId(), '_ID') !== false) {
            log::add('APSystemsSunspec', 'debug', "L'équipement $name (ID : $eqLogicId) est un micro-onduleur, pas un ECU. Cron ignoré.");
            return;
        }

        if (!$eqLogic->getIsEnable()) {
            log::add('APSystemsSunspec', 'debug', "Équipement $name (ID : $eqLogicId) désactivé. Cron ignoré.");
            return;
        }

        try {
            $eqLogic->getECUData(false); // Appel de la méthode getECUData pour récupérer les données variables (tout n'est pas récupéré)
            log::add('APSystemsSunspec', 'info', "Mise à jour des données terminée pour l'équipement : $name (ID : $eqLogicId)");
        } catch (Exception $e) {
            log::add('APSystemsSunspec', 'error', "Erreur lors de la mise à jour des données pour l'équipement $name (ID : $eqLogicId) : " . $e->getMessage());
        }
    }

    // Méthode pour vérifier si l'interrogation est désactivée
    private function isPollingDisabled() {
        $startTime = $this->getConfiguration('stopPollingStart', '');
        $endTime = $this->getConfiguration('stopPollingEnd', '');

        // Récupérer les IDs des commandes depuis la configuration (si elles existent)
        $startCmdId = $this->getConfiguration('stopPollingStartCmd', '');
        $endCmdId = $this->getConfiguration('stopPollingEndCmd', '');

        // Récupérer les commandes correspondantes à partir des IDs
        $startCmd = is_numeric($startCmdId) ? cmd::byId($startCmdId) : null;
        $endCmd = is_numeric($endCmdId) ? cmd::byId($endCmdId) : null;

        // Vérifier si les commandes existent et ont une valeur (par exemple, 1 pour actif)
        $hasStartCmd = is_object($startCmd) ? ($startCmd->execCmd() == 1) : false;
        $hasEndCmd = is_object($endCmd) ? ($endCmd->execCmd() == 1) : false;


        // Si aucune configuration, ni variable #sunset#/#sunrise#, ni commande au format #1234# n'est définie pour start OU end, on continue
        if ((empty($startTime) && strpos($startTime, '#sunset#') === false && strpos($startTime, '#sunrise#') === false && !preg_match('/^#\d+#$/', $startTime) && !$hasStartCmd) ||
            (empty($endTime) && strpos($endTime, '#sunset#') === false && strpos($endTime, '#sunrise#') === false && !preg_match('/^#\d+#$/', $endTime) && !$hasEndCmd)) {
            return false;
        }

        $currentTime = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $currentHourMinute = $currentTime->format('H:i');

        // Gestion de #sunrise# ou #sunset# avec ou sans décalage pour startTime
        if (preg_match('/^#sun(rise|set)#(?:\s*([+-])\s*(\d+))?$/', $startTime, $matches)) {
            $sunType = $matches[1]; // sunrise ou sunset
            $sign = isset($matches[2]) ? $matches[2] : ''; // Signe (+ ou -), vide si pas de décalage
            $number = isset($matches[3]) ? (int)$matches[3] : 0; // Nombre, 0 si pas de décalage
            $minutesOffset = $sign === '-' ? -$number : $number; // Appliquer le signe au décalage
            $sunValue = jeedom::evaluateExpression("#sun{$sunType}#");
            if (is_numeric($sunValue)) {
                $hours = floor($sunValue / 100);
                $minutes = $sunValue % 100;
                $sunDateTime = DateTime::createFromFormat('H:i', sprintf('%02d:%02d', $hours, $minutes), new DateTimeZone('Europe/Paris'));
                if ($sunDateTime) {
                    $sunDateTime->modify("$minutesOffset minutes");
                    $startTime = $sunDateTime->format('H:i');
                } else {
                    $startTime = '';
                    log::add('APSystemsSunspec', 'warning', "Erreur lors de la création de DateTime pour #sun{$sunType}#. Valeur: $sunValue");
                }
            } else {
                $startTime = '';
                log::add('APSystemsSunspec', 'warning', "#sun{$sunType}# n'a pas retourné une valeur numérique valide. Valeur: $sunValue");
            }
        }
        // Gestion des commandes Jeedom avec ou sans décalage pour startTime
        elseif (preg_match('/^#(\d+)#(?:\s*([+-])\s*(\d+))?$/', $startTime, $matches)) {
            $cmdId = $matches[1]; // ID de la commande
            $sign = isset($matches[2]) ? $matches[2] : ''; // Signe (+ ou -), vide si pas de décalage
            $number = isset($matches[3]) ? (int)$matches[3] : 0; // Nombre, 0 si pas de décalage
            $minutesOffset = $sign === '-' ? -$number : $number; // Appliquer le signe au décalage
            $cmd = cmd::byId($cmdId);
            if (is_object($cmd)) {
                $cmdValue = $cmd->execCmd();
                if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $cmdValue)) {
                    $cmdDateTime = DateTime::createFromFormat('H:i', $cmdValue, new DateTimeZone('Europe/Paris'));
                    if ($cmdDateTime) {
                        $cmdDateTime->modify("$minutesOffset minutes");
                        $startTime = $cmdDateTime->format('H:i');
                    } else {
                        $startTime = '';
                        log::add('APSystemsSunspec', 'warning', "Erreur lors de la création de DateTime pour la commande startTime #{$cmdId}#. Valeur: $cmdValue");
                    }
                } elseif (is_numeric($cmdValue) && $cmdValue >= 0 && $cmdValue <= 2359) {
                    $hours = floor($cmdValue / 100);
                    $minutes = $cmdValue % 100;
                    $cmdDateTime = DateTime::createFromFormat('H:i', sprintf('%02d:%02d', $hours, $minutes), new DateTimeZone('Europe/Paris'));
                    if ($cmdDateTime) {
                        $cmdDateTime->modify("$minutesOffset minutes");
                        $startTime = $cmdDateTime->format('H:i');
                    } else {
                        $startTime = '';
                        log::add('APSystemsSunspec', 'warning', "Erreur lors de la création de DateTime pour la commande startTime #{$cmdId}#. Valeur: $cmdValue");
                    }
                } else {
                    $startTime = '';
                    log::add('APSystemsSunspec', 'warning', "Valeur de la commande startTime #{$cmdId}# invalide. Valeur: $cmdValue");
                }
            } else {
                $startTime = '';
                log::add('APSystemsSunspec', 'warning', "Commande startTime #{$cmdId}# introuvable.");
            }
        }

        // Gestion de #sunrise# ou #sunset# avec ou sans décalage pour endTime
        if (preg_match('/^#sun(rise|set)#(?:\s*([+-])\s*(\d+))?$/', $endTime, $matches)) {
            $sunType = $matches[1]; // sunrise ou sunset
            $sign = isset($matches[2]) ? $matches[2] : ''; // Signe (+ ou -), vide si pas de décalage
            $number = isset($matches[3]) ? (int)$matches[3] : 0; // Nombre, 0 si pas de décalage
            $minutesOffset = $sign === '-' ? -$number : $number; // Appliquer le signe au décalage
            $sunValue = jeedom::evaluateExpression("#sun{$sunType}#");
            if (is_numeric($sunValue)) {
                $hours = floor($sunValue / 100);
                $minutes = $sunValue % 100;
                $sunDateTime = DateTime::createFromFormat('H:i', sprintf('%02d:%02d', $hours, $minutes), new DateTimeZone('Europe/Paris'));
                if ($sunDateTime) {
                    $sunDateTime->modify("$minutesOffset minutes");
                    $endTime = $sunDateTime->format('H:i');
                } else {
                    $endTime = '';
                    log::add('APSystemsSunspec', 'warning', "Erreur lors de la création de DateTime pour #sun{$sunType}#. Valeur: $sunValue");
                }
            } else {
                $endTime = '';
                log::add('APSystemsSunspec', 'warning', "#sun{$sunType}# n'a pas retourné une valeur numérique valide. Valeur: $sunValue");
            }
        }
        // Gestion des commandes Jeedom avec ou sans décalage pour endTime
        elseif (preg_match('/^#(\d+)#(?:\s*([+-])\s*(\d+))?$/', $endTime, $matches)) {
            $cmdId = $matches[1]; // ID de la commande
            $sign = isset($matches[2]) ? $matches[2] : ''; // Signe (+ ou -), vide si pas de décalage
            $number = isset($matches[3]) ? (int)$matches[3] : 0; // Nombre, 0 si pas de décalage
            $minutesOffset = $sign === '-' ? -$number : $number; // Appliquer le signe au décalage
            $cmd = cmd::byId($cmdId);
            if (is_object($cmd)) {
                $cmdValue = $cmd->execCmd();
                if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $cmdValue)) {
                    $cmdDateTime = DateTime::createFromFormat('H:i', $cmdValue, new DateTimeZone('Europe/Paris'));
                    if ($cmdDateTime) {
                        $cmdDateTime->modify("$minutesOffset minutes");
                        $endTime = $cmdDateTime->format('H:i');
                    } else {
                        $endTime = '';
                        log::add('APSystemsSunspec', 'warning', "Erreur lors de la création de DateTime pour la commande endTime #{$cmdId}#. Valeur: $cmdValue");
                    }
                } elseif (is_numeric($cmdValue) && $cmdValue >= 0 && $cmdValue <= 2359) {
                    $hours = floor($cmdValue / 100);
                    $minutes = $cmdValue % 100;
                    $cmdDateTime = DateTime::createFromFormat('H:i', sprintf('%02d:%02d', $hours, $minutes), new DateTimeZone('Europe/Paris'));
                    if ($cmdDateTime) {
                        $cmdDateTime->modify("$minutesOffset minutes");
                        $endTime = $cmdDateTime->format('H:i');
                    } else {
                        $endTime = '';
                        log::add('APSystemsSunspec', 'warning', "Erreur lors de la création de DateTime pour la commande endTime #{$cmdId}#. Valeur: $cmdValue");
                    }
                } else {
                    $endTime = '';
                    log::add('APSystemsSunspec', 'warning', "Valeur de la commande endTime #{$cmdId}# invalide. Valeur: $cmdValue");
                }
            } else {
                $endTime = '';
                log::add('APSystemsSunspec', 'warning', "Commande endTime #{$cmdId}# introuvable.");
            }
        }

        // Si une commande stopPollingStartCmd existe, utiliser sa valeur
        if ($hasStartCmd) {
            $startTime = $startCmd->execCmd();
        }

        // Si une commande stopPollingEndCmd existe, utiliser sa valeur
        if ($hasEndCmd) {
            $endTime = $endCmd->execCmd();
        }

        // Si aucune des deux valeurs n'est définie après traitement, on continue
        if (empty($startTime) || empty($endTime)) {
            return false;
        }

        // Validation du format HH:MM ou conversion si format 648 (06:48) pour startTime
        if (!empty($startTime) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $startTime)) {
            if (is_numeric($startTime) && $startTime >= 0 && $startTime <= 2359) {
                $startTime = sprintf('%02d:%02d', floor($startTime / 100), $startTime % 100);
            } else {
                log::add('APSystemsSunspec', 'error', "Format invalide pour stopPollingStart: $startTime. Utilisez HH:MM ou un nombre comme 648.");
                return false;
            }
        }

        // Validation du format HH:MM ou conversion si format 648 (06:48) pour endTime
        if (!empty($endTime) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $endTime)) {
            if (is_numeric($endTime) && $endTime >= 0 && $endTime <= 2359) {
                $endTime = sprintf('%02d:%02d', floor($endTime / 100), $endTime % 100);
            } else {
                log::add('APSystemsSunspec', 'error', "Format invalide pour stopPollingEnd: $endTime. Utilisez HH:MM ou un nombre comme 648.");
                return false;
            }
        }

        // Conversion des heures en minutes pour comparaison
        $startMinutes = (intval(substr($startTime, 0, 2)) * 60) + intval(substr($startTime, 3, 2));
        $endMinutes = (intval(substr($endTime, 0, 2)) * 60) + intval(substr($endTime, 3, 2));
        $currentMinutes = (intval(substr($currentHourMinute, 0, 2)) * 60) + intval(substr($currentHourMinute, 3, 2));

        // Comparaison pour déterminer si on est dans la plage de désactivation
        if ($startMinutes > $endMinutes) {
            if ($currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes) {
                log::add('APSystemsSunspec', 'debug', "Interrogation désactivée : heure actuelle ($currentHourMinute) dans la plage $startTime à $endTime (chevauche minuit).");
                return true;
            }
        } else {
            if ($currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes) {
                log::add('APSystemsSunspec', 'debug', "Interrogation désactivée : heure actuelle ($currentHourMinute) dans la plage $startTime à $endTime.");
                return true;
            }
        }

        return false;
    }




    public function getECUData($all=false) {
        $ip = $this->getLogicalId();
        $timeout = $this->getConfiguration('timeout', 3);
        $parentId = $this->getId();
        
        $children = eqLogic::byTypeAndSearchConfiguration('APSystemsSunspec', array('parent_id' => $parentId));
        if (empty($children)) {
            log::add('APSystemsSunspec', 'info', "Aucun micro-onduleur trouvé pour l'ECU avec IP : $ip (ID : $parentId)");
            $this->checkAndUpdateCmd('power', 0);
            $this->checkAndUpdateCmd('totalEnergy', 0);
            $this->checkAndUpdateCmd('state', 0);
            $this->refreshWidget();
            return false;
        }

        // Vérifier si l'interrogation est désactivée pour la période actuelle
        if ($this->isPollingDisabled()) {
            log::add('APSystemsSunspec', 'info', "Interrogation des micro-onduleurs désactivée pour l'ECU avec IP : $ip (ID : $parentId) pendant la période définie.");
            
            // Mettre toutes les commandes à 0 pendant la période d'arrêt
            $this->checkAndUpdateCmd('power', 0);
            $this->checkAndUpdateCmd('totalEnergy', 0);
            $this->checkAndUpdateCmd('state', 0);

            foreach ($children as $child) {
                $modbusId = $child->getConfiguration('modbus_id');
                if (!is_numeric($modbusId)) {
                    log::add('APSystemsSunspec', 'error', "ID Modbus invalide pour l'enfant avec logicalId : " . $child->getLogicalId());
                    continue;
                }
                $child->checkAndUpdateCmd('state', 0);
                $this->checkAndUpdateCmd("power_mo_$modbusId", 0);
                $this->checkAndUpdateCmd("energy_mo_$modbusId", 0);
                $this->checkAndUpdateCmd("state_mo_$modbusId", 0);
                $child->refreshWidget();
            }

            $this->refreshWidget();
            return false;
        }

        $success = true;
        $totalPower = 0;
        $totalEnergy = 0;

        foreach ($children as $child) {
            if (!$child->getIsEnable()) {
                $name = $child->getName();
                log::add('APSystemsSunspec', 'debug', "Enfant $name (ID : {$child->getId()}) désactivé, ignoré.");
                $child->checkAndUpdateCmd('state', 0);
                // Mettre à jour les commandes correspondantes dans l'ECU parent
                $modbusId = $child->getConfiguration('modbus_id');
                $this->checkAndUpdateCmd("power_mo_$modbusId", 0);
                $this->checkAndUpdateCmd("energy_mo_$modbusId", 0);
                $this->checkAndUpdateCmd("state_mo_$modbusId", 0);
                $child->refreshWidget();
                continue;
            }

            $modbusId = $child->getConfiguration('modbus_id');
            if (!is_numeric($modbusId)) {
                log::add('APSystemsSunspec', 'error', "ID Modbus invalide pour l'enfant avec logicalId : " . $child->getLogicalId());
                $success = false;
                $child->checkAndUpdateCmd('state', 0);
                // Mettre à jour les commandes correspondantes dans l'ECU parent
                $this->checkAndUpdateCmd("power_mo_$modbusId", 0);
                $this->checkAndUpdateCmd("energy_mo_$modbusId", 0);
                $this->checkAndUpdateCmd("state_mo_$modbusId", 0);
                $child->refreshWidget();
                continue;
            }

            $client = null;
            try {
                $client = new ModbusClient($ip, 502, $timeout);
                $client->setSlave($modbusId);

                $data = [];

                $premReg = 40002;
                $nbReg = 125;
                $firstBlock = $client->readHoldingRegisters($premReg, $nbReg);
                if ($firstBlock === false || empty($firstBlock)) {
                    throw new Exception("Aucune donnée reçue pour Modbus ID $modbusId (premier bloc)");
                }
                for ($i = 0; $i < $nbReg; $i++) {
                    $data[$i] = $firstBlock[$i];
                }

                $premReg = 40002 + $nbReg;
                $nbReg = 125;
                $secondBlock = $client->readHoldingRegisters($premReg, $nbReg);
                if ($secondBlock === false || empty($secondBlock)) {
                    throw new Exception("Aucune donnée reçue pour Modbus ID $modbusId (deuxième bloc)");
                }
                for ($i = 0; $i < $nbReg; $i++) {
                    $data[$i + $nbReg] = $secondBlock[$i];
                }
    /*
                $premReg = $premReg + $nbReg;
                $nbReg = 1;
                $thirdBlock = $client->readHoldingRegisters($premReg, $nbReg);
                if ($thirdBlock === false || empty($thirdBlock)) {
                    throw new Exception("Aucune donnée reçue pour Modbus ID $modbusId (troisième bloc)");
                }
                for ($i = 0; $i < $nbReg; $i++) {
                    $data[$i + $nbReg + 125] = $thirdBlock[$i];
                }
    */
                $this->updateChildCommands($child, $data, $all);

                // vérifier si les dcpower_dcv* ne sont pas à 0 si les U et I ne le sont pas
                for ($i = 1; $i <= 8; $i++) {
                    $voltageCmd = $child->getCmd('info', "dc_voltage_dcv$i");
                    $currentCmd = $child->getCmd('info', "dc_current_dcv$i");
                    $powerCmd = $child->getCmd('info', "dc_power_dcv$i");

                    if (is_object($voltageCmd) && is_object($currentCmd) && is_object($powerCmd)) {
                        log::add('APSystemsSunspec', 'debug', "Vérification de la puissance DC pour PV$i : U = " . ($voltageCmd ? $voltageCmd->execCmd() : 'N/A') . ", I = " . ($currentCmd ? $currentCmd->execCmd() : 'N/A') . ", P = " . ($powerCmd ? $powerCmd->execCmd() : 'N/A'));
                        $voltageValue = $voltageCmd->execCmd();
                        $currentValue = $currentCmd->execCmd();
                        $powerValue = $powerCmd->execCmd();
                        if (is_numeric($voltageValue) && is_numeric($currentValue) && $voltageValue > 0 && $currentValue > 0 && $powerValue == 0) {
                            // Mettre à jour la puissance DC si la tension et le courant sont valides
                            $powerValue = round(floatval($voltageValue * $currentValue), 2);
                            $child->checkAndUpdateCmd("dc_power_dcv$i", $powerValue);
                            log::add('APSystemsSunspec', 'debug', "Puissance DC PV$i mise à jour : $powerValue W");
                        }
                    }
                }

                // Récupérer la puissance du micro-onduleur
                $powerValue = 0;
                $powerCmd = $child->getCmd('info', 'power');
                if (is_object($powerCmd)) {
                    $powerValue = $powerCmd->execCmd();
                    if (is_numeric($powerValue) && $powerValue >= 0) {
                        $powerValue = round(floatval($powerValue), 2);
                        $totalPower += $powerValue;
                    } else {
                        $powerValue = 0;
                    }
                }
                // Mettre à jour la commande power_mo_X dans l'ECU
                $this->checkAndUpdateCmd("power_mo_$modbusId", $powerValue);

                // Récupérer l'énergie du micro-onduleur
                $energyValue = 0;
                $energyCmd = $child->getCmd('info', 'energy');
                if (is_object($energyCmd)) {
                    $energyValue = $energyCmd->execCmd();
                    if (is_numeric($energyValue) && $energyValue >= 0) {
                        $energyValue = round(floatval($energyValue), 2);
                        $totalEnergy += $energyValue;
                    } else {
                        $energyValue = 0;
                    }
                }
                // Mettre à jour la commande energy_mo_X dans l'ECU
                $this->checkAndUpdateCmd("energy_mo_$modbusId", $energyValue);

                // Mettre à jour l'état du micro-onduleur
                $child->checkAndUpdateCmd('state', 1);
                // Mettre à jour la commande state_mo_X dans l'ECU
                $this->checkAndUpdateCmd("state_mo_$modbusId", 1);

                log::add('APSystemsSunspec', 'info', "Données ECU récupérées pour enfant ID $modbusId (logicalId : " . $child->getLogicalId() . ")");
            } catch (Exception $e) {
                $child->checkAndUpdateCmd('state', 0);
                // Mettre à jour les commandes correspondantes dans l'ECU parent
                $this->checkAndUpdateCmd("power_mo_$modbusId", 0);
                $this->checkAndUpdateCmd("energy_mo_$modbusId", 0);
                $this->checkAndUpdateCmd("state_mo_$modbusId", 0);
                log::add('APSystemsSunspec', 'error', "Erreur lors de la récupération des données ECU pour enfant ID $modbusId : " . $e->getMessage());
                $success = false;
            } finally {
                if ($client !== null) {
                    $client->close();
                }
                $child->refreshWidget();
            }
        }

        // Arrondir les valeurs totales à 2 décimales avant de les enregistrer
        $totalPower = round($totalPower, 2);
        if ($totalPower >= 1000) {
            $totalPower = round($totalPower / 1000, 2) * 1000; // Arrondir à la puissance de 1000
        }
        $totalEnergy = round($totalEnergy, 2);
        if ($totalEnergy >= 1000) {
            $totalEnergy = round($totalEnergy / 1000, 2) * 1000; // Arrondir à la puissance de 1000
        }

        // Mettre à jour les commandes de l'ECU parent
        $this->checkAndUpdateCmd('power', $totalPower);
        $this->checkAndUpdateCmd('totalEnergy', $totalEnergy);
        $this->checkAndUpdateCmd('state', $totalPower > 0 ? 1 : 0);
        $this->refreshWidget();

        return $success;
    }

    private function updateChildCommands($child, $data, $all=false) {
        $commands = $child->getCmd('info');
        if (empty($commands)) {
            log::add('APSystemsSunspec', 'warning', "Aucune commande de type 'info' trouvée pour l'enfant avec logicalId : " . $child->getLogicalId());
            return;
        }

        $offset = 40002;

        foreach ($commands as $cmd) {
            $registre = $cmd->getConfiguration('registre', 0);
            if ($registre - $offset < 0) {
                log::add('APSystemsSunspec', 'debug', "Registre $registre non interrogé en cron pour le commande {$cmd->getLogicalId()}");
                continue;
            }
            $size = $cmd->getConfiguration('size', 1);
            $calcul = $cmd->getConfiguration('calcul', '');
            $coef = $cmd->getConfiguration('coef', 0);
            //$nameCmd = $cmd->getName();
            $logicalId = $cmd->getLogicalId();
            $cmdAll = $cmd->getConfiguration('all', false) == 0 ? false : true;

            if ($registre < $offset || $registre + $size - 1 > 40261 || ($cmdAll && !$all)) {
                log::add('APSystemsSunspec', 'debug', "Registre $registre hors plage ou non interrogé pour la commande {$cmd->getLogicalId()} (taille : $size)");
                continue;
            }

            $index = $registre - $offset;

            try {
                if ($calcul == 'float32' && $size == 2) {
                    $value = $this->decodeFloat32($data[$index], $data[$index + 1]);
                    // Filtrer les valeurs aberrantes (infinies, NaN ou trop grandes)
                    if (is_numeric($value) && (abs($value) > 1e+30 || is_infinite($value) || is_nan($value))) {
                        $value = null; // Remplacer par null si la valeur est aberrante
                        log::add('APSystemsSunspec', 'warning', "Valeur aberrante détectée pour la commande {$cmd->getLogicalId()} : valeur ignorée");
                    } else {
                        // Arrondir toutes les valeurs float32 à 2 décimales
                        if (is_numeric($value)) {
                            $value = round($value, 2);
                        }
                    }
                } elseif ($calcul == 'uint16') {
                    $value = $data[$index];
                } elseif ($calcul == 'int16') {
                    $value = ($data[$index] > 32767) ? $data[$index] - 65536 : $data[$index];
                } elseif ($calcul == 'acc32' && $size == 2) {
                    $value = ($data[$index] << 16) + $data[$index + 1];
                } elseif ($calcul == 'string') {
                    $value = '';
                    for ($i = 0; $i < $size; $i++) {
                        $value .= pack('n', $data[$index + $i]);
                    }
                    $value = trim($value);
                } elseif ($calcul == 'enum16') {
                    $value = $data[$index];
                    switch ($value) {
                        case 0:
                            $value = 'Non défini';
                            break;
                        case 1:
                            $value = 'OFF';
                            break;
                        case 2:
                            $value = 'En sommeil';
                            break;
                        case 3:
                            $value = 'Démarrage';
                            break;
                        case 4:
                            $value = 'MPPT actif';
                            break;
                        case 5:
                            $value = 'Puissance limitée';
                            break;
                        case 6:
                            $value = 'Arrêt en cours';
                            break;
                        case 7:
                            $value = 'Erreur';
                            break;
                        case 8: 
                            $value = 'En veille';
                            break;
                        default:
                            $value = 'État inconnu';
                    }
                } elseif ($calcul == 'bitfield32' && $size == 2) {
                    $value_hex[0] = $data[$index];
                    $value_hex[1] = $data[$index + 1];
                    $value = '';
                    $bitPosition = 0;
                    // Convertir les valeurs hexadécimales en binaire
                    for ($bit = 0; $bit < 16; $bit++) {
                        $mask = 1 << $bit;
                        $bitValue = ($value_hex[0] & $mask) ? 1 : 0;
                        $value .= $this->decodeBitfield32($bitValue, $bitPosition);
                        $bitPosition++;
                    }
                    for ($bit = 0; $bit < 16; $bit++) {
                        $mask = 1 << $bit;
                        $bitValue = ($value_hex[1] & $mask) ? 1 : 0;
                        $value .= $this->decodeBitfield32($bitValue, $bitPosition);
                        $bitPosition++;
                    }
                    if ($value == '') {
                        $value = 'Aucun événement';
                    } else {
                        $value = rtrim($value);
                    }
                    log::add('APSystemsSunspec', 'debug', "Valeur décodée pour la commande {$cmd->getLogicalId()} : {$value}");
                } else {
                    $value = $data[$index];
                    log::add('APSystemsSunspec', 'debug', "Type de calcul non spécifié pour la commande {$cmd->getLogicalId()}, utilisation de la valeur brute : $value");
                }

                if (is_numeric($value)) {
                    if (!is_numeric($coef)) {
                        log::add('APSystemsSunspec', 'warning', "Coefficient invalide pour la commande {$cmd->getLogicalId()} : $coef. Utilisation de 0 par défaut.");
                        $coef = 0;
                    }
                    if (strpos($logicalId, 'coef') === false) {
                        $value = $value * pow(10, $coef);
                        // Filtrer les tensions incohérentes pour les commandes uint16 (vph1ph2, vph1, etc.)
                        if (in_array($logicalId, ['vph1ph2', 'vph2ph3', 'vph3ph1', 'vph1', 'vph2', 'vph3'])) {
                            if ($value < 0 || $value > 500) {
                                $value = 0; // Remplacer par 0 si la tension est hors plage (0-500 V)
                                log::add('APSystemsSunspec', 'warning', "Tension incohérente détectée pour la commande {$cmd->getLogicalId()} (valeur : $value V) : valeur remplacée par 0");
                            }
                        }
                    }
                }

                $child->checkAndUpdateCmd($cmd->getLogicalId(), $value);
                log::add('APSystemsSunspec', 'debug', "Commande {$cmd->getLogicalId()} mise à jour avec la valeur : " . (is_null($value) ? 'null' : $value) . " (registre : $registre, taille : $size, type : $calcul, coef : $coef)");
            } catch (Exception $e) {
                log::add('APSystemsSunspec', 'error', "Erreur lors de la mise à jour de la commande {$cmd->getLogicalId()} : " . $e->getMessage());
            }
        }
    }

    private function createChildEquipment($ip, $modbusId, $type, $timeout = 3, $objectId = null, $order = 0, $pv = 2) {
        $existingEqLogic = eqLogic::byLogicalId($ip . '_ID' . $modbusId, 'APSystemsSunspec');
        if (is_object($existingEqLogic)) {
            log::add('APSystemsSunspec', 'info', "Équipement déjà existant pour ID $modbusId, pas de création. Mise à jour des commandes si nécessaire.");
            $existingEqLogic->setConfiguration('timeout', $timeout);
            $existingEqLogic->setConfiguration('type', $type);
            if (is_null($existingEqLogic->getConfiguration('nb_pv')) || !is_numeric($existingEqLogic->getConfiguration('nb_pv'))) {
                $existingEqLogic->setConfiguration('nb_pv', $pv);
            } else {
                $pv = $existingEqLogic->getConfiguration('nb_pv');
            }
            $existingEqLogic->setOrder($order);
            if ($existingEqLogic instanceof APSystemsSunspec) {
                $existingEqLogic->checkAndCreateCommandsMO($type, $pv);
                //$existingEqLogic->checkAndCreateCommands();
            } else {
                log::add('APSystemsSunspec', 'error', "L'équipement existingEqlogic n'est pas une instance d'APSystemsSunspec. Impossible d'appeler checkAndCreateCommandsMO.");
            }
            
            $existingEqLogic->save();
            // Mettre à jour les commandes de l'ECU parent après la création de l'enfant
            // $parent = eqLogic::byId($this->getId());
            //if (is_object($parent)) {
                //if ($parent instanceof APSystemsSunspec) {
                    //$parent->checkAndCreateCommands();
                //} else {
                    //log::add('APSystemsSunspec', 'error', "Parent n'est pas une instance d'APSystemsSunspec. Impossible d'appeler checkAndCreateCommands.");
                //}
            //}
            return;
        }
        $newEqLogic = new APSystemsSunspec();
        $newEqLogic->setName($this->getName() . ' ID ' . $modbusId . ' (' . $type . ')');
        $newEqLogic->setLogicalId($ip . '_ID' . $modbusId);
        $newEqLogic->setEqType_name('APSystemsSunspec');
        $newEqLogic->setConfiguration('parent_id', $this->getId());
        $newEqLogic->setConfiguration('ip', $ip);
        $newEqLogic->setConfiguration('modbus_id', (int)$modbusId);
        $newEqLogic->setConfiguration('type', $type);
        $newEqLogic->setConfiguration('nb_pv', $pv);
        $newEqLogic->setConfiguration('timeout', $timeout);
        $newEqLogic->setOrder($order);
        if ($objectId) {
            $newEqLogic->setObject_id($objectId);
        }
        // Définir l'état de l'enfant comme activé et visible
        $newEqLogic->setIsVisible(1);
        $newEqLogic->setIsEnable(1);
        $newEqLogic->save();

        $newEqLogic->checkAndCreateCommandsMO($type, $pv);
        log::add('APSystemsSunspec', 'debug', "Équipement créé avec ID : " . $newEqLogic->getId());
        // Mettre à jour les commandes de l'ECU parent après la création de l'enfant
        $this->checkAndCreateCommands();
    }

    public function setParameter($register, $value) {
        $ip = $this->getLogicalId();
        $modbusId = $this->getConfiguration('modbus_id', 1);
        $timeout = $this->getConfiguration('timeout', 3);
        try {
            $client = new ModbusClient($ip, 502, $timeout);
            $client->setSlave($modbusId);
            $success = $client->writeMultipleRegisters($register, $value);

            if ($success) {
                log::add('APSystemsSunspec', 'info', "Écriture réussie : registre $register, valeur " . json_encode($value) . " pour Modbus ID $modbusId");
                return true;
            }
            return false;
        } catch (Exception $e) {
            log::add('APSystemsSunspec', 'error', "Erreur lors de l'écriture Modbus : " . $e->getMessage());
            return false;
        } finally {
            if (isset($client)) {
                $client->close();
            }
        }
    }

    public function getImage() {
        if (strpos($this->getLogicalId(), '_ID') === false) {
            return 'plugins/APSystemsSunspec/plugin_info/APSystemsSunspec_icon.png';
        } else {
            return 'plugins/APSystemsSunspec/plugin_info/microinverter_icon.png';
        }
    }
}

class APSystemsSunspecCmd extends cmd {
    public function execute($_options = array()) {
        $eqLogic = $this->getEqLogic();
        $actionBtn = $this->getLogicalId();
        $btnAction = 0;
        log::add('APSystemsSunspec','debug',"Action sur $actionBtn");
        $offset = 0;
        
        switch ($actionBtn) {
            case 'refresh':
                if ($eqLogic instanceof APSystemsSunspec) {
                    $eqLogic->refreshData();
                } else {
                    log::add('APSystemsSunspec', 'error', "The method 'refreshData' does not exist in the eqLogic class.");
                }
            break;
            case 'sliderPower':
                $virtualCmd = virtualCmd::byId($this->getValue());
                $cmdTheoPower = $eqLogic->getCmd(null, 'limitTheoPower');
                $value = isset($_options['slider']) ? intval($_options['slider']) : 100;
                $result = jeedom::evaluateExpression($value);
                $virtualCmd->event($result);
                $cmdWidget = $eqLogic->getCmd(null, 'widget');
                if (is_object($cmdWidget)) {
                    $parameters = $cmdWidget->getDisplay('parameters');
                    if (isset($parameters['pvMaxPower']) && ($parameters['pvMaxPower'] != '') && (intval($parameters['pvMaxPower']) != 1)) {
                        $maxPower = intval($parameters['pvMaxPower']);
                        $result = intval(((100 - $result) * $maxPower) / 100);
                    } else {
                        log::add('APSystemsSunspec', 'debug', "Le paramètre maxPower n'est pas défini pour la commande widget.");
                        $result = 0;
                    }        
                }
                $cmdTheoPower->event($result);
            break;
            case 'btnValideLimitPower':
                $value = array(0, 0, 0, 0, 1);
            case 'btnDevalideLimitPower':

                if (!isset($value)) {
                    $value = array(0, 0, 0, 0, 0);
                }
                $limit = 1;
                $offset = 4;
                $cmd = $eqLogic->getCmd(null, $actionBtn);
                $registre = 'cmdRegistreLimit';
                $default = 40193;
                $cmdLimit = $eqLogic->getCmd(null, 'limitPower');
            	$valueLimit = intval($cmdLimit->execCmd()) * 10;
                $value[0] = $valueLimit;
                //$cmdCurl = 'curl "http://192.168.1.141/index.php/meter/set_meter_display_funcs?meter_func=1&this_func=1&power_limit=1000"';
                //$output = shell_exec($cmdCurl);
                //log::add('APSystemsSunspec','debug',"Action sur $actionBtn => réponse à $cmdCurl => " . json_encode($output));
                $btnAction = 1; 
                
            break;
            case 'btnStandbyMO':

                $cmd = $eqLogic->getCmd(null, $actionBtn);
                $registre = 'cmdRegistreLimit';
                $default = 40188;
                $valueName = $cmd->getName();
                $btnAction = 1;
                if ($valueName == 'Standby MO') {
                    $value[] = 0;
                    $rename = 'Wakeup MO';
                } elseif ($valueName == 'Wakeup MO') {
                    $value[] = 1;
                    $rename = 'Standby MO';
                } else {
                    log::add('APSystemsSunspec','error',"Action sur $actionBtn => le nom de la commande n'est pas reconnue et est $valueName");
                    $btnAction = 0;
                }
                    
            break;
        }
        if ($btnAction == 1){
            $registreCmd = intval($cmd->getConfiguration($registre, $default)) - $offset;
            log::add('APSystemsSunspec','debug',"Action sur $actionBtn => commande registre $registreCmd avec la valeur" . json_encode($value));
            if ($eqLogic instanceof APSystemsSunspec) {
                $result = $eqLogic->setParameter($registreCmd, $value);
                if ($result) {
                    log::add('APSystemsSunspec', 'info', "Action sur $actionBtn => registre $registreCmd mis à jour avec succès.");
                    if (isset($rename)) {
                        $cmd->setName($rename);
                        $cmd->save();
                    }
                    if (isset($limit)) {
                        $cmdValide = $eqLogic->getCmd(null, 'valideLimitPower');
                        $cmdValide->event($value[4]);
                        }
                } else {
                    log::add('APSystemsSunspec', 'error', "Action sur $actionBtn => échec de la mise à jour du registre $registreCmd.");
                }
            } else {
                log::add('APSystemsSunspec', 'error', "The method 'setParameter' is not available for the eqLogic class.");
            }
        }
        
        // Action sur modification du slider
        switch ($this->getSubType()) {
            case 'slider':
                log::add('APSystemsSunspec','debug','Action subtype Slider');
            break;
            case 'other':
                log::add('APSystemsSunspec','debug','Action subtype Other');
            break;
        }
    }
}


/*     * **********************Fonctions locales*************************** */

 
function displayParamsAPS(){
    // : Fonction de personnalisation des paramètres de l'équipement    
    $return = array();
    $return = array(
    //------------ Général -------------
    'Background'=>'transparent',
    'inverterColor'=>'grey', // : Couleur des éléments de catégorie "onduleur" [ Exemple : #fffff, white]
    'inverterColorTextIn'=>'black', // : Couleur des textes internes a l'onduleur [ Exemple : #fffff, white]
    'noGridColor'=> '#db041c', // : Couleur du logo "noGridColor" [ Exemple : #fffff, white]
    'colorDanger'=>'red', // : Couleur des rectangles en cas de dépassement de puissance. (Défaut : red)
    'blink'=>0, // : Active le clignotement du rectangle en cas de dépassement de puissance. (Défaut: 0)
    'fontAlert'=>0, // : Applique la couleur 'colorDanger' au texte en cas de dépassement de puissance. (Défaut: 0)
    'activateGauge'=>1, // : Active les gauges dans les rectangles. Pas oublier de renseigner les MaxPower (loadMaxPower, load1MaxPower, pv1Maxpower...) [ Défaut : 1 ]
    'activateShadow'=>0, // : Active un shadow sur les textes dans les rectangles.(Permet d'avoir un contraste lors de l'utilisation des gauges) [ Défaut : 0 ]
    'shadowStyle'=>'2px 2px 3px black', // : Style du shadow. [ défaut : 2px 2px 3px black ]
    //------------ Solar ------------
    'pv1Name'=>'PV1', // : Personnalisation du nom du Pv1. (Ex: Ouest, Nord, PV1 ...)
    'pv1MaxPower'=>1, // : Puissance max du Pv1. (permet la gestion de la gauge et des alertes)
    'pv2Name'=>'PV2', // : Personnalisation du nom du Pv2. (Ex: Ouest, Nord, PV2 ...)
    'pv2MaxPower'=>1, // : Puissance max du Pv2. (permet la gestion de la gauge et des alertes)
    'pv3Name'=>'PV3', // : Personnalisation du nom du Pv3. (Ex: Ouest, Nord, PV3 ...)
    'pv3MaxPower'=>1, // : Puissance max du Pv3. (permet la gestion de la gauge et des alertes)
    'pv4Name'=>'PV4', // : Personnalisation du nom du Pv4. (Ex: Ouest, Nord, PV4 ...)
    'pv4MaxPower'=>1, // : Puissance max du Pv4. (permet la gestion de la gauge et des alertes)
    'pv5Name'=>'PV5', // : Personnalisation du nom du Pv5. (Ex: Ouest, Nord, PV5 ...)
    'pv5MaxPower'=>1, // : Puissance max du Pv5. (permet la gestion de la gauge et des alertes)
    'pv6Name'=>'PV6', // : Personnalisation du nom du Pv6. (Ex: Ouest, Nord, PV6 ...)
    'pv6MaxPower'=>1, // : Puissance max du Pv6. (permet la gestion de la gauge et des alertes)
    'pv7Name'=>'PV7', // : Personnalisation du nom du Pv7. (Ex: Ouest, Nord, PV7 ...)
    'pv7MaxPower'=>1, // : Puissance max du Pv7. (permet la gestion de la gauge et des alertes)
    'pv8Name'=>'PV8', // : Personnalisation du nom du Pv8. (Ex: Ouest, Nord, PV8 ...)
    'pv8MaxPower'=>1, // : Puissance max du Pv8. (permet la gestion de la gauge et des alertes)
    'dailySolarText'=>'DAILY SOLAR', // : Personnalisation du texte. (défaut : DAILY SOLAR);
    'pvMaxPower'=>1, // : Puissance max des PV. (permet la gestion, de la vitesse de l'animation, de la gauge et des alertes)
    'solarColor'=>'orange', // : Couleur des éléments de catégorie "solaire" [ Exemple : #fffff, white]
    'pvState0Color'=>'orange', // : Couleur des éléments du pv si pas de production [ Exemple : #fffff, white | défaut : #solarColor#]
    //------------ Load ------------
    'load1Name'=>'Load1', // : Personnalisation du nom du Load1. (Ex: C.E, Clim, ...)
    'load1Icon'=>'oven', // : Choix icône intègrée : Voir liste en bas.
    'load1maxPower'=>0, // : Puissance Max (permet la gestion de la gauge et des alertes)
    'load2Name'=>'Load2', // : Personnalisation du nom du Load2. (Ex: C.E, Clim, ...)
    'load2Icon'=>'oven', // : Choix icône intègrée : Voir liste en bas.
    'load2maxPower'=>0, // : Puissance Max (permet la gestion de la gauge et des alertes)
    'load3Name'=>'Load3', // : Personnalisation du nom du Load3. (Ex: C.E, Clim, ...)
    'load3Icon'=>'oven', // : Choix icône intègrée : Voir liste en bas.
    'load3maxPower'=>0, // : Puissance Max (permet la gestion de la gauge et des alertes)
    'load4Name'=>'Load4', // : Personnalisation du nom du Load4. (Ex: C.E, Clim, ...)
    'load4Icon'=>'oven', // : Choix icône intègrée : Voir liste en bas.
    'load4maxPower'=>0, // : Puissance Max (permet la gestion de la gauge et des alertes)
    'force4Load'=>0, // : Force a 4 loads par colonne. (désactivé si utilisation de 7 PV ou plus !) [défaut : 0]
    'dailyLoadText'=>'DAILY LOAD', // : Personnalisation du texte. (défaut : DAILY LOAD)
    'loadMaxPower'=>0, // : Puissance max des équipements "Load". (permet la gestion, de la vitesse de l'animation, de la gauge et des alertes)
    'loadColor'=>'#5fb6ad', // : Couleur des éléments de catégorie "load" [ Exemple : #fffff, white]
    'loadAnimate'=>1, // : Pour désactiver l'animation des Load passer ce paramètre a 0
    'activateGaugeRatio'=>1, // : Passer ce paramètre a 0 pour désactiver la gauge ratio.
    'sizeGaugeRatio'=>4, // : Taille de la gauge ratio. [ 1 a 10 | défaut : 4]
    //------------ Grid ------------
    'displayGrid'=>0, // : affiche ou non tous les éléments du réseau (0 ou 1, défaut : 1)
    'dailyGridSellText'=>'DAILY GRID SELL', // : Personnalisation du texte. (défaut : DAILY GRID SELL)
    'dailyGridBuyText'=>'DAILY GRID BUY', // : Personnalisation du texte. (défaut : DAILY GRID BUY)
    'gridMaxPower'=>0, // : Puissance max de consommation. (permet la gestion, de la vitesse de l'animation, de la gauge et des alertes)
    'gridColor'=>'#5490c2', // : Couleur par défaut des éléments de catégorie "réseau" [ Exemple : #fffff, white | défaut : #5490c2 ]
    'gridSellColor'=>'#5490c2', // : Couleur des éléments si en vente (injection). [ Exemple : #fffff, white | défaut : #5490c2 ]
    'gridBuyColor'=>'#5490c2', // : Couleur des éléments si en achat (consommation). [ Exemple : #fffff, white | défaut : #5490c2 ]
    //------------ Battery ------------
    'dailyBatteryChargeText'=>'DAILY CHARGE', // : Personnalisation du texte. (défaut : DAILY CHARGE)
    'dailyBatteryDischargeText'=>'DAILY DISCHARGE', // : Personnalisation du texte. (défaut : DAILY DISCHARGE)
    'batteryMaxPower'=>0, // : Puissance max de la batterie. (permet la gestion, de la vitesse de l'animation, de la gauge et des alertes)
    'batterySocShutdown'=>0, // : SOC mini. (defaut: 0)
    'mpptName'=>'Chargeur PV', // : Personnalisation du nom du Chargeur PV.
    'batteryColor'=>'pink', // : Couleur par défaut des éléments de catégorie "batterie". [ Exemple : #fffff, white | Défaut : pink]
    'batteryStateColor'=>'pink', // : Couleur de l'état de charge et icône. [ Exemple : #fffff, white | Défaut : pink]
    'batteryChargeColor'=>'pink', // : Couleur des éléments si en charge. [ Exemple : #fffff, white | Défaut : pink]
    'batteryDischargeColor'=>'pink', // : Couleur des éléments si en décharge. [ Exemple : #fffff, white | Défaut : pink]
    'batteryIcon'=>'battery', // : Choix icône intègrée (Voir liste en bas) ou image perso :
    'batteryIconImage'=>'', // : Si batteryIcon est de type image, bien mettre le chemin jusqu'au fichier sans oublier l'extension.
    'autoColorBattery'=>1, // : Auto coloration de l'état de charge et icône en fonction de l'état de charge. [ 0 = désactivé | Défaut : 1 ])
    //------------ Aux ------------
    'auxColor'=>'#a43df5', // : Couleur des éléments de catégorie "aux" [ Exemple : #fffff, white]
    'auxMaxPower'=>0, // : Puissance max des "Aux". (permet la gestion, de la vitesse de l'animation, de la gauge et des alertes)
    //------------ Perso ------------
    'perso1Param'=>'', // : Paramètre pour afficher une commande perso (il faut obligatoirement avoir créé la commande perso1_state)
    'debug'=>0, // : paramètre pour passer les logs de la console en mode debug
    );
    return($return);
}
