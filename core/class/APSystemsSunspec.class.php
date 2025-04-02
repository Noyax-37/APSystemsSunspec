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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../lib/ModbusClient.php';

class APSystemsSunspec extends eqLogic {
    public function preSave() {
        // Rien ici pour l'instant
    }

    public function postSave() {
        $this->checkAndCreateCommands();
    }

    // Méthode pour vérifier et créer les commandes
    public function checkAndCreateCommands() {
        // Exemple : Commande "Puissance" (type info, sous-type numeric)
        $powerCmd = $this->getCmd(null, 'power');
        if (!is_object($powerCmd)) {
            $powerCmd = new APSystemsSunspecCmd();
            $powerCmd->setName(__('Puissance', __FILE__));
            $powerCmd->setEqLogic_id($this->getId());
            $powerCmd->setLogicalId('power');
            $powerCmd->setType('info');
            $powerCmd->setSubType('numeric');
            $powerCmd->setUnite('W'); // Unité en watts
            $powerCmd->save();
        }

        // Exemple : Commande "État" (type info, sous-type binary)
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

        // Exemple : Commande "Rafraîchir" (type action)
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

    // Méthode pour mettre à jour une commande
    public function refreshData() {
        $ip = $this->getLogicalId(); // Récupère l'adresse IP
        // Logique pour interroger l'appareil via l'IP (ex. API Sunspec)
        // Mise à jour des valeurs des commandes
        $this->checkAndUpdateCmd('power', 1500); // Exemple : 1500W
        $this->checkAndUpdateCmd('state', 1);    // Exemple : État ON
    }

    public function scanMicroInverters() {
        $ip = $this->getLogicalId(); // Adresse IP de l'ECU
        $modbusId = 1;
        $maxAttempts = 247; // Limite Modbus (1 à 247)

        while ($modbusId <= $maxAttempts) {
            try {
                $response = $this->queryModbus($ip, $modbusId, 40070);
                
                if ($response === false) {
                    log::add('APSystemsSunspec', 'debug', "Aucune réponse pour Modbus ID $modbusId, fin du scan");
                    break; // Pas de réponse, fin du scan
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
    }

    private function queryModbus($ip, $modbusId, $register) {
        try {
            $client = new ModbusClient($ip, 502, 5); // IP, port, timeout 5s
            $client->setSlave($modbusId);
            $response = $client->readHoldingRegisters($register, 1);

            if ($response === false || empty($response)) {
                return false;
            }
            return $response[0]; // Retourne la première valeur
        } catch (Exception $e) {
            log::add('APSystemsSunspec', 'error', 'Erreur Modbus : ' . $e->getMessage());
            return false;
        }
    }

    public function setParameter($register, $value) {
        $ip = $this->getLogicalId();
        $modbusId = $this->getConfiguration('modbus_id', 1); // Par défaut 1 si non défini

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
    }

}

// Classe pour les commandes
class APSystemsSunspecCmd extends cmd {
    // Méthode execute pour les actions (optionnel)
    public function execute($_options = array()) {
        $eqLogic = $this->getEqLogic(); // Récupère l'équipement associé
        switch ($this->getLogicalId()) {
            case 'refresh':
                // Logique pour rafraîchir les données depuis l'IP
                $eqLogic->refreshData();
                break;
        }
    }
}