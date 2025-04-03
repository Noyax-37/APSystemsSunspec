<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../lib/ModbusClient.php';

class APSystemsSunspec extends eqLogic {
    public function preSave() {
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
            $powerCmd->setName(__('Puissance totale', __FILE__));
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

    public function createCommand($logicalId, $name, $type, $subType, $unit = '', $registre = 0, $calcul = '', $size = 1, $order = 1, $coef = 1, $isVisible = 1) {
        $cmd = $this->getCmd(null, $logicalId);
        if (!is_object($cmd)) {
            $cmd = new APSystemsSunspecCmd();
            $cmd->setName(__($name, __FILE__));
            $cmd->setEqLogic_id($this->getId());
            $cmd->setLogicalId($logicalId);
            $cmd->setType($type);
            $cmd->setSubType($subType);
            $cmd->setConfiguration('registre', $registre);
            $cmd->setConfiguration('calcul', $calcul);
            $cmd->setConfiguration('size', $size);
            $cmd->setIsVisible($isVisible);
            $cmd->setOrder($order);
            $cmd->setConfiguration('coef', $coef);
            if ($unit) {
                $cmd->setUnite($unit);
            }
            $cmd->save();
        }
    }

    public function checkAndCreateCommandsMO($type) {
        $order = 1;
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
        $this->createCommand('amps', 'Courant', 'info', 'numeric', 'A', 40072, 'uint16', 1, $order);
        $order++;
        if ($type == 'triphasé') {
            $this->createCommand('ampsph1', 'Courant phase 1', 'info', 'numeric', 'A', 40073, 'uint16', 1, $order);
            $order++;
            $this->createCommand('ampsph2', 'Courant phase 2', 'info', 'numeric', 'A', 40074, 'uint16', 1, $order);
            $order++;
            $this->createCommand('ampsph3', 'Courant phase 3', 'info', 'numeric', 'A', 40075, 'uint16', 1, $order);
            $order++;
            $this->createCommand('vph1ph2', 'Tension ph1/ph2', 'info', 'numeric', 'V', 40077, 'uint16', 1, $order);
            $order++;
            $this->createCommand('vph2ph3', 'Tension ph2/ph3', 'info', 'numeric', 'V', 40078, 'uint16', 1, $order);
            $order++;
            $this->createCommand('vph3ph1', 'Tension ph3/ph1', 'info', 'numeric', 'V', 40079, 'uint16', 1, $order);
            $order++;
        }
        $this->createCommand('vph1', 'Tension ph1', 'info', 'numeric', 'V', 40080, 'uint16', 1, $order);
        $order++;
        if ($type == 'triphasé') {
            $this->createCommand('vph2', 'Tension ph2', 'info', 'numeric', 'V', 40081, 'uint16', 1, $order);
            $order++;
            $this->createCommand('vph3', 'Tension ph3', 'info', 'numeric', 'V', 40082, 'uint16', 1, $order);
            $order++;
        }
        $this->createCommand('power', 'Puissance', 'info', 'numeric', 'W', 40084, 'int16', 1, $order);
        $order++;
        $this->createCommand('frequency', 'Fréquence', 'info', 'numeric', 'Hz', 40086, 'int16', 1, $order);
        $order++;
        $this->createCommand('va', 'Puissance apparente', 'info', 'numeric', 'VA', 40088, 'int16', 1, $order);
        $order++;
        $this->createCommand('var', 'Puissance réactive', 'info', 'numeric', 'VAR', 40090, 'int16', 1, $order);
        $order++;
        $this->createCommand('power_factor', 'Facteur de puissance', 'info', 'numeric', '', 40092, 'int16', 1, $order);
        $order++;
        $this->createCommand('energy', 'Énergie', 'info', 'numeric', 'Wh', 40094, 'acc32', 2, $order);
        $order++;
        $this->createCommand('cabinet_temp', 'Température du boîtier', 'info', 'numeric', '°C', 40103, 'int16', 1, $order);
        $order++;
        $this->createCommand('operating_state', 'État de fonctionnement', 'info', 'string', '', 40104, 'enum16', 1, $order);
        $order++;
        $this->createCommand('event1', 'Événement 1', 'info', 'string', '', 40105, 'bitfield32', 2, $order);
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
        $this->createCommand('cabinet_temp_float', 'Température du boîtier float', 'info', 'numeric', '°C', 40156, 'float32', 2, $order);
    }

    public function refreshData() {
        $ip = $this->getLogicalId();
        $timeout = $this->getConfiguration('timeout', 3); // Timeout par défaut à 3s
        $totalPower = 0;
        $state = 0;

        // Récupérer tous les enfants
        $children = eqLogic::byTypeAndSearchConfiguration('APSystemsSunspec', array('parent_id' => $this->getId()));
        foreach ($children as $child) {
            $modbusId = $child->getConfiguration('modbus_id');
            try {
                $client = new ModbusClient($ip, 502, $timeout);
                $client->setSlave($modbusId);

                // Lire la puissance (registre 40084 pour int16 ou 40144 pour float32)
                $power = $client->readHoldingRegisters(40144, 2); // float32, 2 registres
                $powerValue = $this->decodeFloat32($power[0], $power[1]);
                $child->checkAndUpdateCmd('power_float', $powerValue);
                $totalPower += $powerValue;

                // Mettre à jour l'état de l'enfant (exemple : actif si puissance > 0)
                $child->checkAndUpdateCmd('state', $powerValue > 0 ? 1 : 0);

                log::add('APSystemsSunspec', 'info', "Données rafraîchies pour enfant ID $modbusId : Puissance = $powerValue W");
            } catch (Exception $e) {
                log::add('APSystemsSunspec', 'error', "Erreur refresh pour enfant ID $modbusId : " . $e->getMessage());
            }
        }

        // Mettre à jour le parent avec laa somme
        $this->checkAndUpdateCmd('power', $totalPower);
        $this->checkAndUpdateCmd('state', $totalPower > 0 ? 1 : 0);
        log::add('APSystemsSunspec', 'info', "Données rafraîchies pour IP : $ip - Puissance totale : $totalPower W");
    }

    private function decodeFloat32($high, $low) {
        $bin = sprintf("%016b%016b", $high, $low);
        $sign = substr($bin, 0, 1) == '1' ? -1 : 1;
        $exp = bindec(substr($bin, 1, 8)) - 127;
        $mantissa = 1 + bindec(substr($bin, 9)) / pow(2, 23);
        return $sign * $mantissa * pow(2, $exp);
    }

    public function scanMicroInverters($objectId = null) {

        $ip = $this->getLogicalId();
        $timeout = $this->getConfiguration('timeout', 3);
        $modbusId = 1;
        $maxAttempts = 247;

        log::add('APSystemsSunspec', 'info', "Début du scan pour IP : $ip (ID équipement : " . $this->getId() . ")");
        while ($modbusId <= $maxAttempts) {
            try {
                $response = $this->queryModbus($ip, $modbusId, 40070, $timeout);
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
                    $this->createChildEquipment($ip, $modbusId, $type, $timeout, $objectId);
                    log::add('APSystemsSunspec', 'info', "Micro-onduleur détecté : ID $modbusId ($type)");
                }

                $modbusId++;
            } catch (Exception $e) {
                log::add('APSystemsSunspec', 'debug', "Erreur Modbus ID $modbusId : " . $e->getMessage());
                break;
            }
        }
        log::add('APSystemsSunspec', 'info', "Scan terminé pour IP : $ip");
        $this->save();
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
            return $response[0];
        } catch (Exception $e) {
            log::add('APSystemsSunspec', 'debug', "Aucune réponse pour ID $modbusId : " . $e->getMessage());
            return false;
        }
    }

    public function getECUData() {
        $ip = $this->getLogicalId();
        $timeout = $this->getConfiguration('timeout', 3);
        $parentId = $this->getId();
    
        // Récupérer tous les fils ayant cet équipement comme parent
        $children = eqLogic::byTypeAndSearchConfiguration('APSystemsSunspec', array('parent_id' => $parentId));
    
        if (empty($children)) {
            log::add('APSystemsSunspec', 'info', "Aucun micro-onduleur trouvé pour l'ECU avec IP : $ip (ID : $parentId)");
            return false;
        }
    
        // Traiter chaque enfant pour récupérer ses données Modbus
        $success = true;
        foreach ($children as $child) {
            $modbusId = $child->getConfiguration('modbus_id');
            if (!is_numeric($modbusId)) {
                log::add('APSystemsSunspec', 'error', "ID Modbus invalide pour l'enfant avec logicalId : " . $child->getLogicalId());
                $success = false;
                continue;
            }
    
            try {
                $client = new ModbusClient($ip, 502, $timeout);
                $client->setSlave($modbusId);
    
                // Diviser la lecture en deux blocs pour respecter la limite de 125 registres
                $data = [];
    
                // Premier bloc : 40002 à 40126 (125 registres)
                $firstBlock = $client->readHoldingRegisters(40002, 125);
                if ($firstBlock === false || empty($firstBlock)) {
                    throw new Exception("Aucune donnée reçue pour Modbus ID $modbusId (premier bloc)");
                }
                // Ajouter les données du premier bloc
                for ($i = 0; $i < 125; $i++) {
                    $data[$i] = $firstBlock[$i];
                }
    
                // Deuxième bloc : 40127 à 40157 (31 registres)
                $secondBlock = $client->readHoldingRegisters(40127, 31);
                if ($secondBlock === false || empty($secondBlock)) {
                    throw new Exception("Aucune donnée reçue pour Modbus ID $modbusId (deuxième bloc)");
                }
                // Ajouter les données du deuxième bloc
                for ($i = 0; $i < 31; $i++) {
                    $data[$i + 125] = $secondBlock[$i];
                }
    
                // Mettre à jour les commandes de l'enfant avec les données lues
                $this->updateChildCommands($child, $data);
                log::add('APSystemsSunspec', 'info', "Données ECU récupérées pour enfant ID $modbusId (logicalId : " . $child->getLogicalId() . ")");
            } catch (Exception $e) {
                log::add('APSystemsSunspec', 'error', "Erreur lors de la récupération des données ECU pour enfant ID $modbusId : " . $e->getMessage());
                $success = false;
            }
        }
    
        return $success;
    }

    private function updateChildCommands($child, $data) {
        // Récupérer toutes les commandes de type 'info' de l'enfant
        $commands = $child->getCmd('info');
        if (empty($commands)) {
            log::add('APSystemsSunspec', 'warning', "Aucune commande de type 'info' trouvée pour l'enfant avec logicalId : " . $child->getLogicalId());
            return;
        }

        foreach ($commands as $cmd) {
            // Récupérer les informations de configuration de la commande
            $registre = $cmd->getConfiguration('registre', 0);
            $size = $cmd->getConfiguration('size', 1);
            $calcul = $cmd->getConfiguration('calcul', '');

            // Vérifier si le registre est dans la plage lue (40002 à 40157)
            if ($registre < 40002 || $registre + $size - 1 > 40157) {
                log::add('APSystemsSunspec', 'debug', "Registre $registre hors plage pour la commande {$cmd->getLogicalId()} (taille : $size)");
                continue;
            }

            // Calculer l'index dans le tableau $data
            $index = $registre - 40002;

            try {
                // Traiter la valeur en fonction du type de donnée (calcul)
                if ($calcul == 'float32' && $size == 2) {
                    $value = $this->decodeFloat32($data[$index], $data[$index + 1]);
                } elseif ($calcul == 'uint16') {
                    $value = $data[$index];
                } elseif ($calcul == 'int16') {
                    $value = ($data[$index] > 32767) ? $data[$index] - 65536 : $data[$index];
                } elseif ($calcul == 'acc32' && $size == 2) {
                    $value = ($data[$index] << 16) + $data[$index + 1];
                } elseif ($calcul == 'string') {
                    $value = '';
                    for ($i = 0; $i < $size; $i++) {
                        $value .= pack('n', $data[$index + $i]); // Convertir les registres en chaîne
                    }
                    $value = trim($value);
                } elseif ($calcul == 'enum16') {
                    $value = $data[$index]; // À adapter selon les valeurs possibles
                } elseif ($calcul == 'bitfield32' && $size == 2) {
                    $value = ($data[$index] << 16) + $data[$index + 1]; // À adapter selon le format
                } else {
                    // Par défaut, on prend la valeur brute
                    $value = $data[$index];
                    log::add('APSystemsSunspec', 'debug', "Type de calcul non spécifié pour la commande {$cmd->getLogicalId()}, utilisation de la valeur brute : $value");
                }

                // Mettre à jour la commande avec la valeur
                $child->checkAndUpdateCmd($cmd->getLogicalId(), $value);
                log::add('APSystemsSunspec', 'debug', "Commande {$cmd->getLogicalId()} mise à jour avec la valeur : $value (registre : $registre, taille : $size, type : $calcul)");
            } catch (Exception $e) {
                log::add('APSystemsSunspec', 'error', "Erreur lors de la mise à jour de la commande {$cmd->getLogicalId()} : " . $e->getMessage());
            }
        }
    }

    private function createChildEquipment($ip, $modbusId, $type, $timeout = 3, $objectId = null) {
        $existingEqLogic = eqLogic::byLogicalId($ip . '_ID' . $modbusId, 'APSystemsSunspec');
        if (is_object($existingEqLogic)) {
            log::add('APSystemsSunspec', 'debug', "Équipement déjà existant pour ID $modbusId, pas de création");
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
        $newEqLogic->setConfiguration('timeout', $timeout);
        if ($objectId) {
            $newEqLogic->setObject_id($objectId);
        }
        $newEqLogic->setIsVisible(1);
        $newEqLogic->setIsEnable(1);
        $newEqLogic->save();

        $newEqLogic->checkAndCreateCommandsMO($type);
        log::add('APSystemsSunspec', 'debug', "Équipement créé avec ID : " . $newEqLogic->getId());
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
        }
    }

    // Ajouter le champ timeout dans la configuration
    public static function getConfigFields() {
        return array(
            'timeout' => array(
                'type' => 'number',
                'name' => 'Timeout (secondes)',
                'default' => 3,
                'min' => 1,
                'max' => 30,
                'step' => 1,
                'description' => 'Délai d\'attente pour les requêtes Modbus (en secondes)',
            ),
        );
    }

    public function getImage() {
        if (strpos($this->getLogicalId(), '_ID') === false) {
            return 'plugins/APSystemsSunspec/plugin_info/APSystemsSunspec_icon.png'; // Icône pour le père
        } else {
            return 'plugins/APSystemsSunspec/plugin_info/microinverter_icon.png'; // Icône pour les fils
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