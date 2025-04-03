<?php
class ModbusClient {
    private $ip;
    private $port;
    private $socket;
    private $slaveId;
    private $timeout;
    private $transactionId = 0;

    public function __construct($ip, $port = 502, $timeout = 15) {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->connect();
    }

    private function connect() {
        if (class_exists('log')) {
            log::add('APSystemsSunspec', 'debug', "Tentative de connexion à $this->ip:$this->port (timeout: $this->timeout s)");
        }
        $this->socket = @fsockopen($this->ip, $this->port, $errno, $errstr, $this->timeout);
        if ($this->socket === false) {
            if (class_exists('log')) {
                log::add('APSystemsSunspec', 'error', "Échec de connexion à $this->ip:$this->port - $errstr ($errno)");
            }
            throw new Exception("Impossible de se connecter à $this->ip:$this->port - $errstr ($errno)");
        }
        stream_set_timeout($this->socket, $this->timeout);
        stream_set_blocking($this->socket, 1);
        if (class_exists('log')) {
            log::add('APSystemsSunspec', 'debug', "Connexion établie à $this->ip:$this->port");
        }
    }

    public function setSlave($slaveId) {
        if ($slaveId < 1 || $slaveId > 247) {
            throw new Exception("ID esclave invalide : $slaveId (doit être entre 1 et 247)");
        }
        $this->slaveId = $slaveId;
    }

