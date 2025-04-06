#!/usr/bin/python3
import sys
import socket
import json
import logging
from pymodbus.client import ModbusTcpClient

logging.basicConfig(level=logging.INFO)
log = logging.getLogger('APSystemsSunspecd')

def scan_modbus_ids(ip, max_id):
    client = ModbusTcpClient(ip, port=502)
    devices = {}

    if not client.connect():
        log.error(f"Connexion échouée à {ip}:502")
        return devices

    for modbus_id in range(1, max_id + 1):
        try:
            # Lire le registre 0x9c42 (SunSpec ID)
            result = client.read_holding_registers(0x9c42, 1, slave=modbus_id)
            if result.isError() or result.registers[0] != 0x53756e53:  # "SunS" en hex
                continue  # Pas un appareil SunSpec

            # Lire le registre 0x9c86 pour identifier le type
            type_result = client.read_holding_registers(0x9c86, 1, slave=modbus_id)
            if type_result.isError():
                continue

            device_type = "monophase" if type_result.registers[0] == 101 else "triphase" if type_result.registers[0] == 103 else "inconnu"
            if device_type != "inconnu":
                devices[modbus_id] = device_type
                log.info(f"ID {modbus_id} détecté : {device_type}")

        except Exception as e:
            log.error(f"Erreur pour ID {modbus_id} : {str(e)}")
            continue

    client.close()
    return devices

def main():
    server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server_socket.bind(('127.0.0.1', 55000))  # Port du démon
    server_socket.listen(5)
    log.info("Démon démarré sur 127.0.0.1:55000")

    while True:
        client_socket, _ = server_socket.accept()
        data = client_socket.recv(1024).decode()
        request = json.loads(data)

        if request['action'] == 'scan_modbus_ids':
            ip = request['params']['ip']
            max_id = request['params']['max_id']
            result = scan_modbus_ids(ip, max_id)
            response = json.dumps({'devices': result})
            client_socket.send(response.encode())

        client_socket.close()

if __name__ == "__main__":
    main()