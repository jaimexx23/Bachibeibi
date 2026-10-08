let lastScanTime = 0;
let lastCode = "";
let qrScanner = null;

/*
|--------------------------------------------------------------------------
| EXTRAER CÓDIGO DEL ALUMNO
|--------------------------------------------------------------------------
|
| Acepta:
| - 202207061
| - ALUMNO: 202207061
| - https://tudominio.com/qr/202207061
| - URLs con ?code=202207061
|
*/
function extractStudentCode(rawText) {
    const text = String(rawText || "").trim();

    if (!text) {
        return "";
    }

    /*
     * Si el QR contiene una URL.
     */
    try {
        if (text.includes("://")) {
            const url = new URL(text);

            const fromQuery =
                url.searchParams.get("code") ||
                url.searchParams.get("student_code") ||
                url.searchParams.get("alumno") ||
                url.searchParams.get("id");

            if (fromQuery) {
                return fromQuery
                    .trim()
                    .toUpperCase();
            }

            const parts = url.pathname
                .split("/")
                .filter(Boolean);

            if (parts.length > 0) {
                return parts[
                    parts.length - 1
                ]
                    .trim()
                    .toUpperCase();
            }
        }
    } catch (error) {
        console.warn(
            "No se pudo interpretar el QR como URL:",
            error
        );
    }

    /*
     * Formatos:
     * ALUMNO: ABC123
     * CODE=ABC123
     */
    const prefixed = text.match(
        /(?:ALUMNO|STUDENT(?:_CODE)?|CODIGO|CODE)\s*[:=\-]\s*([A-Z0-9\-_.]+)/i
    );

    if (prefixed) {
        return prefixed[1]
            .trim()
            .toUpperCase();
    }

    /*
     * Código simple.
     */
    const token = text
        .toUpperCase()
        .match(
            /[A-Z0-9][A-Z0-9\-_.]{1,}/
        );

    return token
        ? token[0]
            .trim()
            .toUpperCase()
        : "";
}


/*
|--------------------------------------------------------------------------
| OBTENER CAJA DE RESULTADOS
|--------------------------------------------------------------------------
*/

function getResultBox() {
    return document.getElementById(
        "result"
    );
}


/*
|--------------------------------------------------------------------------
| MOSTRAR RESULTADO
|--------------------------------------------------------------------------
*/

function showResult(
    message,
    type = "info"
) {
    const resultBox =
        getResultBox();

    if (!resultBox) {
        console.log(
            type,
            message
        );

        return;
    }

    resultBox.innerHTML = `
        <div class="${type}">
            ${message}
        </div>
    `;
}


/*
|--------------------------------------------------------------------------
| ESTADO VISUAL DEL ESCÁNER
|--------------------------------------------------------------------------
*/

function setReaderState(state) {
    const reader =
        document.getElementById(
            "reader"
        );

    if (!reader) {
        return;
    }

    reader.classList.remove(
        "scan-success",
        "scan-duplicate",
        "scan-error"
    );

    if (state === "success") {
        reader.classList.add(
            "scan-success"
        );
    }

    if (state === "duplicate") {
        reader.classList.add(
            "scan-duplicate"
        );
    }

    if (state === "error") {
        reader.classList.add(
            "scan-error"
        );
    }

    if (state) {
        setTimeout(() => {
            reader.classList.remove(
                "scan-success",
                "scan-duplicate",
                "scan-error"
            );
        }, 2500);
    }
}


/*
|--------------------------------------------------------------------------
| MENSAJE SOBRE EL ESCÁNER
|--------------------------------------------------------------------------
*/

function showScanFeedback(
    message,
    type = "info"
) {
    const feedback =
        document.getElementById(
            "scanFeedback"
        );

    if (!feedback) {
        return;
    }

    feedback.className =
        "scan-feedback";

    if (
        type === "success" ||
        type === "ok"
    ) {
        feedback.classList.add(
            "ok"
        );
    }

    if (type === "duplicate") {
        feedback.classList.add(
            "duplicate"
        );
    }

    if (type === "error") {
        feedback.classList.add(
            "error"
        );
    }

    feedback.textContent =
        String(message || "");

    feedback.classList.add(
        "visible"
    );

    setTimeout(() => {
        feedback.classList.remove(
            "visible"
        );
    }, 3000);
}


