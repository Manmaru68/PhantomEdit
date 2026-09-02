<?php
//Inicialitzem la sessió
session_start();
require_once __DIR__ .'/config/config.php';
require_once __DIR__ .'/controllers/ImageController.php';

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = '/';

$route = trim(str_replace($base_path,'',$request_uri), '/');

//Instància per gestionar les peticions
$controller = new ImageController();

// Monitorització de rutes
switch($route) {
    case '':
        $controller -> index();
        break;
    case 'upload':
        $controller -> upload();
        break;
    case 'checkStatus':
        $controller -> checkStatus();
        break;
    default:
        header("HTTP/1.0 404 NOT FOUND");
        echo "Error HTTP: 404 Not Found | Pàgina no trobada";
        break;
}