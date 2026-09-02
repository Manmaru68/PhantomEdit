<?php
if(!defined('SECURE_ACCESS')) {
    header('Location: /');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ca">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale:1.0">
        <title>PhantomEdit</title>
        <link rel="stylesheet" href="/assets/css/style.css">
        <script src="/assets/js/main.js"></script>
    </head>
    <body>
        <div class="container">
            <div class="main-header">
                <img src="/assets/img/PhantomEdit.png" alt="PhantomEdit logo" class="logo">
                <h1>PhantomEdit</h1>
            </div>
            <div class="upload-section">
                <form id="uploadForm" enctype="multipart/form-data" method="post">
                    <div class="form-group">
                        <label for="image">Selecciona una imatge:</label>
                        <input type="file" id="image" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label>Com vols fer la teva petició?</label>
                        <div class="input-options">
                            <div class="option">
                                <input type="radio" id="textInput" name="inputType" value="text" checked>
                                <label for="textInput">Text</label>
                                <textarea id="textRequest" name="editRequest" placeholder="Descriu aqui els canvis que vols fer."></textarea>
                            </div>
                            <div class="option">
                                <input type="radio" id="audioInput" name="inputType" value="audio">
                                <label for="audioInput">Àudio</label>
                                <button type="button" id="recordButton" disabled>Enregistrar àudio</button>
                            </div>
                        </div>
                    </div>

                    <button type="submit">Processar imatge</button>
                </form>
            </div>

            <div class="preview-section">
                <h2>Vista previa</h2>
                <div class="preview-container">
                    <div class="preview-box">
                        <h3>Imatge Original</h3>
                        <div id="originalPreview" class="image-preview"></div>
                    </div>
                    <div class="preview-box">
                        <h3>Imatge Editada</h3>
                        <div id="editedPreview" class="image-preview"></div>
                    </div>
                </div>
                <div id="status"></div>
            </div>
        </div>
    </body>
</html>
