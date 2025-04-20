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
        $this->checkAndCreateCommands();
    }
            
    public function postSave() {
        // Appeler la création des commandes pour l'ECU
        //$this->checkAndCreateCommands();
    
        $autorefresh = $this->getConfiguration('autorefresh', '');
        $id = $this->getId();
    
        $isEnable = false;
        try {
            $isEnable = $this->getIsEnable();
        } catch (Exception $e) {
            log::add('APSystemsSunspec', 'error', 'Erreur lors de l\'appel à getIsEnable : ' . $e->getMessage());
            return;
        }
    
        // Vérifier si l'équipement est un ECU (et non un micro-onduleur)
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
                        log::add('APSystemsSunspec', 'debug', "Max Power PV$i : $maxPower");
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
                            log::add('APSystemsSunspec', 'debug', 'Paramparent : ' . json_encode($paramParent));
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
                $cron->save();
                log::add('APSystemsSunspec', 'info', "Cron mis à jour pour l'équipement ID $id avec schedule : $autorefresh");
            } else {
                $cron->remove();
                log::add('APSystemsSunspec', 'info', "Cron supprimé pour l'équipement ID $id (autorefresh désactivé ou équipement désactivé)");
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
    
    public function checkAndCreateCommands() {
        
        $checked = $GLOBALS['checked'] ?? false; // Initialize $checked if not already set
        $displayParam = displayParamsAPS();
        
        if (strpos($this->getLogicalId(), '_ID') === false) { // Si c'est un ECU

            // Commande pour l'application du widget'
            $order = 1;
            $stateCmd = $this->getCmd(null, 'widget');
            $newCmd = false;
            if (!is_object($stateCmd)) {
                $stateCmd = new APSystemsSunspecCmd();
                $newCmd = true;
            }

            if ($newCmd || !$checked) {
                $stateCmd->setName(__('Widget', __FILE__));
            }
            $parameters = $stateCmd->getDisplay('parameters');
            if (!is_array($parameters)) {
                foreach ($parameters as $key => $value) {
                    $displayParam[$key] = $value;
                }
            }
            $stateCmd->setEqLogic_id($this->getId());
            $stateCmd->setLogicalId('widget');
            $stateCmd->setType('info');
            $stateCmd->setSubType('string');
            $stateCmd->setConfiguration('widget', '');
            $stateCmd->setTemplate('dashboard', 'APSystemsSunspec::distribution_onduleur_APSystemsSunspec');
            $stateCmd->setDisplay('parameters', $displayParam);
            $stateCmd->setOrder($order);
            $stateCmd->save();

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

            // Commande pour l'état global de l'ECU
            $order++;
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
            $stateCmd->setConfiguration('widget', '');
            $stateCmd->setOrder($order);
            $stateCmd->save();
            
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

            // Créer les commandes pour chaque micro-onduleur (puissance, énergie, état)
            $children = eqLogic::byTypeAndSearchConfiguration('APSystemsSunspec', array('parent_id' => $this->getId()));
            $order++; // Commencer après les commandes principales (state, power, totalEnergy)

            foreach ($children as $child) {
                $modbusId = $child->getConfiguration('modbus_id');
                if (!is_numeric($modbusId)) {
                    log::add('APSystemsSunspec', 'error', "ID Modbus invalide pour l'enfant avec logicalId : " . $child->getLogicalId());
                    continue;
                }

                // Commande pour la puissance du micro-onduleur
                $this->createCommand(
                    "power_mo_$modbusId",
                    "Puissance MO $modbusId",
                    'info',
                    'numeric',
                    'W',
                    0, // Pas de registre, car c'est une valeur calculée
                    '', // Pas de calcul spécifique
                    1,
                    $order,
                    0, // Pas de coefficient
                    1, // Visible
                    'pv' . $modbusId . '_power' // Widget spécifique
                );
                $order++;

                // Commande pour l'énergie du micro-onduleur
                $this->createCommand(
                    "energy_mo_$modbusId",
                    "Énergie MO $modbusId",
                    'info',
                    'numeric',
                    'Wh',
                    0,
                    '',
                    1,
                    $order,
                    0, // Pas de coefficient
                    1, // Visible
                    'pv' . $modbusId . '_energy' // Widget spécifique
                );
                $order++;

                // Commande pour l'état du micro-onduleur
                $this->createCommand(
                    "state_mo_$modbusId",
                    "État MO $modbusId",
                    'info',
                    'binary',
                    '',
                    0,
                    '',
                    1,
                    $order
                );
                $order++;
            }

            log::add('APSystemsSunspec', 'info', 'Commandes créées pour l\'équipement ECU ID : ' . $this->getId());
        } else {
            // Si c'est un micro-onduleur
            // Commande pour l'état
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
            $stateCmdWid = $this->getCmd(null, 'widget');
            if (!is_object($stateCmdWid)) {
                $stateCmdWid = new APSystemsSunspecCmd();
                $newCmd = true;
            }
            if ($newCmd || !$checked) {
                $stateCmdWid->setName(__('Widget', __FILE__));
            }
            $parameters = $stateCmd->getDisplay('parameters');
            if (!is_array($parameters)) {
                foreach ($parameters as $key => $value) {
                    $displayParam[$key] = $value;
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


            log::add('APSystemsSunspec', 'info', 'Commandes créées pour le micro-onduleur ID : ' . $this->getId());
        }
    }

    public function createCommand($logicalId, $name, $type, $subType, $unit = '', $registre = 0, $calcul = '', $size = 1, $order = 1, $coef = 0, $isVisible = 1, $widget = '') {
        $cmd = $this->getCmd(null, $logicalId);
        $newCmd = false;
        if (!is_object($cmd)) {
            log::add('APSystemsSunspec', 'debug', 'Création de la commande : ' . $name);
            $cmd = new APSystemsSunspecCmd();
            $cmd->setLogicalId($logicalId);
            $newCmd = true;
        } else {
            log::add('APSystemsSunspec', 'debug', 'Mise à jour de la commande : ' . $name);
        }
        $checked = $GLOBALS['checked'] ?? false; // Initialize $checked if not already set
        if (!$checked || $newCmd) {
            $cmd->setName($name);
        }
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType($type);
        $cmd->setSubType($subType);
        $cmd->setConfiguration('registre', $registre);
        $cmd->setConfiguration('calcul', $calcul);
        $cmd->setConfiguration('size', $size);
        $cmd->setConfiguration('widget', $widget);
        $cmd->setIsVisible($isVisible);
        $cmd->setOrder($order);
        $cmd->setConfiguration('coef', $coef);
        if ($unit) {
            $cmd->setUnite($unit);
        }
        $cmd->save();
    }

    public function majCoef() {
        log::add('APSystemsSunspec', 'info', 'Mise à jour des coefficients');
        $ip = $this->getConfiguration('ip');
        $timeout = $this->getConfiguration('timeout', 3);
        $modbusId = $this->getConfiguration('modbus_id', 1);

        try {
            $client = new ModbusClient($ip, 502, $timeout);
            $client->setSlave($modbusId);

            $data = [];
            $block1 = $client->readHoldingRegisters(40076, 1);
            if ($block1 === false || empty($block1)) {
                throw new Exception("Aucune donnée reçue pour le registre 40076 (coefA)");
            }
            $data[40076 - 40076] = $block1[0];

            $block2 = $client->readHoldingRegisters(40083, 11);
            if ($block2 === false || empty($block2)) {
                throw new Exception("Aucune donnée reçue pour les registres 40083 à 40093");
            }
            for ($i = 0; $i < 11; $i++) {
                $data[40083 - 40076 + $i] = $block2[$i];
            }

            $block3 = $client->readHoldingRegisters(40096, 1);
            if ($block3 === false || empty($block3)) {
                throw new Exception("Aucune donnée reçue pour le registre 40096 (coefE)");
            }
            $data[40096 - 40076] = $block3[0];

            $block4 = $client->readHoldingRegisters(40107, 1);
            if ($block4 === false || empty($block4)) {
                throw new Exception("Aucune donnée reçue pour le registre 40107 (coefTemp)");
            }
            $data[40107 - 40076] = $block4[0];

            $client->close();

            $coefA = $data[0];
            if ($coefA > 32767) {
                $coefA = $coefA - 65536;
            }
            $coefV = $data[7];
            if ($coefV > 32767) {
                $coefV = $coefV - 65536;
            }
            $coefP = $data[9];
            if ($coefP > 32767) {
                $coefP = $coefP - 65536;
            }
            $coefF = $data[11];
            if ($coefF > 32767) {
                $coefF = $coefF - 65536;
            }
            $coefVA = $data[13];
            if ($coefVA > 32767) {
                $coefVA = $coefVA - 65536;
            }
            $coefVAR = $data[15];
            if ($coefVAR > 32767) {
                $coefVAR = $coefVAR - 65536;
            }
            $coefPF = $data[17];
            if ($coefPF > 32767) {
                $coefPF = $coefPF - 65536;
            }
            $coefE = $data[20];
            if ($coefE > 32767) {
                $coefE = $coefE - 65536;
            }
            $coefTemp = $data[31];
            if ($coefTemp > 32767) {
                $coefTemp = $coefTemp - 65536;
            }

            return array(
                'coefA' => $coefA,
                'coefV' => $coefV,
                'coefP' => $coefP,
                'coefF' => $coefF,
                'coefVA' => $coefVA,
                'coefVAR' => $coefVAR,
                'coefPF' => $coefPF,
                'coefE' => $coefE,
                'coefTemp' => $coefTemp
            );
        } catch (Exception $e) {
            if (isset($client)) {
                $client->close();
            }
            log::add('APSystemsSunspec', 'error', "Erreur lors de la lecture des coefficients : " . $e->getMessage());
            return array(
                'coefA' => 0,
                'coefV' => 0,
                'coefP' => 0,
                'coefF' => 0,
                'coefVA' => 0,
                'coefVAR' => 0,
                'coefPF' => 0,
                'coefE' => 0,
                'coefTemp' => 0
            );
        }
    }

    public function checkAndCreateCommandsMO($type, $pv = 4) {
        $order2 = 500;
        $this->createCommand('coefA', 'Coefficient Intensité', 'info', 'numeric', '', 40076, 'int16', 1, $order2);
        $order2++;
        $this->createCommand('coefV', 'Coefficient Tension', 'info', 'numeric', '', 40083, 'int16', 1, $order2);
        $order2++;
        $this->createCommand('coefP', 'Coefficient Puissance', 'info', 'numeric', '', 40085, 'int16', 1, $order2);
        $order2++;
        $this->createCommand('coefF', 'Coefficient Fréquence', 'info', 'numeric', '', 40087, 'int16', 1, $order2);
        $order2++;
        $this->createCommand('coefVA', 'Coefficient VA', 'info', 'numeric', '', 40089, 'int16', 1, $order2);
        $order2++;
        $this->createCommand('coefVAR', 'Coefficient VAR', 'info', 'numeric', '', 40091, 'int16', 1, $order2);
        $order2++;
        $this->createCommand('coefPF', 'Coefficient Facteur de Puissance', 'info', 'numeric', '', 40093, 'int16', 1, $order2);
        $order2++;
        $this->createCommand('coefE', 'Coefficient Énergie', 'info', 'numeric', '', 40096, 'int16', 1, $order2);
        $order2++;
        $this->createCommand('coefTemp', 'Coefficient Température', 'info', 'numeric', '', 40107, 'int16', 1, $order2);

        $coef = $this->majCoef();
        if ($coef === null) {
            log::add('APSystemsSunspec', 'error', "Échec de la récupération des coefficients, utilisation des valeurs par défaut.");
            $coef = array(
                'coefA' => 0,
                'coefV' => 0,
                'coefP' => 0,
                'coefF' => 0,
                'coefVA' => 0,
                'coefVAR' => 0,
                'coefPF' => 0,
                'coefE' => 0,
                'coefTemp' => 0
            );
        }
        log::add('APSystemsSunspec', 'info', "Coefficients mis à jour : A={$coef['coefA']}, V={$coef['coefV']}, P={$coef['coefP']}, F={$coef['coefF']}, VA={$coef['coefVA']}, VAR={$coef['coefVAR']}, PF={$coef['coefPF']}, E={$coef['coefE']}, Temp={$coef['coefTemp']}");

        $order = 3;
        $this->createCommand('ID', 'id', 'info', 'numeric', '', 40002, 'uint16', 1, $order);
        $order++;
        $this->createCommand('length', 'Model Length', 'info', 'numeric', '', 40003, 'uint16', 2, $order);
        $order++;
        $this->createCommand('manufacturer', 'Constructeur', 'info', 'string', '', 40004, 'string', 16, $order);
        $order++;
        $this->createCommand('model', 'Modèle', 'info', 'string', '', 40020, 'string', 16, $order);
        $order++;
        $this->createCommand('version', 'Version', 'info', 'string', '', 40044, 'string', 8, $order);
        $order++;
        $this->createCommand('serial', 'Numéro de série', 'info', 'string', '', 40052, 'string', 16, $order);
        $order++;
        $this->createCommand('device_address', 'Adresse Modbus', 'info', 'numeric', '', 40068, 'uint16', 1, $order);
        $order++;
        $this->createCommand('id_ph', 'Modèle (101 = mono, 103 = tri)', 'info', 'numeric', '', 40070, 'uint16', 1, $order);
        $order++;
        $this->createCommand('nb_reg_ph', 'Nombre de registres', 'info', 'numeric', '', 40071, 'uint16', 1, $order);
        $order++;
        $this->createCommand('amps', 'Courant', 'info', 'numeric', 'A', 40072, 'uint16', 1, $order, $coef['coefA']);
        $order++;
        if ($type == 'triphasé') {
            $this->createCommand('ampsph1', 'Courant phase 1', 'info', 'numeric', 'A', 40073, 'uint16', 1, $order, $coef['coefA']);
            $order++;
            $this->createCommand('ampsph2', 'Courant phase 2', 'info', 'numeric', 'A', 40074, 'uint16', 1, $order, $coef['coefA']);
            $order++;
            $this->createCommand('ampsph3', 'Courant phase 3', 'info', 'numeric', 'A', 40075, 'uint16', 1, $order, $coef['coefA']);
            $order++;
            $this->createCommand('vph1ph2', 'Tension ph1/ph2', 'info', 'numeric', 'V', 40077, 'uint16', 1, $order, $coef['coefV']);
            $order++;
            $this->createCommand('vph2ph3', 'Tension ph2/ph3', 'info', 'numeric', 'V', 40078, 'uint16', 1, $order, $coef['coefV']);
            $order++;
            $this->createCommand('vph3ph1', 'Tension ph3/ph1', 'info', 'numeric', 'V', 40079, 'uint16', 1, $order, $coef['coefV']);
            $order++;
        }
        $this->createCommand('vph1', 'Tension ph1', 'info', 'numeric', 'V', 40080, 'uint16', 1, $order, $coef['coefV']);
        $order++;
        if ($type == 'triphasé') {
            $this->createCommand('vph2', 'Tension ph2', 'info', 'numeric', 'V', 40081, 'uint16', 1, $order, $coef['coefV']);
            $order++;
            $this->createCommand('vph3', 'Tension ph3', 'info', 'numeric', 'V', 40082, 'uint16', 1, $order, $coef['coefV']);
            $order++;
        }
        $this->createCommand('power', 'Puissance', 'info', 'numeric', 'W', 40084, 'int16', 1, $order, $coef['coefP'], 1, 'pv_power');
        $order++;
        $this->createCommand('frequency', 'Fréquence', 'info', 'numeric', 'Hz', 40086, 'int16', 1, $order, $coef['coefF']);
        $order++;
        $this->createCommand('va', 'Puissance apparente', 'info', 'numeric', 'VA', 40088, 'int16', 1, $order, $coef['coefVA']);
        $order++;
        $this->createCommand('var', 'Puissance réactive', 'info', 'numeric', 'VAR', 40090, 'int16', 1, $order, $coef['coefVAR']);
        $order++;
        $this->createCommand('power_factor', 'Facteur de puissance', 'info', 'numeric', '', 40092, 'int16', 1, $order, $coef['coefPF']);
        $order++;
        $this->createCommand('energy', 'Énergie', 'info', 'numeric', 'Wh', 40094, 'acc32', 2, $order, $coef['coefE'], 1, 'daily_solar');
        $order++;
        $this->createCommand('cabinet_temp', 'Température du boîtier', 'info', 'numeric', '°C', 40103, 'int16', 1, $order, $coef['coefTemp']);
        $order++;
        $this->createCommand('operating_state', 'État de fonctionnement', 'info', 'string', '', 40108, 'enum16', 1, $order);
        $order++;
        $this->createCommand('event1', 'Événement 1', 'info', 'string', '', 40110, 'bitfield32', 2, $order);
        $order++;
        $this->createCommand('id_float', 'ID float (111 = mono, 113 = tri)', 'info', 'numeric', '', 40122, 'uint16', 1, $order);
        $order++;
        $this->createCommand('nb_reg_float', 'Nombre de registres pour float32', 'info', 'numeric', '', 40123, 'uint16', 1, $order);
        $order++;
        $this->createCommand('amps_float', 'Courant float', 'info', 'numeric', 'A', 40124, 'float32', 2, $order);
        $order++;
        $this->createCommand('ampsph1_float', 'Courant phase 1 float', 'info', 'numeric', 'A', 40126, 'float32', 2, $order);
        $order++;
        if ($type == 'triphasé') {
            $this->createCommand('ampsph2_float', 'Courant phase 2 float', 'info', 'numeric', 'A', 40128, 'float32', 2, $order);
            $order++;
            $this->createCommand('ampsph3_float', 'Courant phase 3 float', 'info', 'numeric', 'A', 40130, 'float32', 2, $order);
            $order++;
            $this->createCommand('vph1ph2_float', 'Tension ph1/ph2 float', 'info', 'numeric', 'V', 40132, 'float32', 2, $order);
            $order++;
            $this->createCommand('vph2ph3_float', 'Tension ph2/ph3 float', 'info', 'numeric', 'V', 40134, 'float32', 2, $order);
            $order++;
            $this->createCommand('vph3ph1_float', 'Tension ph3/ph1 float', 'info', 'numeric', 'V', 40136, 'float32', 2, $order);
            $order++;
        }
        $this->createCommand('vph1_float', 'Tension ph1 float', 'info', 'numeric', 'V', 40138, 'float32', 2, $order);
        $order++;
        if ($type == 'triphasé') {
            $this->createCommand('vph2_float', 'Tension ph2 float', 'info', 'numeric', 'V', 40140, 'float32', 2, $order);
            $order++;
            $this->createCommand('vph3_float', 'Tension ph3 float', 'info', 'numeric', 'V', 40142, 'float32', 2, $order);
            $order++;
        }
        $this->createCommand('power_float', 'Puissance float', 'info', 'numeric', 'W', 40144, 'float32', 2, $order);
        $order++;
        $this->createCommand('frequency_float', 'Fréquence float', 'info', 'numeric', 'Hz', 40146, 'float32', 2, $order);
        $order++;
        $this->createCommand('va_float', 'Puissance apparente float', 'info', 'numeric', 'VA', 40148, 'float32', 2, $order);
        $order++;
        $this->createCommand('var_float', 'Puissance réactive float', 'info', 'numeric', 'VAR', 40150, 'float32', 2, $order);
        $order++;
        $this->createCommand('power_factor_float', 'Facteur de puissance float', 'info', 'numeric', '', 40152, 'float32', 2, $order);
        $order++;
        $this->createCommand('energy_float', 'Énergie float', 'info', 'numeric', 'Wh', 40154, 'float32', 2, $order);
        $order++;
        $this->createCommand('cabinet_temp_float', 'Température du boîtier float', 'info', 'numeric', '°C', 40162, 'float32', 2, $order);
        $order++;
        $this->createCommand('id_ic', 'ID contrôles', 'info', 'numeric', '°C', 40184, 'uint16', 1, $order);
        $order++;
        $this->createCommand('nb_reg_ic', 'Nombre de registres pour IC', 'info', 'numeric', '', 40185, 'uint16', 1, $order);
        $order++;
        $this->createCommand('connection_state', 'État de connexion', 'info', 'numeric', '', 40186, 'uint16', 1, $order);
        $order++;
        $this->createCommand('wmaxlimpct', 'Puissance max limite (en %)', 'info', 'numeric', '%', 40189, 'uint16', 1, $order, -1);
        $order++;
        $this->createCommand('wmaxlim_enabled', 'Limitation puissance activée (1= activée)', 'info', 'numeric', '', 40193, 'uint16', 1, $order);
        $order++;
        $this->createCommand('id_dcdata', 'ID DC data', 'info', 'numeric', '', 40212, 'uint16', 1, $order);
        $order++;
        $this->createCommand('nb_reg_dcdata', 'Nombre de registres pour DC data', 'info', 'numeric', '', 40213, 'uint16', 1, $order);
        if ($pv >= 1){
            $order++;
            $this->createCommand('dc_voltage_dcv1', 'Tension DC PV1', 'info', 'numeric', 'V', 40214, 'float32', 2, $order, 0, 1, 'pv1_voltage');
            $order++;
            $this->createCommand('dc_current_dcv1', 'Courant DC PV1', 'info', 'numeric', 'A', 40230, 'float32', 2, $order, 0, 1, 'pv1_current');
            $order++;
            $this->createCommand('dc_power_dcv1', 'Puissance DC PV1', 'info', 'numeric', 'W', 40246, 'float32', 2, $order, 0, 1, 'pv1_power');
        }
        if ($pv >= 2){
            $order++;
            $this->createCommand('dc_voltage_dcv2', 'Tension DC PV2', 'info', 'numeric', 'V', 40216, 'float32', 2, $order, 0, 1, 'pv2_voltage');
            $order++;
            $this->createCommand('dc_current_dcv2', 'Courant DC PV2', 'info', 'numeric', 'A', 40232, 'float32', 2, $order, 0, 1, 'pv2_current');
            $order++;
            $this->createCommand('dc_power_dcv2', 'Puissance DC PV2', 'info', 'numeric', 'W', 40248, 'float32', 2, $order, 0, 1, 'pv2_power');
        }
        if ($pv >= 3){
            $order++;
            $this->createCommand('dc_voltage_dcv3', 'Tension DC PV3', 'info', 'numeric', 'V', 40218, 'float32', 2, $order, 0, 1, 'pv3_voltage');
            $order++;
            $this->createCommand('dc_current_dcv3', 'Courant DC PV3', 'info', 'numeric', 'A', 40234, 'float32', 2, $order, 0, 1, 'pv3_current');
            $order++;
            $this->createCommand('dc_power_dcv3', 'Puissance DC PV3', 'info', 'numeric', 'W', 40250, 'float32', 2, $order, 0, 1, 'pv3_power');
        }
        if ($pv >= 4){
            $order++;
            $this->createCommand('dc_voltage_dcv4', 'Tension DC PV4', 'info', 'numeric', 'V', 40220, 'float32', 2, $order, 0, 1, 'pv4_voltage');
            $order++;
            $this->createCommand('dc_current_dcv4', 'Courant DC PV4', 'info', 'numeric', 'A', 40236, 'float32', 2, $order, 0, 1, 'pv4_current');
            $order++;
            $this->createCommand('dc_power_dcv4', 'Puissance DC PV4', 'info', 'numeric', 'W', 40252, 'float32', 2, $order, 0, 1, 'pv4_power');
        }
        if ($pv >= 5){
            $order++;
            $this->createCommand('dc_voltage_dcv5', 'Tension DC PV5', 'info', 'numeric', 'V', 40222, 'float32', 2, $order, 0, 1, 'pv5_voltage');
            $order++;
            $this->createCommand('dc_current_dcv5', 'Courant DC PV5', 'info', 'numeric', 'A', 40238, 'float32', 2, $order, 0, 1, 'pv5_current');
            $order++;
            $this->createCommand('dc_power_dcv5', 'Puissance DC PV5', 'info', 'numeric', 'W', 40254, 'float32', 2, $order, 0, 1, 'pv5_power');
        }
        if ($pv >= 6){
            $order++;
            $this->createCommand('dc_voltage_dcv6', 'Tension DC PV6', 'info', 'numeric', 'V', 40224, 'float32', 2, $order, 0, 1, 'pv6_voltage');
            $order++;
            $this->createCommand('dc_current_dcv6', 'Courant DC PV6', 'info', 'numeric', 'A', 40240, 'float32', 2, $order, 0, 1, 'pv6_current');
            $order++;
            $this->createCommand('dc_power_dcv6', 'Puissance DC PV6', 'info', 'numeric', 'W', 40256, 'float32', 2, $order, 0, 1, 'pv6_power');
        }
        if ($pv >= 7){
            $order++;
            $this->createCommand('dc_voltage_dcv7', 'Tension DC PV7', 'info', 'numeric', 'V', 40226, 'float32', 2, $order, 0, 1, 'pv7_voltage');
            $order++;
            $this->createCommand('dc_current_dcv7', 'Courant DC PV7', 'info', 'numeric', 'A', 40242, 'float32', 2, $order, 0, 1, 'pv7_current');
            $order++;
            $this->createCommand('dc_power_dcv7', 'Puissance DC PV7', 'info', 'numeric', 'W', 40258, 'float32', 2, $order, 0, 1, 'pv7_power');
        }
        if ($pv >= 8){
            $order++;
            $this->createCommand('dc_voltage_dcv8', 'Tension DC PV8', 'info', 'numeric', 'V', 40228, 'float32', 2, $order, 0, 1, 'pv8_voltage');
            $order++;
            $this->createCommand('dc_current_dcv8', 'Courant DC PV8', 'info', 'numeric', 'A', 40244, 'float32', 2, $order, 0, 1, 'pv8_current');
            $order++;
            $this->createCommand('dc_power_dcv8', 'Puissance DC PV8', 'info', 'numeric', 'W', 40260, 'float32', 2, $order, 0, 1, 'pv8_power');
        }
    }

    public function refreshData() {
        log::add('APSystemsSunspec', 'info', "Rafraîchissement manuel des données pour l'équipement : " . $this->getName() . " (ID : " . $this->getId() . ")");
        $this->getECUData(); // Réutiliser la logique existante
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

    public function scanMicroInverters($objectId = null, $ifChecked = false, $unique = false, $uniqueId = null) {
        $ip = $this->getLogicalId();
        $timeout = $this->getConfiguration('timeout', 3);
        $modbusId = 1;
        $maxAttempts = 247;
        $order = $this->getOrder();
        $GLOBALS['checked'] = $ifChecked;
        $unique = $unique? true : false;
        log::add('APSystemsSunspec', 'info', "test : " . $unique . " - " . $uniqueId);
        if ($unique === true) {
            $modbusId = $uniqueId;
            $maxAttempts = $uniqueId;
            log::add('APSystemsSunspec', 'info', "Scan unique pour IP : $ip (ID équipement : " . $this->getId() . ")" . " - Modbus ID : $modbusId");
        } else {
            log::add('APSystemsSunspec', 'info', "Début du scan pour IP : $ip (ID équipement : " . $this->getId() . ")");
        }
        while ($modbusId <= $maxAttempts) {
            try {
                // Premier appel à queryModbus pour le registre 40070
                $response = $this->queryModbus($ip, $modbusId, 40070, $timeout);
                log::add('APSystemsSunspec', 'debug', "Valeur exacte de \$response pour Modbus ID $modbusId (registre 40070) : " . var_export($response, true));

                if ($response === false) {
                    if ($unique == true) {
                        log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId (registre 40070), fin du scan");
                        break;
                    } else {
                        log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId (registre 40070), vérification de 'trou' dans la suite des MO déjà enregistrés");
                        $eqLogics = eqLogic::byType('APSystemsSunspec');
                        foreach ($eqLogics as $eqLogic) {
                            $logicalId = $eqLogic->getLogicalId();
                            $modbusIdToScan = $eqLogic->getConfiguration('modbusId');
                            if (strpos($logicalId, $ip) && strpos($logicalId, '_ID') && ($modbusIdToScan > $modbusId)) {
                                $modbusId = $modbusIdToScan;
                                log::add('APSystemsSunspec', 'debug', "'Trou' trouvé pour Modbus ID $modbusId, on continue le scan");
                                break;
                            }
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

                    log::add('APSystemsSunspec', 'debug', "Valeur exacte de \$modelData pour Modbus ID $modbusId (registre 40020) : " . var_export($modelData, true));
        
                    if ($modelData === false) {
                        if ($unique == true) {
                            log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId (registre 40020), fin du scan");
                            break;
                        } else {
                            log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId (registre 40020), vérification de 'trou' dans la suite des MO déjà enregistrés");
                            $eqLogics = eqLogic::byType('APSystemsSunspec');
                            foreach ($eqLogics as $eqLogic) {
                                $logicalId = $eqLogic->getLogicalId();
                                $modbusIdToScan = $eqLogic->getConfiguration('modbusId');
                                if (strpos($logicalId, $ip) && strpos($logicalId, '_ID') && ($modbusIdToScan > $modbusId)) {
                                    $modbusId = $modbusIdToScan;
                                    log::add('APSystemsSunspec', 'debug', "'Trou' trouvé pour Modbus ID $modbusId, on continue le scan");
                                    break;
                                }
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
            $eqLogic->getECUData();
            log::add('APSystemsSunspec', 'info', "Mise à jour des données terminée pour l'équipement : $name (ID : $eqLogicId)");
        } catch (Exception $e) {
            log::add('APSystemsSunspec', 'error', "Erreur lors de la mise à jour des données pour l'équipement $name (ID : $eqLogicId) : " . $e->getMessage());
        }
    }

    // Méthode pour vérifier si l'interrogation est désactivée
    private function isPollingDisabled() {
        $startTime = $this->getConfiguration('stopPollingStart', '');
        $endTime = $this->getConfiguration('stopPollingEnd', '');

        // Si les paramètres ne sont pas définis, l'interrogation n'est pas désactivée
        if (empty($startTime) || empty($endTime)) {
            return false;
        }

        // Vérifier le format des heures (HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $startTime) ||
            !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $endTime)) {
            log::add('APSystemsSunspec', 'error', "Format invalide pour stopPollingStart ($startTime) ou stopPollingEnd ($endTime). Utilisez le format HH:MM.");
            return false;
        }

        // Obtenir l'heure actuelle
        $currentTime = new DateTime('now', new DateTimeZone('Europe/Paris')); // Ajustez le fuseau horaire selon vos besoins
        $currentHourMinute = $currentTime->format('H:i');

        // Convertir les heures en minutes pour une comparaison plus facile
        $startMinutes = (intval(substr($startTime, 0, 2)) * 60) + intval(substr($startTime, 3, 2));
        $endMinutes = (intval(substr($endTime, 0, 2)) * 60) + intval(substr($endTime, 3, 2));
        $currentMinutes = (intval(substr($currentHourMinute, 0, 2)) * 60) + intval(substr($currentHourMinute, 3, 2));

        // Cas où la période d'arrêt chevauche minuit (ex. 22:00 à 06:00)
        if ($startMinutes > $endMinutes) {
            // La période d'arrêt va de startTime à minuit, puis de minuit à endTime
            if ($currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes) {
                log::add('APSystemsSunspec', 'debug', "Interrogation désactivée : heure actuelle ($currentHourMinute) dans la plage $startTime à $endTime (chevauche minuit).");
                return true;
            }
        } else {
            // Cas normal (ex. 01:00 à 05:00)
            if ($currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes) {
                log::add('APSystemsSunspec', 'debug', "Interrogation désactivée : heure actuelle ($currentHourMinute) dans la plage $startTime à $endTime.");
                return true;
            }
        }

        return false;
    }

    public function getECUData() {
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
                $this->updateChildCommands($child, $data);

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
        $totalEnergy = round($totalEnergy, 2);

        // Mettre à jour les commandes de l'ECU parent
        $this->checkAndUpdateCmd('power', $totalPower);
        $this->checkAndUpdateCmd('totalEnergy', $totalEnergy);
        $this->checkAndUpdateCmd('state', $totalPower > 0 ? 1 : 0);
        $this->refreshWidget();

        return $success;
    }

    private function updateChildCommands($child, $data) {
        $commands = $child->getCmd('info');
        if (empty($commands)) {
            log::add('APSystemsSunspec', 'warning', "Aucune commande de type 'info' trouvée pour l'enfant avec logicalId : " . $child->getLogicalId());
            return;
        }

        foreach ($commands as $cmd) {
            $registre = $cmd->getConfiguration('registre', 0);
            $size = $cmd->getConfiguration('size', 1);
            $calcul = $cmd->getConfiguration('calcul', '');
            $coef = $cmd->getConfiguration('coef', 0);
            //$nameCmd = $cmd->getName();
            $logicalId = $cmd->getLogicalId();

            if ($registre < 40002 || $registre + $size - 1 > 40261) {
                log::add('APSystemsSunspec', 'debug', "Registre $registre hors plage pour la commande {$cmd->getLogicalId()} (taille : $size)");
                continue;
            }

            $index = $registre - 40002;

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
                $existingEqLogic->checkAndCreateCommands();
            } else {
                log::add('APSystemsSunspec', 'error', "L'équipement existingEqlogic n'est pas une instance d'APSystemsSunspec. Impossible d'appeler checkAndCreateCommandsMO.");
            }
            
            $existingEqLogic->save();
            // Mettre à jour les commandes de l'ECU parent après la création de l'enfant
            $parent = eqLogic::byId($this->getId());
            if (is_object($parent)) {
                if ($parent instanceof APSystemsSunspec) {
                    $parent->checkAndCreateCommands();
                } else {
                    log::add('APSystemsSunspec', 'error', "Parent n'est pas une instance d'APSystemsSunspec. Impossible d'appeler checkAndCreateCommands.");
                }
            }
            return;
        }
        $newEqLogic = new APSystemsSunspec();
        $newEqLogic->setName($this->getName() . ' ID ' . $modbusId . ' (' . $type . ')');
        $newEqLogic->setLogicalId($ip . '_ID' . $modbusId);
        $newEqLogic->setEqType_name('APSystemsSunspec');
        $newEqLogic->setConfiguration('parent_id', $this->getId());
        $newEqLogic->setConfiguration('ip', $ip);
        $newEqLogic->setConfiguration('modbus_id', $modbusId);
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
            $success = $client->writeSingleRegister($register, $value);

            if ($success) {
                log::add('APSystemsSunspec', 'info', "Écriture réussie : registre $register, valeur $value pour Modbus ID $modbusId");
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
        if ($this->getLogicalId() == 'refresh') {
            $eqLogic = $this->getEqLogic();
            $eqLogic->refreshData();
        } elseif ($this->getLogicalId() == 'set_value') {
            $eqLogic = $this->getEqLogic();
            $value = isset($_options['slider']) ? intval($_options['slider']) : 0;
            $eqLogic->setParameter(40071, $value);
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
    'inverterColor'=>'', // : Couleur des éléments de catégorie "onduleur" [ Exemple : #fffff, white]
    'inverterColorTextIn'=>'', // : Couleur des textes internes a l'onduleur [ Exemple : #fffff, white]
    'noGridColor'=> '', // : Couleur du logo "noGridColor" [ Exemple : #fffff, white]
    'colorDanger'=>'red', // : Couleur des rectangles en cas de dépassement de puissance. (Défaut : red)
    'blink'=>0, // : Active le clignotement du rectangle en cas de dépassement de puissance. (Défaut: 0)
    'fontAlert'=>0, // : Applique la couleur 'colorDanger' au texte en cas de dépassement de puissance. (Défaut: 0)
    'activateGauge'=>1, // : Active les gauges dans les rectangles. Pas oublier de renseigner les MaxPower (loadMaxPower, load1MaxPower, pv1Maxpower...) [ Défaut : 1 ]
    'activateShadow'=>0, // : Active un shadow sur les textes dans les rectangles.(Permet d'avoir un contraste lors de l'utilisation des gauges) [ Défaut : 0 ]
    'shadowStyle'=>'2px 2px 3px black', // : Style du shadow. [ défaut : 2px 2px 3px black ]
    //------------ Solar ------------
    'pv1Name'=>'PV1', // : Personnalisation du nom du Pv1. (Ex: Ouest, Nord, PV1 ...)
    'pv1MaxPower'=>0, // : Puissance max du Pv1. (permet la gestion de la gauge et des alertes)
    'pv2Name'=>'PV2', // : Personnalisation du nom du Pv2. (Ex: Ouest, Nord, PV2 ...)
    'pv2MaxPower'=>0, // : Puissance max du Pv2. (permet la gestion de la gauge et des alertes)
    'pv3Name'=>'PV3', // : Personnalisation du nom du Pv3. (Ex: Ouest, Nord, PV3 ...)
    'pv3MaxPower'=>0, // : Puissance max du Pv3. (permet la gestion de la gauge et des alertes)
    'pv4Name'=>'PV4', // : Personnalisation du nom du Pv4. (Ex: Ouest, Nord, PV4 ...)
    'pv4MaxPower'=>0, // : Puissance max du Pv4. (permet la gestion de la gauge et des alertes)
    'pv5Name'=>'PV5', // : Personnalisation du nom du Pv5. (Ex: Ouest, Nord, PV5 ...)
    'pv5MaxPower'=>0, // : Puissance max du Pv5. (permet la gestion de la gauge et des alertes)
    'pv6Name'=>'PV6', // : Personnalisation du nom du Pv6. (Ex: Ouest, Nord, PV6 ...)
    'pv6MaxPower'=>0, // : Puissance max du Pv6. (permet la gestion de la gauge et des alertes)
    'pv7Name'=>'PV7', // : Personnalisation du nom du Pv7. (Ex: Ouest, Nord, PV7 ...)
    'pv7MaxPower'=>0, // : Puissance max du Pv7. (permet la gestion de la gauge et des alertes)
    'pv8Name'=>'PV8', // : Personnalisation du nom du Pv8. (Ex: Ouest, Nord, PV8 ...)
    'pv8MaxPower'=>0, // : Puissance max du Pv8. (permet la gestion de la gauge et des alertes)
    'dailySolarText'=>'DAILY SOLAR', // : Personnalisation du texte. (défaut : DAILY SOLAR);
    'pvMaxPower'=>0, // : Puissance max des PV. (permet la gestion, de la vitesse de l'animation, de la gauge et des alertes)
    'solarColor'=>'', // : Couleur des éléments de catégorie "solaire" [ Exemple : #fffff, white]
    'pvState0Color'=>'', // : Couleur des éléments du pv si pas de production [ Exemple : #fffff, white | défaut : #solarColor#]
    //------------ Load ------------
    'load1Name'=>'Load1', // : Personnalisation du nom du Load1. (Ex: C.E, Clim, ...)
    'load1Icon'=>'', // : Choix icône intègrée : Voir liste en bas.
    'load1maxPower'=>0, // : Puissance Max (permet la gestion de la gauge et des alertes)
    'load2Name'=>'Load2', // : Personnalisation du nom du Load2. (Ex: C.E, Clim, ...)
    'load2Icon'=>'', // : Choix icône intègrée : Voir liste en bas.
    'load2maxPower'=>0, // : Puissance Max (permet la gestion de la gauge et des alertes)
    'load3Name'=>'Load3', // : Personnalisation du nom du Load3. (Ex: C.E, Clim, ...)
    'load3Icon'=>'', // : Choix icône intègrée : Voir liste en bas.
    'load3maxPower'=>0, // : Puissance Max (permet la gestion de la gauge et des alertes)
    'load4Name'=>'Load4', // : Personnalisation du nom du Load4. (Ex: C.E, Clim, ...)
    'load4Icon'=>'', // : Choix icône intègrée : Voir liste en bas.
    'load4maxPower'=>0, // : Puissance Max (permet la gestion de la gauge et des alertes)
    'force4Load'=>0, // : Force a 4 loads par colonne. (désactivé si utilisation de 7 PV ou plus !) [défaut : 0]
    'dailyLoadText'=>'DAILY LOAD', // : Personnalisation du texte. (défaut : DAILY LOAD)
    'loadMaxPower'=>0, // : Puissance max des équipements "Load". (permet la gestion, de la vitesse de l'animation, de la gauge et des alertes)
    'loadColor'=>'', // : Couleur des éléments de catégorie "load" [ Exemple : #fffff, white]
    'loadAnimate'=>1, // : Pour désactiver l'animation des Load passer ce paramètre a 0
    'activateGaugeRatio'=>1, // : Passer ce paramètre a 0 pour désactiver la gauge ratio.
    'sizeGaugeRatio'=>4, // : Taille de la gauge ratio. [ 1 a 10 | défaut : 4]
    //------------ Grid ------------
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
    'batteryIcon'=>'', // : Choix icône intègrée (Voir liste en bas) ou image perso :
    'batteryIconImage'=>'', // : Si batteryIcon est de type image, bien mettre le chemin jusqu'au fichier sans oublier l'extension.
    'autoColorBattery'=>1, // : Auto coloration de l'état de charge et icône en fonction de l'état de charge. [ 0 = désactivé | Défaut : 1 ])
    //------------ Aux ------------
    'auxColor'=>'', // : Couleur des éléments de catégorie "aux" [ Exemple : #fffff, white]
    'auxMaxPower'=>0, // : Puissance max des "Aux". (permet la gestion, de la vitesse de l'animation, de la gauge et des alertes)
    //------------ Perso ------------
    'perso1Param'=>'', // : Paramètre pour afficher une commande perso (il faut obligatoirement avoir créé la commande perso1_state)
    );
    return($return);
}
