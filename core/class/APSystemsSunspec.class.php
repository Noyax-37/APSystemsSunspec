<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../lib/ModbusClient.php';

class APSystemsSunspec extends eqLogic {
    public function preSave() {
        // Vérifie que l'IP (logicalId) est définie avant sauvegarde
        if (empty($this->getLogicalId())) {
            throw new Exception('L\'adresse IP (logicalId) ne peut pas être vide.');
        }
    }

    public function postSave() {
        $this->checkAndCreateCommands();
        log::add('APSystemsSunspec', 'debug', 'Équipement sauvegardé avec ID : ' . $this->getId());
    }

    public function checkAndCreateCommands() {
        $powerCmd = $this->getCmd(null, 'power');
        if (!is_object($powerCmd)) {
            $powerCmd = new APSystemsSunspecCmd();
            $powerCmd->setName(__('Puissance', __FILE__));
            $powerCmd->setEqLogic_id($this->getId());
            $powerCmd->setLogicalId('power');
            $powerCmd->setType('info');
            $powerCmd->setSubType('numeric');
            $powerCmd->setUnite('W');
            $powerCmd->save();
        }

        $stateCmd = $this->getCmd(null, 'state');
        if (!is_object($stateCmd)) {
            $stateCmd = new APSystemsSunspecCmd();
            $stateCmd->setName(__('État', __FILE__));
            $stateCmd->setEqLogic_id($this->getId());
            $stateCmd->setLogicalId('state');
            $stateCmd->setType('info');
            $stateCmd->setSubType('binary');
            $stateCmd->save();
        }

        $refreshCmd = $this->getCmd(null, 'refresh');
        if (!is_object($refreshCmd)) {
            $refreshCmd = new APSystemsSunspecCmd();
            $refreshCmd->setName(__('Rafraîchir', __FILE__));
            $refreshCmd->setEqLogic_id($this->getId());
            $refreshCmd->setLogicalId('refresh');
            $refreshCmd->setType('action');
            $refreshCmd->setSubType('other');
            $refreshCmd->save();
        }
    }

    public function refreshData() {
        $ip = $this->getLogicalId();
        $this->checkAndUpdateCmd('power', 1500); // Exemple
        $this->checkAndUpdateCmd('state', 1);    // Exemple
        log::add('APSystemsSunspec', 'info', "Données rafraîchies pour IP : $ip");
    }

    public function scanMicroInverters() {
        $ip = $this->getLogicalId();
        $modbusId = 1;
        $maxAttempts = 247;

        log::add('APSystemsSunspec', 'info', "Début du scan pour IP : $ip (ID équipement : " . $this->getId() . ")");
        while ($modbusId <= $maxAttempts) {
            try {
                $response = $this->queryModbus($ip, $modbusId, 40070);
                log::add('APSystemsSunspec', 'debug', "Réponse Modbus ID $modbusId : " . ($response === false ? 'aucune' : $response));

                if ($response === false) {
                    log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId, fin du scan");
                    break;
                }

                $type = null;
                if ($response == 101) {
                    $type = 'monophasé';
                } elseif ($response == 103) {
                    $type = 'triphasé';
                }

                if ($type !== null) {
                    $this->createChildEquipment($ip, $modbusId, $type);
                    log::add('APSystemsSunspec', 'info', "Micro-onduleur détecté : ID $modbusId ($type)");
                }

                $modbusId++;
            } catch (Exception $e) {
                log::add('APSystemsSunspec', 'debug', 'Erreur Modbus ID ' . $modbusId . ' : ' . $e->getMessage());
                break;
            }
        }
        log::add('APSystemsSunspec', 'info', 'Scan terminé pour IP : ' . $ip);
        $this->save(); // Sauvegarde l'équipement parent après le scan
    }

    private function queryModbus($ip, $modbusId, $register) {
        try {
            $client = new ModbusClient($ip, 502, 5);
            $client->setSlave($modbusId);
            $response = $client->readHoldingRegisters($register, 1);

            if ($response === false || empty($response)) {
                return false;
            }
            return $response[0];
        } catch (Exception $e) {
            log::add('APSystemsSunspec', 'error', 'Erreur Modbus pour IP ' . $ip . ' ID ' . $modbusId . ' : ' . $e->getMessage());
            return false;
        }
    }

    private function createChildEquipment($ip, $modbusId, $type) {
        $newEqLogic = new APSystemsSunspec();
        $newEqLogic->setName('Micro-onduleur ' . $modbusId . ' (' . $type . ')');
        $newEqLogic->setLogicalId($ip . '_ID' . $modbusId);
        $newEqLogic->setEqType_name('APSystemsSunspec');
        $newEqLogic->setConfiguration('parent_id', $this->getId());
        $newEqLogic->setConfiguration('modbus_id', $modbusId);
        $newEqLogic->setConfiguration('type', $type);
        $newEqLogic->save();

        $newEqLogic->checkAndCreateCommands();
        log::add('APSystemsSunspec', 'debug', "Équipement créé avec ID : " . $newEqLogic->getId());
    }

    public function setParameter($register, $value) {
        $ip = $this->getLogicalId();
        $modbusId = $this->getConfiguration('modbus_id', 1);
        try {
            $client = new ModbusClient($ip, 502, 5);
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