    public function readHoldingRegisters($startRegister, $quantity) {
        if (!$this->socket || !$this->slaveId) {
            throw new Exception("Connexion non établie ou ID esclave non défini");
        }
        if ($quantity < 1 || $quantity > 125) {
            throw new Exception("Quantité invalide : $quantity (doit être entre 1 et 125)");
        }

        $this->transactionId++;
        if ($this->transactionId > 65535) {
            $this->transactionId = 1;
        }

        $request = pack(
            'nnnCCnn',
            $this->transactionId,
            0,
            6,
            $this->slaveId,
            3,
            $startRegister,
            $quantity
        );

        if (class_exists('log')) {
            log::add('APSystemsSunspec', 'debug', "Préparation envoi requête Modbus : " . bin2hex($request));
        }
        
        if (!is_resource($this->socket) || feof($this->socket)) {
            if (class_exists('log')) {
                log::add('APSystemsSunspec', 'error', "Socket invalide ou fermé avant envoi");
            }
            $this->close();
            $this->connect();
        }

        $bytesWritten = @fwrite($this->socket, $request);
        if ($bytesWritten === false || $bytesWritten < strlen($request)) {
            if (class_exists('log')) {
                log::add('APSystemsSunspec', 'error', "Échec envoi initial : $bytesWritten octets écrits sur " . strlen($request));
            }
            $this->close();
            $this->connect();
            $bytesWritten = @fwrite($this->socket, $request);
            if ($bytesWritten === false || $bytesWritten < strlen($request)) {
                if (class_exists('log')) {
                    log::add('APSystemsSunspec', 'error', "Échec persistant : $bytesWritten octets écrits sur " . strlen($request));
                }
                throw new Exception("Échec persistant de l'envoi de la requête Modbus");
            }
        }
        if (class_exists('log')) {
            log::add('APSystemsSunspec', 'debug', "Requête envoyée avec succès : $bytesWritten octets");
        }

        usleep(1000000); // 1s pour l’ECU

        $totalLength = 7 + (2 + $quantity * 2); // 11 octets pour 1 registre
        $response = '';
        $bytesRead = 0;
        $timeoutSec = 3; // Réduit à 3s pour les IDs non connectés
        $startTime = time();

        while ($bytesRead < $totalLength && (time() - $startTime) < $timeoutSec) {
            $chunk = @fread($this->socket, $totalLength);
            if ($chunk !== false && strlen($chunk) > 0) {
                $response .= $chunk;
                $hexResponse = bin2hex($response);
                $bytesRead = strlen($hexResponse) / 2;
                if (class_exists('log')) {
                    log::add('APSystemsSunspec', 'debug', "Chunk brut (hex) : " . bin2hex($chunk));
                    log::add('APSystemsSunspec', 'debug', "Chunk brut (longueur) : " . strlen($chunk));
                    log::add('APSystemsSunspec', 'debug', "Réponse cumulée : " . $hexResponse);
                    log::add('APSystemsSunspec', 'debug', "Longueur hex brute : " . strlen($hexResponse));
                    log::add('APSystemsSunspec', 'debug', "Longueur calculée : " . $bytesRead . " / $totalLength");
                }
            }
            usleep(200000); // 200ms entre lectures
        }

        if ($bytesRead < 7) {
            if (class_exists('log')) {
                log::add('APSystemsSunspec', 'info', "Aucun équipement détecté pour ID $this->slaveId : timeout ou pas de réponse");
            }
            throw new Exception("Échec de la lecture de l'en-tête de la réponse");
        }

        $header = substr($response, 0, 7);
        if (class_exists('log')) {
            log::add('APSystemsSunspec', 'debug', "En-tête reçu : " . bin2hex($header));
        }

        $headerData = unpack('ntransId/nproto/nlength/Cslave', $header);
        $dataLength = $headerData['length'] - 1; // 5 - 1 = 4
        $expectedTotal = 7 + $dataLength; // 7 + 4 = 11

        if ($bytesRead < $expectedTotal) {
            if (class_exists('log')) {
                log::add('APSystemsSunspec', 'error', "Réponse incomplète : $bytesRead octets sur $expectedTotal, contenu : " . bin2hex($response));
            }
            throw new Exception("Réponse Modbus incomplète ou invalide");
        }

        $data = substr($response, 7);
        if (class_exists('log')) {
            log::add('APSystemsSunspec', 'debug', "Données reçues : " . bin2hex($data));
        }

        $responseData = unpack('Cfunc/CbyteCount/n*values', $data);
        if (class_exists('log')) {
            log::add('APSystemsSunspec', 'debug', "responseData : " . print_r($responseData, true));
        }
        if ($responseData['func'] == 0x83) {
            $errorCode = unpack('Cerror', substr($data, 1, 1))['error'];
            throw new Exception("Erreur Modbus : Code $errorCode");
        }

        if ($responseData['func'] != 3 || $responseData['byteCount'] != $quantity * 2) {
            if (class_exists('log')) {
                log::add('APSystemsSunspec', 'debug', "Erreur détail : func={$responseData['func']}, byteCount={$responseData['byteCount']}, attendu 3 et " . ($quantity * 2));
            }
            throw new Exception("Réponse invalide : fonction ou taille des données incorrecte");
        }

        $values = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $values[] = $responseData['values' . $i];
        }
        return $values;
    }

    public function writeSingleRegister($register, $value) {
        if (!$this->socket || !$this->slaveId) {
            throw new Exception("Connexion non établie ou ID esclave non défini");
        }
        if ($value < 0 || $value > 65535) {
            throw new Exception("Valeur invalide : $value (doit être entre 0 et 65535)");
        }

        $this->transactionId++;
        if ($this->transactionId > 65535) {
            $this->transactionId = 1;
        }

        $request = pack(
            'nnnCCnn',
            $this->transactionId,
            0,
            6,
            $this->slaveId,
            6,
            $register,
            $value
        );

        if (class_exists('log')) {
            log::add('APSystemsSunspec', 'debug', "Envoi écriture : " . bin2hex($request));
        }
        $bytesWritten = @fwrite($this->socket, $request);
        if ($bytesWritten === false || $bytesWritten < strlen($request)) {
            if (class_exists('log')) {
                log::add('APSystemsSunspec', 'error', "Échec envoi écriture : $bytesWritten octets écrits sur " . strlen($request));
            }
            $this->close();
            $this->connect();
            $bytesWritten = @fwrite($this->socket, $request);
            if ($bytesWritten === false || $bytesWritten < strlen($request)) {
                throw new Exception("Échec persistant de l'envoi de la requête Modbus");
            }
        }

        $response = @fread($this->socket, 12);
        if ($response === false || strlen($response) != 12) {
            throw new Exception("Réponse Modbus incomplète ou invalide");
        }

        $responseData = unpack('ntransId/nproto/nlength/Cslave/Cfunc/nregister/nvalue', $response);
        if ($responseData['func'] == 0x86) {
            $errorCode = unpack('Cerror', substr($response, 8, 1))['error'];
            throw new Exception("Erreur Modbus : Code $errorCode");
        }

        if ($responseData['func'] != 6 || $responseData['register'] != $register || $responseData['value'] != $value) {
            throw new Exception("Réponse invalide : écriture non confirmée");
        }

        return true;
    }

    public function close() {
        if ($this->socket && is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
            if (class_exists('log')) {
                log::add('APSystemsSunspec', 'debug', "Connexion fermée à $this->ip:$this->port");
            }
        }
    }

    public function __destruct() {
        $this->close();
    }
}