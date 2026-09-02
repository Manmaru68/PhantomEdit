# PhantomEdit

> Aplicación web de edición de imágenes mediante instrucciones en lenguaje natural, con soporte para entrada por texto o audio y procesamiento asíncrono mediante servicios cloud.

![PhantomEdit](assets/img/PhantomEdit.png)

## Descripción

**PhantomEdit** es una aplicación web desarrollada en **PHP** que permite subir una imagen y solicitar modificaciones sobre ella mediante una instrucción escrita o una grabación de audio.

La aplicación gestiona todo el flujo de edición: recibe y valida los archivos, los almacena en **Google Cloud Storage**, crea una petición de procesamiento y consulta de forma asíncrona su estado hasta que la imagen editada está disponible.

El proyecto está organizado separando la lógica HTTP, la lógica de negocio y la gestión del almacenamiento, con un frontend desarrollado en **HTML, CSS y JavaScript**.

---

## Características

* Subida de imágenes desde el navegador.
* Vista previa de la imagen antes de procesarla.
* Instrucciones de edición mediante texto.
* Instrucciones mediante grabación de audio.
* Almacenamiento de archivos mediante **Google Cloud Storage**.
* Procesamiento asíncrono de las solicitudes.
* Identificación de cada solicitud mediante un `requestId` único.
* Consulta periódica del estado de procesamiento.
* Visualización de la imagen original y del resultado.
* API HTTP para crear solicitudes y consultar su estado.
* Validación de tipos y tamaños de archivo.

---

## Arquitectura

```text
                         ┌──────────────────────┐
                         │       FRONTEND       │
                         │   HTML / CSS / JS    │
                         └──────────┬───────────┘
                                    │
                                    │ POST /upload
                                    ▼
                         ┌──────────────────────┐
                         │  ImageController     │
                         │                      │
                         │ HTTP + Routing       │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │    ImageService     │
                         │                      │
                         │ Validación           │
                         │ Lógica de negocio    │
                         │ Procesamiento        │
                         └───────┬───────┬──────┘
                                 │       │
                       Storage   │       │ HTTP / JSON
                                 │       ▼
                                 │  ┌──────────────────┐
                                 │  │ Servicio externo │
                                 │  │ de procesamiento │
                                 │  └────────┬─────────┘
                                 │           │
                                 ▼           │
                         ┌────────────────────────┐
                         │ Google Cloud Storage   │
                         │                        │
                         │ • Imágenes originales  │
                         │ • Audio                │
                         │ • Imágenes editadas    │
                         └────────────────────────┘

                                    ▲
                                    │
                              GET /checkStatus
                                    │
                                    │
                         ┌──────────┴───────────┐
                         │   Frontend Polling   │
                         │      cada 2 s        │
                         └──────────────────────┘
```

### Componentes principales

| Componente               | Responsabilidad                                      |
| ------------------------ | ---------------------------------------------------- |
| `index.php`              | Punto de entrada y routing de la aplicación          |
| `ImageController.php`    | Gestión de las peticiones HTTP                       |
| `ImageService.php`       | Lógica principal del procesamiento                   |
| `GoogleCloudStorage.php` | Gestión de archivos en Google Cloud Storage          |
| `views/index.php`        | Interfaz principal                                   |
| `assets/js/main.js`      | Interacción con el usuario y comunicación con la API |
| `assets/css/style.css`   | Estilos de la aplicación                             |
| `config/`                | Configuración                                        |

---

## Flujo de funcionamiento

### 1. Selección de imagen

El usuario selecciona una imagen desde la interfaz.

El frontend genera una **vista previa local** antes de realizar cualquier subida.

---

### 2. Introducción de la instrucción

El usuario puede indicar qué quiere modificar de dos formas:

**Texto**

```text
Make the sky look like sunset
```

**Audio**

El usuario activa el micrófono y graba la instrucción directamente desde el navegador.

Para ello se utiliza la **MediaRecorder API**.

---

### 3. Creación de la petición

La información se envía al backend mediante:

```http
POST /upload
```

utilizando `multipart/form-data`.

La petición contiene la imagen y la instrucción correspondiente.

---

### 4. Validación

Antes de procesar los archivos, el backend comprueba sus características.

Actualmente se contemplan:

**Imágenes**

* JPEG
* JPG
* PNG
* BMP

**Audio**

* MPEG
* WAV

El tamaño máximo configurado para los archivos es de aproximadamente **5 MB**.

---

### 5. Almacenamiento

Los archivos recibidos se almacenan en **Google Cloud Storage**.

La aplicación separa esta responsabilidad en:

```text
GoogleCloudStorage.php
```

de forma que la lógica de almacenamiento no queda mezclada con la lógica de negocio.

---

### 6. Procesamiento asíncrono

Cada petición recibe un identificador único:

```text
requestId
```

Este identificador permite asociar posteriormente el resultado con la petición original.

La aplicación envía la información necesaria al servicio externo de procesamiento mediante HTTP/JSON.

Un ejemplo conceptual de la petición es:

```json
{
  "requestId": "...",
  "imageRef": "...",
  "audioRef": "...",
  "text": "..."
}
```

El procesamiento se realiza de forma **asíncrona**, evitando mantener bloqueada la petición HTTP original.

---

### 7. Consulta del estado

Mientras la imagen está siendo procesada, el frontend consulta periódicamente:

```http
GET /checkStatus?requestId=<requestId>
```

Cada petición devuelve el estado actual.

Durante el procesamiento:

```json
{
  "success": true,
  "status": "processing",
  "imageUrl": null
}
```