/*
|--------------------------------------------------------------------------
| ÚLTIMO ALUMNO ESCANEADO
|--------------------------------------------------------------------------
*/

function updateLastScanned(
    name,
    time
) {
    const element =
        document.getElementById(
            "lastScanned"
        );

    if (!element) {
        return;
    }

    element.hidden = false;

    element.textContent =
        name +
        (
            time
                ? " — " + time
                : ""
        );
}


/*
|--------------------------------------------------------------------------
| TOKEN CSRF
|--------------------------------------------------------------------------
*/

function getCsrfToken() {
    /*
     * Primero intenta obtenerlo desde:
     *
     * <input id="csrf_token">
     */
    const hidden =
        document.getElementById(
            "csrf_token"
        );

    if (
        hidden &&
        hidden.value
    ) {
        return hidden.value;
    }

    /*
     * También acepta:
     *
     * <meta name="csrf-token">
     */
    const meta =
        document.querySelector(
            'meta[name="csrf-token"]'
        );

    if (meta) {
        return (
            meta.getAttribute(
                "content"
            ) || ""
        );
    }

    return "";
}


/*
|--------------------------------------------------------------------------
| ENVIAR ASISTENCIA A LARAVEL
|--------------------------------------------------------------------------
*/

async function sendCheckin(
    code,
    source = "qr"
) {
    const normalizedCode =
        extractStudentCode(code);

    /*
     * Código inválido.
     */
    if (!normalizedCode) {
        showResult(
            "No se pudo obtener un código válido.",
            "error"
        );

        showScanFeedback(
            "Código QR no válido",
            "error"
        );

        setReaderState(
            "error"
        );

        return;
    }

    /*
     * Evitar lecturas repetidas
     * muy rápidas de la cámara.
     */
    const now =
        Date.now();

    if (
        source === "qr" &&
        normalizedCode === lastCode &&
        now - lastScanTime < 4000
    ) {
        return;
    }

    lastCode =
        normalizedCode;

    lastScanTime =
        now;

    console.log(
        "Código detectado:",
        normalizedCode
    );

    showResult(
        `
        QR detectado:
        <strong>
            ${normalizedCode}
        </strong>
        <br>
        Registrando asistencia...
        `,
        "info"
    );

    showScanFeedback(
        `Detectado: ${normalizedCode}`,
        "info"
    );

    /*
     * Obtener CSRF.
     */
    const csrfToken =
        getCsrfToken();

    if (!csrfToken) {
        console.error(
            "No se encontró token CSRF."
        );

        showResult(
            "No se encontró el token CSRF. Recarga la página.",
            "error"
        );

        showScanFeedback(
            "Error de seguridad",
            "error"
        );

        setReaderState(
            "error"
        );

        return;
    }

    try {
        /*
         * Petición a Laravel.
         */
        const response =
            await fetch(
                "/api/checkin",
                {
                    method:
                        "POST",

                    credentials:
                        "same-origin",

                    headers: {
                        "Content-Type":
                            "application/json",

                        "Accept":
                            "application/json",

                        "X-Requested-With":
                            "XMLHttpRequest",

                        "X-CSRF-TOKEN":
                            csrfToken
                    },

                    body:
                        JSON.stringify({
                            student_code:
                                normalizedCode,

                            source:
                                source
                        })
                }
            );

        /*
         * Si Laravel nos redirigió,
         * posiblemente perdió la sesión.
         */
        if (
            response.redirected
        ) {
            console.error(
                "Check-in redirigido:",
                response.url
            );

            showResult(
                `
                La sesión del escáner
                ya no es válida.
                <br>
                Cierra sesión e inicia
                nuevamente.
                `,
                "error"
            );

            showScanFeedback(
                "Sesión no válida",
                "error"
            );

            setReaderState(
                "error"
            );

            return;
        }

        /*
         * Detectar respuesta JSON.
         */
        const contentType =
            response.headers.get(
                "content-type"
            ) || "";

        let data = null;
        let rawText = "";

        if (
            contentType.includes(
                "application/json"
            )
        ) {
            try {
                data =
                    await response.json();
            } catch (error) {
                console.error(
                    "Error leyendo JSON:",
                    error
                );
            }

        } else {
            rawText =
                await response.text();
        }

        /*
         * Esto es MUY IMPORTANTE
         * para depurar desde F12.
         */
        console.log(
            "Respuesta /api/checkin:",
            {
                status:
                    response.status,

                ok:
                    response.ok,

                data:
                    data,

                rawText:
                    rawText
            }
        );


        /*
        |--------------------------------------------------------------------------
        | 401 / 403
        | Sesión no autorizada
        |--------------------------------------------------------------------------
        */

        if (
            response.status === 401 ||
            response.status === 403
        ) {
            const message =
                data?.message ||
                "Sesión no autorizada.";

            showResult(
                message,
                "error"
            );

            showScanFeedback(
                message,
                "error"
            );

            setReaderState(
                "error"
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | 419
        | Token CSRF / sesión expirada
        |--------------------------------------------------------------------------
        */

        if (
            response.status === 419
        ) {
            showResult(
                `
                La sesión o el token
                de seguridad expiró.
                <br>
                Recarga la página e
                inicia sesión nuevamente.
                `,
                "error"
            );

            showScanFeedback(
                "Sesión expirada",
                "error"
            );

            setReaderState(
                "error"
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | 409
        | Asistencia ya registrada
        |--------------------------------------------------------------------------
        */

        if (
            response.status === 409
        ) {
            const studentName =
                data?.student ||
                normalizedCode;

            const time =
                data?.time ||
                data?.date ||
                "";

            showResult(
                `
                <strong>
                    ${studentName}
                </strong>
                ya tiene asistencia
                registrada hoy.
                ${
                    time
                        ? "<br>Hora: " +
                          time
                        : ""
                }
                `,
                "info"
            );

            showScanFeedback(
                `${studentName} ya fue registrado`,
                "duplicate"
            );

            updateLastScanned(
                studentName,
                time
            );

            setReaderState(
                "duplicate"
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | 404
        | Alumno no encontrado
        |--------------------------------------------------------------------------
        */

        if (
            response.status === 404
        ) {
            const message =
                data?.message ||
                "Alumno no registrado.";

            showResult(
                `
                ${message}
                <br>
                Código:
                <strong>
                    ${normalizedCode}
                </strong>
                `,
                "error"
            );

            showScanFeedback(
                "Alumno no registrado",
                "error"
            );

            setReaderState(
                "error"
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | OTROS ERRORES
        |--------------------------------------------------------------------------
        */

        if (!response.ok) {
            const message =
                data?.message ||
                rawText ||
                `Error HTTP ${response.status}`;

            showResult(
                message,
                "error"
            );

            showScanFeedback(
                message,
                "error"
            );

            setReaderState(
                "error"
            );

            console.error(
                "Error check-in:",
                {
                    status:
                        response.status,

                    data:
                        data,

                    rawText:
                        rawText
                }
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | CONFIRMACIÓN REAL
        |--------------------------------------------------------------------------
        |
        | No consideramos una respuesta exitosa
        | solamente porque sea HTTP 200.
        |
        | Laravel debe responder:
        |
        | "ok": true
        |
        */

        if (
            !data ||
            data.ok !== true
        ) {
            showResult(
                `
                El servidor respondió,
                pero no confirmó el
                registro de asistencia.
                `,
                "error"
            );

            showScanFeedback(
                "Registro no confirmado",
                "error"
            );

            setReaderState(
                "error"
            );

            console.error(
                "Respuesta inesperada:",
                data,
                rawText
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | ASISTENCIA REGISTRADA
        |--------------------------------------------------------------------------
        */

        const studentName =
            data.student ||
            normalizedCode;

        const classroom =
            data.classroom || "";

        const time =
            data.time ||
            data.date ||
            "";

        showResult(
            `
            Asistencia registrada
            correctamente.
            <br>

            <strong>
                ${studentName}
            </strong>

            ${
                classroom
                    ? " — " +
                      classroom
                    : ""
            }

            ${
                time
                    ? "<br>Hora: " +
                      time
                    : ""
            }
            `,
            "ok"
        );

        showScanFeedback(
            `¡Asistencia registrada — ${studentName}!`,
            "success"
        );

        updateLastScanned(
            studentName,
            time
        );

        setReaderState(
            "success"
        );

    } catch (error) {
        console.error(
            "Error enviando check-in:",
            error
        );

        showResult(
            `
            No se pudo comunicar con
            el servidor.
            <br>
            Revisa tu conexión o vuelve
            a iniciar sesión.
            `,
            "error"
        );

        showScanFeedback(
            "Error de conexión",
            "error"
        );

        setReaderState(
            "error"
        );
    }
}


/*
|--------------------------------------------------------------------------
| QR DETECTADO POR LA CÁMARA
|--------------------------------------------------------------------------
*/

function onScanSuccess(
    decodedText
) {
    console.log(
        "QR DETECTADO:",
        decodedText
    );

    sendCheckin(
        decodedText,
        "qr"
    );
}


/*
|--------------------------------------------------------------------------
| ERROR DE LECTURA
|--------------------------------------------------------------------------
|
| No mostramos mensajes por cada frame.
| Es normal que la cámara intente leer muchas veces.
|
*/

function onScanFailure(
    error
) {
    // Intencionalmente vacío.
}


/*
|--------------------------------------------------------------------------
| INICIALIZAR CÁMARA
|--------------------------------------------------------------------------
*/

function initializeScanner() {
    const reader =
        document.getElementById(
            "reader"
        );

    /*
     * scan.js se carga desde layouts.app,
     * incluso en páginas que no tienen scanner.
     */
    if (!reader) {
        return;
    }

    /*
     * Verificar librería.
     */
    if (
        typeof Html5QrcodeScanner ===
        "undefined"
    ) {
        console.error(
            "Html5QrcodeScanner no está disponible."
        );

        showResult(
            `
            No se pudo cargar la
            librería del escáner QR.
            `,
            "error"
        );

        return;
    }

    /*
     * NO usamos directamente #reader.
     *
     * Creamos un DIV interno exclusivo
     * para html5-qrcode.
     *
     * Esto evita que la librería elimine
     * scanFeedback e instrucciones.
     */
    let scannerHost =
        document.getElementById(
            "qr-reader-live"
        );

    if (!scannerHost) {
        scannerHost =
            document.createElement(
                "div"
            );

        scannerHost.id =
            "qr-reader-live";

        reader.appendChild(
            scannerHost
        );
    }

    try {
        qrScanner =
            new Html5QrcodeScanner(
                "qr-reader-live",
                {
                    /*
                     * Frames por segundo.
                     */
                    fps: 10,

                    /*
                     * Área de lectura.
                     */
                    qrbox: {
                        width: 250,
                        height: 250
                    },

                    /*
                     * Recordar cámara usada.
                     */
                    rememberLastUsedCamera:
                        true,

                    /*
                     * Mostrar linterna cuando
                     * el teléfono la soporte.
                     */
                    showTorchButtonIfSupported:
                        true,

                    /*
                     * Preferir cámara trasera.
                     */
                    videoConstraints: {
                        facingMode: {
                            ideal:
                                "environment"
                        }
                    }
                },

                false
            );

        qrScanner.render(
            onScanSuccess,
            onScanFailure
        );

        console.log(
            "Escáner QR iniciado correctamente."
        );

    } catch (error) {
        console.error(
            "Error iniciando escáner:",
            error
        );

        showResult(
            `
            No se pudo iniciar
            la cámara.
            `,
            "error"
        );
    }
}


/*
|--------------------------------------------------------------------------
| ENTRADA MANUAL
|--------------------------------------------------------------------------
*/

window.manualCheckin =
    function manualCheckin() {
        const input =
            document.getElementById(
                "manualCode"
            );

        if (!input) {
            return;
        }

        const code =
            input.value.trim();

        if (!code) {
            showResult(
                `
                Escribe un número
                de cuenta o código.
                `,
                "error"
            );

            return;
        }

        /*
         * Enviar manualmente.
         */
        sendCheckin(
            code,
            "manual"
        );

        /*
         * Limpiar campo.
         */
        input.value = "";
    };


/*
|--------------------------------------------------------------------------
| LEER QR DESDE UNA IMAGEN
|--------------------------------------------------------------------------
*/

async function scanQrFile(
    file
) {
    if (
        typeof Html5Qrcode ===
        "undefined"
    ) {
        showResult(
            `
            La librería de QR
            no está disponible.
            `,
            "error"
        );

        return;
    }

    /*
     * Crear contenedor oculto
     * para procesar archivo.
     */
    let fileHost =
        document.getElementById(
            "qr-file-reader"
        );

    if (!fileHost) {
        fileHost =
            document.createElement(
                "div"
            );

        fileHost.id =
            "qr-file-reader";

        fileHost.style.display =
            "none";

        document.body.appendChild(
            fileHost
        );
    }

    let fileScanner = null;

    try {
        showResult(
            `
            Analizando imagen QR...
            `,
            "info"
        );

        fileScanner =
            new Html5Qrcode(
                "qr-file-reader"
            );

        /*
         * scanFile es método de la instancia.
         */
        const decodedText =
            await fileScanner.scanFile(
                file,
                true
            );

        console.log(
            "QR detectado desde imagen:",
            decodedText
        );

        await sendCheckin(
            decodedText,
            "image"
        );

    } catch (error) {
        console.error(
            "Error leyendo imagen QR:",
            error
        );

        showResult(
            `
            No se encontró un código
            QR válido en la imagen.
            `,
            "error"
        );

    } finally {
        if (fileScanner) {
            try {
                fileScanner.clear();
            } catch (error) {
                // No hacer nada.
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| CUANDO CARGA LA PÁGINA
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {

        console.log(
            "scan.js cargado correctamente"
        );

        /*
         * Inicializar cámara
         * solamente si estamos en /scan.
         */
        initializeScanner();


        /*
        |--------------------------------------------------------------------------
        | BOTÓN MANUAL
        |--------------------------------------------------------------------------
        */

        const manualButton =
            document.querySelector(
                'button[onclick="manualCheckin()"]'
            );

        if (manualButton) {
            /*
             * Quitamos el onclick HTML
             * y usamos addEventListener.
             */
            manualButton.removeAttribute(
                "onclick"
            );

            manualButton.addEventListener(
                "click",
                function () {
                    window.manualCheckin();
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ENTER EN ENTRADA MANUAL
        |--------------------------------------------------------------------------
        */

        const manualInput =
            document.getElementById(
                "manualCode"
            );

        if (manualInput) {
            manualInput.addEventListener(
                "keydown",
                function (event) {
                    if (
                        event.key ===
                        "Enter"
                    ) {
                        event.preventDefault();

                        window.manualCheckin();
                    }
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SUBIR IMAGEN QR
        |--------------------------------------------------------------------------
        */

        const qrFileInput =
            document.getElementById(
                "qrFileInput"
            );

        const scanFileButton =
            document.getElementById(
                "scanFileBtn"
            );

        if (
            qrFileInput &&
            scanFileButton
        ) {
            /*
             * Abrir selector.
             */
            scanFileButton.addEventListener(
                "click",
                function (event) {
                    event.preventDefault();

                    qrFileInput.click();
                }
            );

            /*
             * Procesar archivo.
             */
            qrFileInput.addEventListener(
                "change",
                async function () {
                    const file =
                        qrFileInput
                            .files?.[0];

                    if (!file) {
                        return;
                    }

                    await scanQrFile(
                        file
                    );

                    /*
                     * Permitir volver a
                     * seleccionar el mismo archivo.
                     */
                    qrFileInput.value =
                        "";
                }
            );
        }
    }
);