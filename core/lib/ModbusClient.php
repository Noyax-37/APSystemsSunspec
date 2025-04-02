<?php
class ModbusClient {
    private $ip;
    private $port;
    private $socket;
    private $slaveId;
    private $timeout;
    private $transactionId = 0;

    /**
     * Constructeur de la classe ModbusClient
     * @param string $ip Adresse IP de l'équipement
     * @param int $port Port TCP (par défaut 502)
     * @param int $timeout Délai d'attente en secondes (par défaut 5)
     */
    public function __construct($ip, $port = 502, $timeout = 5) {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->connect();
    }

    /**
     * Établit la connexion au serveur Modbus
     * @throws Exception si la connexion échoue
     */
    private function connect() {
        $this->socket = @fsockopen($this->ip, $this->port, $errno, $errstr, $this->timeout);
        if ($this->socket === false) {
            throw new Exception("Impossible de se connecter à $this->ip:$this->port - $errstr ($errno)");
        }
        stream_set_timeout($this->socket, $this->timeout);
    }

    /**
     * Définit l'ID de l'esclave (unité Modbus)
     * @param int $slaveId ID de l'esclave (1-247)
     */
    public function setSlave($slaveId) {
        if ($slaveId < 1 || $slaveId > 247) {
            throw new Exception("ID esclave invalide : $slaveId (doit être entre 1 et 247)");
        }
        $this->slaveId = $slaveId;
    }

    /**
     * Lit les registres Holding (fonction Modbus 3)
     * @param int $startRegister Registre de départ (1-based, ajusté en interne)
     * @param int $quantity Nombre de registres à lire
     * @return array|bool Tableau des valeurs ou false en cas d'échec
     */
    public function readHoldingRegisters($startRegister, $quantity) {
        if (!$this->socket || !$this->slaveId) {
            throw new Exception("Connexion non établie ou ID esclave non défini");
        }

        if ($quantity < 1 || $quantity > 125) {
            throw new Exception("Quantité invalide : $quantity (doit être entre 1 et 125)");
        }

        $this->transactionId++;
        if ($this->transactionId > 65535) {
            $this->transactionId = 1; // Réinitialise si dépassement
        }

        // MBAP Header (7 octets) + PDU (5 octets)
        $request = pack(
            'n n n C C n n', // Format : Transaction ID (2), Protocol ID (2), Length (2), Slave ID (1), Function Code (1), Start Register (2), Quantity (2)
            $this->transactionId, // Transaction ID
            0,                    // Protocol ID (Modbus TCP = 0)
            6,                    // Length (Slave ID + Function + Data = 6 octets)
            $this->slaveId,       // Slave ID
            3,                    // Function Code (Read Holding Registers)
            $startRegister - 1,   // Registre de départ (0-based en interne)
            $quantity             // Nombre de registres
        );

        // Envoi de la requête
        $bytesWritten = fwrite($this->socket, $request);
        if ($bytesWritten === false || $bytesWritten != strlen($request)) {
            throw new Exception("Échec de l'envoi de la requête Modbus");
        }

        // Lecture de la réponse (MBAP Header + PDU)
        $header = fread($this->socket, 7); // 7 octets pour MBAP
        if ($header === false || strlen($header) != 7) {
            throw new Exception("Échec de la lecture de l'en-tête de la réponse");
        }

        $headerData = unpack('ntransId/nproto/nlength/Cslave', $header);
        $dataLength = $headerData['length'] - 2; // Retire Slave ID et Function Code
        $response = fread($this->socket, 2 + $dataLength);

        if ($response === false || strlen($response) != (2 + $dataLength)) {
            throw new Exception("Réponse Modbus incomplète ou invalide");
        }

        $responseData = unpack('Cslave/Cfunc/CbyteCount/n*values', $response);
        if ($responseData['func'] == 0x83) { // Code d'erreur (Function Code + 0x80)
            $errorCode = unpack('Cerror', substr($response, 2, 1))['error'];
            throw new Exception("Erreur Modbus : Code $errorCode");
        }

        if ($responseData['func'] != 3 || $responseData['byteCount'] != $quantity * 2) {
            throw new Exception("Réponse invalide : fonction ou taille des données incorrecte");
        }

        $values = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $values[] = $responseData['values' . $i];
        }
        return $values;
    }

    /**
     * Écrit une valeur dans un registre Holding (fonction Modbus 6)
     * @param int $register Registre à écrire (1-based, ajusté en interne)
     * @param int $value Valeur à écrire (0-65535)
     * @return bool Succès ou échec de l'écriture
     */
    public function writeSingleRegister($register, $value) {
        if (!$this->socket || !$this->slaveId) {
            throw new Exception("Connexion non établie ou ID esclave non défini");
        }

        if ($value < 0 || $value > 65535) {
            throw new Exception("Valeur invalide : $value (doit être entre 0 et 65535)");
        }

        $this->transactionId++;
        if ($this->transactionId > 65535) {
            $this->transactionId = 1; // Réinitialise si dépassement
        }

        // MBAP Header (7 octets) + PDU (5 octets)
        $request = pack(
            'n n n C C n n', // Format : Transaction ID (2), Protocol ID (2), Length (2), Slave ID (1), Function Code (1), Register (2), Value (2)
            $this->transactionId, // Transaction ID
            0,                    // Protocol ID (Modbus TCP = 0)
            6,                    // Length (Slave ID + Function + Data = 6 octets)
            $this->slaveId,       // Slave ID
            6,                    // Function Code (Write Single Register)
            $register - 1,        // Registre (0-based en interne)
            $value                // Valeur à écrire
        );

        // Envoi de la requête
        $bytesWritten = fwrite($this->socket, $request);
        if ($bytesWritten === false || $bytesWritten != strlen($request)) {
            throw new Exception("Échec de l'envoi de la requête Modbus");
        }

        // Lecture de la réponse (MBAP Header + PDU = 12 octets)
        $response = fread($this->socket, 12);
        if ($response === false || strlen($response) != 12) {
            throw new Exception("Réponse Modbus incomplète ou invalide");
        }

        $responseData = unpack('ntransId/nproto/nlength/Cslave/Cfunc/nregister/nvalue', $response);
        if ($responseData['func'] == 0x86) { // Code d'erreur (Function Code + 0x80)
            $errorCode = unpack('Cerror', substr($response, 8, 1))['error'];
            throw new Exception("Erreur Modbus : Code $errorCode");
        }

        if ($responseData['func'] != 6 || $responseData['register'] != ($register - 1) || $responseData['value'] != $value) {
            throw new Exception("Réponse invalide : écriture non confirmée");
        }

        return true;
    }

    /**
     * Ferme la connexion au serveur Modbus
     */
    public function close() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Destructeur pour fermer la connexion proprement
     */
    public function __destruct() {
        $this->close();
    }
}