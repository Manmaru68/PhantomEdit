<?php

//Augmentem el temps d'espera per la pujada d'imatges i àudio
set_time_limit(120); 

require_once __DIR__ .'/../models/GoogleCloudStorage.php';
require_once __DIR__ .'/../models/ImageService.php';
require_once __DIR__ .'/../config/config.php';

// S'encarrega de rebre les peticions i retornar les respostes, verificant
// els POSTs i GETs. ImageService conté la lògica. 

class ImageController {
    private $imageService;

    public function __construct() {
        $this -> imageService = new ImageService();
    }

    // Encarregat de mostrar la pàgina web
    public function index() {
        define('SECURE_ACCESS', true);
        require_once __DIR__ .'/../views/index.php';
    }

    // upload és la funció encarregada de pujar la imatge. Primer valida el mètode POST,
    // després valida si existeix la imatge per posteriorment extraure les dades de la petició i
    // ho delega al ImageService. Per finalitzar, retorna la resposta JSON.
    public function upload() {
        try {
            if (ob_get_length()) ob_clean(); //Es neteja el buffer per evitar caràcters invalids o incorrectes.
            header('Content-Type: application/json');

            if($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('El mètode no està permés');
            }

            if(!isset($_FILES['image'])) {
                throw new Exception('No s\'ha rebut la imatge');
            }

            if($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception($this->getUploadErrorMessage($_FILES['image']['error']));
            }

            //Extracció de les dades de la petició
            $imageFile = $_FILES['image']; //Imatge
            $editRequest = $_POST['editRequest'] ?? null; //Text
            $audioFile = $_FILES['audio'] ?? null; //Audio

            // Validem primer que hi hagi una petició
            if(empty($editRequest) && (!$audioFile || $audioFile['error'] !== UPLOAD_ERR_OK)) {
                throw new Exception('No s\'ha rebut la petició d\'edició');
            }

            //Deleguem el processament de la imatge a processImage()
            $result = $this->imageService->processImage($imageFile, $editRequest, $audioFile);

            //Retornem resposta JSON amb les dades necessàries pel frontend
            echo json_encode([
                'success' => true,
                'message' => 'Sol·licitud rebut correctament',
                'fileName' => $result['requestId'], //ID per fer el seguiment
                'imageUrl' => $result['imageUrl']
            ]);
            return ;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al pujar l\'arxiu: ' . $e->getMessage()
            ]);
            return;
        }
    }

    //Assignem errors amb text per fer debbugs durant la petició
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

    // checkStatus s'encarrega de verificar l'estat del processaament de la imatge.
    // Primer es valida el GET, extrau la requestID i es delega al ImageService 
    public function checkStatus() {
        try{
            if(ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            
            if($_SERVER['REQUEST_METHOD'] !== 'GET'){
                http_response_code(405); //Utilitzem error 405 per indicar que no s'accepta el permès
                echo json_encode([
                    'success' => false,
                    'message' => 'Mètode no permès'
                ]) ;
                return ;
            }

            // S'extrau la IDde la petició i es delega al moddel per verificar l'estat
            $requestId = $_GET['requestId'] ?? null;
            if(!$requestId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Es necessita la ID de la petició per obtenir la imatge editada en el bucket'
                ]);
                return;
            }

            $status = $this->imageService->checkImageStatus($requestId);

            //Aqui obtenim el retorn de l'estat del processament
            echo json_encode([
                'success' => true,
                'status' => $status['status'],
                'imageUrl' => $status['imageUrl']
            ]);
            return;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            return;
        }
    }
}
