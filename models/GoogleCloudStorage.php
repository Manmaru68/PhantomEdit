<?php
require_once __DIR__ .'/../vendor/autoload.php';

use Google\Cloud\Storage\StorageClient;

//En GoogleCloudStorage es gestiona l'emmagatzematge dels arxius, tant per originals
//com editades. Es separa els arxius amb els buckets corresponents
class GoogleCloudStorage {
    private $storage;
    private $config;
    private $buckets = [];

    public function __construct() {
        // Carreguem les credencials per obtenir accès als buckets de GoogleCloudStorage
        $this->config = require __DIR__ . '/../config/google_cloud.php';

        // Fem configuració SSL per tenir conexió amb Google Cloud desde PHP.
        $this->storage = new StorageClient([
            'projectId' => $this->config['project_id'],
            'keyFilePath' => $this->config['key_file'],
            'transport' => 'rest',
            'httpOptions' => [
                'verify' => 'C:\php\extras\ssl\cacert.pem',
                'curl' => [
                    CURLOPT_CAINFO => 'C:\php\extras\ssl\cacert.pem',
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2
                ]
            ]
        ]);

        // Carreguem els buckets i es permet pujar els arxius de imatge i audio
        // simúltaneament i de manera instàntania
        foreach($this->config['buckets'] as $type => $bucketConfig) {
            $this->buckets[$type] = $this->storage->bucket($bucketConfig['name']);
        }
    }

    // Pugem l'arxiu al bucket especificat
    public function uploadFile($file, $type) {
        try {
            if(!isset($this->buckets[$type])) {
                throw new Exception("Tipus de bucket no vàlid: $type");
            }
            //Obtenim el bucket on pujarem l'arxiu
            $bucket = $this->buckets[$type];
            // Generem un nom únic, ja que es convertirà en el requestId per referenciar l'edició de la imatge
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = uniqid() . '.' . $extension;
            
            // Definim la ubicació exacta dins del bucket
            $filePath = $this->config['buckets'][$type]['path'] . '/' . $fileName;

            // Transferim l'arxiu des de PHP al bucket de Google Cloud
            $object = $bucket ->upload(
                fopen($file['tmp_name'], 'r'),
                [
                    'name' => $filePath,
                    'metadata' => [
                        'contentType' => $file['type']
                    ]
                ]
            );

            // Aquest URL anirá als mòduls d'edició per processar la imatge
            $url = $object->info()['mediaLink'];

            // Fem return de la informació que necessita els mòduls per fer el
            // procès d'edició.
            return [
                'success' => true,
                'url' => $url,
                'path' => $filePath,
                'fileName' => $fileName
            ];
        } catch (Exception $e) {
            error_log("Error en GoogleCloudStorage::uploadFile: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Construeix la URL per mostrar la imatge editada al frontend
    public function getPublicUrl($bucketType, $path) {
        if(!isset($this->config['buckets'][$bucketType])) {
            throw new Exception("Tipus de bucket no vàlid: $bucketType");
        }

        //Combinem la URL de bucket amb el nom del l'arxiu
        //Aquesta URL ens permet veure la imatge editada
        $bucketConfig = $this->config['buckets'][$bucketType];
        return $bucketConfig['url'] . '/' . basename($path);
    }

    // Esborrar l'arxiu del bucket
    public function deleteFile($bucketType, $path) {
        try {
            if(!isset($this->config['buckets'][$bucketType])) {
                throw new Exception("Tipus de bucket no vàlid: $bucketType");
            }

            // Busquem l'arxiu, un cop ho localitzem, ho eliminem del bucket
            $bucket = $this->buckets[$bucketType];
            $object = $bucket->object($path);
            $object->delete();

            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Garantim que els arxius siguin únics
    private function generateUniqueFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return uniqid(). '.' . $extension;
    }

    // Verifiquem si la imatge editada es troba en el bucket edited
    public function fileExists($bucketType, $path) {
        try {
            if(!isset($this->config['buckets'][$bucketType])) {
                throw new Exception("Tipus de bucket no vàlid: $bucketType");
            }

            //Busquem l'arxiu en el bucket
            $bucket = $this->buckets[$bucketType];
            $object = $bucket->object($path);

            // El retorn será true si obtenim la imatge editada
            return $object->exists();
        } catch (Exception $e) {
            // Retornem fals en cas de no trobar l'arxiu en el bucket
            // seguirà observant el bucket, esperant la imatge editada.
            return false;
        }
    }
}