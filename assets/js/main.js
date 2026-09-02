document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('uploadForm');
    const fileInput  = document.getElementById('image');
    const textRequestInput = document.getElementById('textRequest');
    const recordButton  = document.getElementById('recordButton');
    const textRadio = document.getElementById('textInput');
    const audioRadio = document.getElementById('audioInput');
    const statusDiv = document.getElementById('status');
    const originalPreview = document.getElementById('originalPreview');
    const editedPreview = document.getElementById('editedPreview');

    // Aquestes variables seran les responsables de la gravació d'àudio
    let mediaRecorder;
    let audioChuncks = [];
    let isRecording = false;
    
    //Quan l'usuari seleccion text, desactivem el botó de l'àudio
    // i si està en plena gravació, ho aturem.
    textRadio.addEventListener('change', function() {
        textRequestInput.disabled = false;
        recordButton.disabled = true;
        if (isRecording) {
            stopRecording();
        }
    });

    // Quan l'usuari selecciona l'opció d'àudio, desactivem el text
    audioRadio.addEventListener('change', function(){
        textRequestInput.disabled = true;
        recordButton.disabled = false;
    });

    // Aquesta funció s'encarrega del botó d'àudio (Enregistrar àudio) per començar i
    // aturar la gravació
    recordButton.addEventListener('click', async function() {
        if (!isRecording) {
            try{
                // Sol·licitem el permís per utilitzar el micròfon
                const stream = await navigator.mediaDevices.getUserMedia({audio: true});
                mediaRecorder = new MediaRecorder(stream);
                audioChuncks = [];

                // Guardem la gravació
                mediaRecorder.ondataavailable = (event) => {
                    audioChuncks.push(event.data);
                };

                // Quan finalitza la gravació i donem click al botó de Enregistrar àudio
                // guardem la gravació en format .wav
                mediaRecorder.onstop = async () => {
                    const audioBlob = new Blob(audioChuncks, {type: 'audio/wav'});
                    const audioFile = new File([audioBlob], 'recording.wav', {type: 'audio/wav'});
                    
                    window.recordedAudioFile = audioFile;
                };

                // Inicia gravació
                mediaRecorder.start();
                isRecording = true;
            } catch (error) {
                alert('No es pot accedir al micròfon, si us plau, accepta els permisos per accedir al teu micròfon');
            }
        } else {
            stopRecording();
        }
        
    });

    // Quan s'atura la gravació, tanquem l'accès al micròfon
    function stopRecording() {
        if(mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
            isRecording = false;
        }
    }

    //Un cop l'usuari ha pujar l'arxiu des del seu directori a la pàgina
    //Mostrem la imatge original en la vista prèvia
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if(file) {
            const reader = new FileReader();
            //Carreguem la imatge a la vista prèvia
            reader.onload = function(e) {
                originalPreview.innerHTML = `<img src="${e.target.result}" alt="Vista prèvia">`;
            }
            reader.readAsDataURL(file);
        }
    });

    //Fem la petició de processar la imatge
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        //Validem que hi hagi imatge seleccionada
        if(!fileInput.files[0]) {
            alert('Si us plau, selecciona una imatge');
            return
        }

        const formData = new FormData();
        formData.append('image', fileInput.files[0]);

        //Afegim les instruccions segons l'opció de petició seleccionat
        if(textRadio.checked) {
            if(!textRequestInput.value.trim()) {
                alert('Si us plau, fes una petició per editar la imatge');
                return;
            }
            formData.append('editRequest', textRequestInput.value.trim());
        } else if (audioRadio.checked) {
            if(!window.recordedAudioFile) {
                alert('Si us plau, fes una gravació d\'àudio');
                return;
            }
            formData.append('audio', window.recordedAudioFile);
        }

        // Mostrem missatges sobre l'estat del processament
        statusDiv.textContent = 'Processant imatge...';
        statusDiv.className = 'status-message processing';

        //Enviem la petició
        try{
            const response = await fetch('/upload', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if(!response.ok) {
                throw new Error(data.message || 'Error en la petició');
            }

            //Si la imatge s'ha pujat al bucket, mostrem missatge i comencem
            //a consultar el bucket d'imatges editades per veure si ja està llest l'edició
            //de la imatge
            if(data.success) {
                statusDiv.textContent = 'Imatge pujada correctament al bucket. Processant imatge...';
                pollForResult(data.fileName);
            } else {
                throw new Error(data.message || 'Error no s\'ha pogut pujar la imatge');
            }
        } catch(e) {
            statusDiv.textContent = `Error: ${e.message}`;
            statusDiv.className = 'status-message error';
        }
    });

    // Aquesta funció és l'encarrega de comprovar cada 2 segons si la imatge editada
    // està llesta.
    async function pollForResult(requestId) {
        const pollInterval = setInterval(async() => {
            try {
                // Demanem l'estat del processament
                const response = await fetch(`/checkStatus?requestId=${requestId}`);
                const data = await response.json();

                //Un cop tenim la imatge editada, ho mostrem al costat de la imatge original per fer comparacions
                if(data.status === 'completed') {
                    clearInterval(pollInterval);
                    editedPreview.innerHTML = `<img src="${data.imageUrl}" alt="Imatge editada">`;
                    statusDiv.textContent = 'Processament completat amb èxit';
                    statusDiv.className = 'status-message success';
                } else if(data.status === 'processing') {
                    // Mentres es vaig consultant sobre la imatge editada
                    // es va mostrant aquest missatge a l'usuari
                    statusDiv.textContent ='Processant imatge...';
                    statusDiv.className = 'status-message processing';
                }
            } catch (e) {
                clearInterval(pollInterval);
                statusDiv.textContent = `Error: ${e.message}`;
                statusDiv.className = 'status-message error';
            }
        }, 2000); //Cada quan temps s'ha de consultar
    }

});