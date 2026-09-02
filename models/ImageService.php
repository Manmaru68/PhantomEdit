<?php
require_once __DIR__ .'/GoogleCloudStorage.php';
require_once __DIR__ .'/../config/config.php';

//En ImageService té la lògica per al processament d'imatges, es valida els arxius i
// es comunica amb les APIs externes.
class ImageService{
    private $storage;
    private $apiEndpoint = 'https://decididor-servicio-1009544784389.europe-west1.run.app';
    
    public function __construct() {
        $this->storage = new GoogleCloudStorage();
    }

    // Es processa la imatge amb instruccions, depenent del tipus de petició (àudio o text)
    public function processImage($imageFile, $editRequest = null, $audioFile = null) {
        $this->validateImage($imageFile);

        //Pugem la imatge al bucket corresponent en Google Cloud Storage
        $imageResult = $this->storage->uploadFile($imageFile,'images');
        if(!$imageResult['success']) {
            throw new Exception($imageResult['error']);
        }

        // Generem un ID únic per a la petició
        // Aquest ID de la petició ens servirá per fer seguiment de la petició i per referenciar la imatge
        $requestId = uniqid();
        
        //JSON que passarem al mòdul seguent amb les dades que espera l'API extern.
        // audioRef i text, ho assignarem posteriorment
        // en cas que una opció no sigui escollit, es deixarà en blanc
        $editData = [
            'requestId' => $requestId,
            'imageRef' => $imageResult['url'],
            'audioRef' => '',
            'text' => ''
        ];

        // Processamen de la imatge a partir del tipus de petició (àudio o text)
        if(!empty($editRequest)) {
            // S'assignem el text en el JSON
            $editData['text'] = $editRequest;
        } elseif($audioFile && $audioFile['error'] === UPLOAD_ERR_OK) {
            // Si està buit el camp del text, fem validacions si s'ha utilitzat
            // l'opció d'àudio. En cas correcte, validarem l'àudio i ho pujarem al bucket
            // i asignem 
            $this->validateAudio($audioFile);
            $audioResult = $this->storage->uploadFile($audioFile, 'audio');
            if (!$audioResult['success']) {
                throw new Exception($audioResult['error']);
            }
            $editData['audioRef'] = $audioResult['url'];
        } else {
            throw new Exception('No s\'ha rebut la petició d\'edició');
        }

        // Enviem la petició amb JSON per processar la imatge
        $this->sendEditRequest($editData);

        //Retorna el resultat amb la ID del seguiment
        return [
            'requestId' => $requestId,
            'imageUrl' => $imageResult['url']
        ];
    }

    // Verifiquem si la imatge editada ja està disponible al bucket 'edited'.
    public function checkImageStatus($requestId) {
        if(empty($requestId)) {
            throw new Exception('Es requereix la ID en la petició per poder obtenir la imatge editada posteriorment.');
        }

        //URL de la imatge editada en el bucket corresponent. Aquest URL ens ajudarà per veure si
        // ja està editat o segueix en processament.
        $editedImagePath = 'edited/' . $requestId . '.jpg';
        
        // Comprovem si existeix la imatge editada en el bucket
        $exists = $this->storage->fileExists('edited', $editedImagePath);
        $imageUrl = null;

        //Si la imatge ja està editada, fem URL per mostrar-la
        if($exists) {
            $imageUrl = "https://storage.cloud.google.com/phantomedit-images/edited/" . $requestId . ".jpg";
        }

        //Fem retorn de l'estat del processament
        return [
            'status' => $exists ? 'completed' : 'processing',
            'imageUrl' => $imageUrl
        ];
    }

    //Fem validacions per a que l'API no rebi imatges incorrectes, invàlids o inusuals.
    private function validateImage($imageFile) {
        if($imageFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($imageFile['error']));
        }

        if($imageFile['size'] > MAX_FILE_SIZE) {
            throw new Exception('La imatge supera el tamany màxim permès: ' . (MAX_FILE_SIZE / (1024 * 1024)) . 'MB');
        }

        if(!in_array($imageFile['type'], ALLOWED_IMAGES_TYPES)) {
            throw new Exception('Aquest tipus o extensió de imatge no està permès. Només s\'accepten: ' . implode(', ', ALLOWED_IMAGES_TYPES));
        }
    }

    private function validateAudio($audioFile) {
        if($audioFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($audioFile['error']));
        }

        if($audioFile['size'] > MAX_FILE_SIZE) {
            throw new Exception('L\'àudio supera el tamany màxim permès. Nomès es permeten: '. (MAX_FILE_SIZE / (1024 * 1024)). 'MB');
        }

        if(!in_array($audioFile['type'], ALLOWED_AUDIO_TYPES)) {
            throw new Exception('Aquest tipus o extensió d\'àudio no està permès. Només s\'accepten: ' . implode(', ', ALLOWED_AUDIO_TYPES));
        }
    }

    //Enviem la petició d'edició a l'API i el servei rebrà
    // la imatge i petició per després retornar la imatge editada al bucket
    private function sendEditRequest($editData) {
        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($editData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        //Configurem timeouts
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        //Verifiquem si hi ha error en el cURL
        if(curl_error($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Error en la petició cURL: ' . $error);
        }

        curl_close($ch);

        // Verifiquem si l'API ens accepta la petició
        if($httpCode !== 200) {
            throw new Exception('Error al processar la imatge: ' . $httpCode);
        }
    }

    //Assignem errors amb text per fer debbugs quan fem la petició
    private function getUploadErrorMessage($errorCode) {
        switch($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'L\'arxiu excedeix el tamany màxim permès per PHP, hem assignat a php.ini 10MB';
            case UPLOAD_ERR_FORM_SIZE: 
                return 'L\'arixu excedeix el tamany màxim permès per el formulari';
            case UPLOAD_ERR_PARTIAL:
                return 'L\'arxiu només s\'ha pujat parcialment';
            case UPLOAD_ERR_NO_FILE:
                return 'No s\'ha pujat cap arxiu';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'No hi ha directori temporal';
            case UPLOAD_ERR_CANT_WRITE:
                return 'No es pot escriure l\'arxiu en el disc';
            case UPLOAD_ERR_EXTENSION:
                return 'No es pot fer la petició amb aquesta extensió \'imatge';
            default:
                return 'Error desconegut, diferent als altres casos d\'errors';
        }
    }
    
    public function deleteImage($bucketType, $path) {
        return $this->storage->deleteFile($bucketType, $path);
    }

}