Cuando termina:

```json
{
  "success": true,
  "status": "completed",
  "imageUrl": "..."
}
```

Finalmente, la interfaz muestra la imagen editada.

---

## Estructura del proyecto

```text
PhantomEdit/
│
├── assets/
│   ├── css/
│   │   └── style.css
│   │
│   ├── img/
│   │   └── PhantomEdit.png
│   │
│   └── js/
│       └── main.js
│
├── config/
│   ├── config.php
│   └── google_cloud.php
│   
│
├── controllers/
│   └── ImageController.php
│
├── models/
│   ├── GoogleCloudStorage.php
│   └── ImageService.php
│
├── views/
│   └── index.php
│
├── composer.json
├── index.php
└── README.md
```

---

## Tecnologías

### Backend

* **PHP 8.4**
* **Composer**
* **cURL**
* Arquitectura basada en servicios

### Frontend

* **HTML5**
* **CSS3**
* **JavaScript**
* **MediaRecorder API**
* **FileReader API**

### Cloud

* **Google Cloud Storage**
* Servicio externo de procesamiento de imágenes
* Comunicación mediante HTTP/JSON

---

## Requisitos

Para ejecutar PhantomEdit localmente necesitas:

* PHP **8.4** o compatible.
* Composer.
* Extensión PHP `curl`.
* Extensión PHP `fileinfo`.
* Extensión PHP `openssl`.
* Un proyecto de Google Cloud.
* Un bucket de Google Cloud Storage.
* Credenciales de servicio de Google Cloud.
* Acceso al servicio externo de procesamiento.

Para utilizar la funcionalidad de audio, el navegador debe soportar `MediaRecorder` y disponer de acceso al micrófono.

---

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/Manmaru68/PhantomEdit.git
cd PhantomEdit
```

### 2. Instalar dependencias

```bash
composer install
```

La principal dependencia del proyecto es:

```json
{
  "require": {
    "google/cloud-storage": "^1.30"
  }
}
```

### 3. Configurar Google Cloud

Configura las credenciales necesarias para acceder a Google Cloud Storage.

---

### 4. Configurar PHP

Comprueba que PHP está correctamente instalado:

```bash
php --version
```

Y que las extensiones necesarias están habilitadas:

```ini
extension=curl
extension=fileinfo
extension=openssl
```

Se recomienda disponer de una configuración similar a:

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

---

### 5. Ejecutar el servidor

Desde la raíz del proyecto:

```bash
php -S localhost:8000
```

La aplicación estará disponible en:

```text
http://localhost:8000
```

---

## API

PhantomEdit utiliza una API HTTP sencilla para gestionar las solicitudes de edición.

### `GET /`

Muestra la aplicación web.

---

### `POST /upload`

Crea una nueva solicitud de edición.

Utiliza:

```text
multipart/form-data
```

y puede recibir:

| Campo         | Descripción                           |
| ------------- | ------------------------------------- |
| `image`       | Imagen que se quiere editar           |
| `editRequest` | Instrucción de edición mediante texto |
| `audio`       | Instrucción de edición mediante audio |

La respuesta contiene información que permite al frontend realizar el seguimiento de la solicitud.

Ejemplo:

```json
{
  "success": true,
  "message": "Sol·licitud rebut correctament",
  "fileName": "request-id",
  "imageUrl": "..."
}
```

---

### `GET /checkStatus`

Consulta el estado de una solicitud.

```text
GET /checkStatus?requestId=<requestId>
```

Estados principales:

```text
processing
completed
```

Ejemplo durante el procesamiento:

```json
{
  "success": true,
  "status": "processing",
  "imageUrl": null
}
```

Ejemplo al finalizar:

```json
{
  "success": true,
  "status": "completed",
  "imageUrl": "..."
}
```

---

## Decisiones técnicas

### Procesamiento asíncrono

Una de las decisiones principales del proyecto es desacoplar la petición HTTP del procesamiento de la imagen.

En lugar de mantener la conexión abierta mientras el servicio externo procesa la imagen, se genera un `requestId` y el frontend consulta posteriormente el estado.

Esto permite:

* Evitar conexiones HTTP largas.
* Separar la interfaz del procesamiento.
* Gestionar operaciones que pueden tardar varios segundos.
* Mostrar estados intermedios al usuario.

---

### Separación de responsabilidades

La aplicación divide sus responsabilidades entre diferentes componentes:

```text
Controller
    ↓
Service
    ↓
Storage / External API
```

Por ejemplo:

```text
ImageController
        ↓
ImageService
        ↓
GoogleCloudStorage
```

Esto facilita el mantenimiento y permite modificar una parte de la aplicación sin tener que rehacer el resto.

---

### Entrada multimodal

PhantomEdit no limita la interacción a texto.

El usuario puede proporcionar la instrucción mediante:

```text
Texto ───────┐
             ├──> ImageService ──> Procesamiento
Audio ───────┘
```

La utilización de `MediaRecorder` permite capturar directamente el audio desde el navegador sin necesidad de una aplicación externa.

---

## Dependencias

Las dependencias PHP se gestionan mediante Composer.

```bash
composer install
```

Dependencia principal:

```text
google/cloud-storage
```

---

## Estado del proyecto

PhantomEdit implementa el flujo completo de una aplicación de edición de imágenes basada en instrucciones del usuario:

```text
Imagen
  ↓
Instrucción
  ↓
Validación
  ↓
Google Cloud Storage
  ↓
Servicio de procesamiento
  ↓
Procesamiento asíncrono
  ↓
Consulta de estado
  ↓
Imagen editada
```

---
