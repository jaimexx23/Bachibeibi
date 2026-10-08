@extends('layouts.app')

@section('content')

<style>
    /*
    |--------------------------------------------------------------------------
    | PALETA
    |--------------------------------------------------------------------------
    */

    :root {
        --scan-green: #166534;
        --scan-green-soft: #22c55e;
        --scan-green-light: #dcfce7;
        --scan-green-extra: #f0fdf4;

        --scan-gold: #c59a45;
        --scan-gold-light: #faf4e8;

        --scan-text: #1f2937;
        --scan-muted: #6b7280;

        --scan-border: #e7eee9;
        --scan-bg: #ffffff;
        --scan-bg-soft: #fafcfb;

        --scan-error: #b91c1c;
        --scan-error-light: #fef2f2;

        --scan-warning: #a16207;
        --scan-warning-light: #fffbeb;
    }


    /*
    |--------------------------------------------------------------------------
    | PÁGINA
    |--------------------------------------------------------------------------
    */

    .scanner-page {
        width: 100%;
        max-width: 1120px;

        margin: 0 auto;

        padding: 24px 18px 40px;

        box-sizing: border-box;

        position: relative;
    }


    /*
    |--------------------------------------------------------------------------
    | ENCABEZADO
    |--------------------------------------------------------------------------
    */

    .scanner-page-header {
        text-align: center;

        margin-bottom: 28px;

        animation:
            scannerFadeDown .7s ease both;
    }

    .scanner-page-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;

        padding: 7px 13px;

        border-radius: 999px;

        background: var(--scan-green-extra);

        color: var(--scan-green);

        font-size: 12px;
        font-weight: 700;

        letter-spacing: .07em;
        text-transform: uppercase;

        margin-bottom: 13px;

        border: 1px solid #d9f3df;
    }

    .scanner-page-badge::before {
        content: "";

        width: 8px;
        height: 8px;

        border-radius: 50%;

        background: var(--scan-green-soft);

        box-shadow:
            0 0 0 0 rgba(34, 197, 94, .4);

        animation:
            scannerPulseDot 2s infinite;
    }

    .scanner-page-title {
        margin: 0;

        color: var(--scan-text);

        font-size: clamp(
            1.65rem,
            4vw,
            2.35rem
        );

        font-weight: 750;

        letter-spacing: -.03em;
    }

    .scanner-page-description {
        max-width: 620px;

        margin:
            10px auto
            0;

        color: var(--scan-muted);

        font-size: 15px;
        line-height: 1.6;
    }


    /*
    |--------------------------------------------------------------------------
    | GRID
    |--------------------------------------------------------------------------
    */

    .scanner-grid {
        display: grid;

        grid-template-columns:
            minmax(0, 1.3fr)
            minmax(280px, .7fr);

        gap: 24px;

        align-items: start;
    }


    /*
    |--------------------------------------------------------------------------
    | TARJETAS
    |--------------------------------------------------------------------------
    */

    .scanner-card {
        position: relative;

        min-width: 0;

        padding: 24px;

        overflow: hidden;

        background:
            linear-gradient(
                145deg,
                #ffffff 0%,
                #ffffff 72%,
                #fbfdfb 100%
            );

        border:
            1px solid
            var(--scan-border);

        border-radius: 24px;

        box-shadow:
            0 18px 50px
            rgba(41, 65, 49, .08);

        animation:
            scannerCardIn
            .7s
            cubic-bezier(.2,.8,.2,1)
            both;
    }

    .scanner-card:nth-child(2) {
        animation-delay: .12s;
    }

    /*
     * Detalle decorativo superior.
     */
    .scanner-card::before {
        content: "";

        position: absolute;

        top: -80px;
        right: -80px;

        width: 190px;
        height: 190px;

        border-radius: 50%;

        background:
            radial-gradient(
                circle,
                rgba(34,197,94,.10),
                rgba(34,197,94,0)
                70%
            );

        pointer-events: none;
    }

    .scanner-header {
        position: relative;

        z-index: 2;

        margin-bottom: 18px;
    }

    .scanner-eyebrow {
        display: inline-block;

        margin-bottom: 7px;

        color: var(--scan-green);

        font-size: 12px;
        font-weight: 750;

        letter-spacing: .08em;

        text-transform: uppercase;
    }

    .scanner-title {
        margin: 0;

        color: var(--scan-text);

        font-size: 1.35rem;
        font-weight: 750;

        letter-spacing: -.02em;
    }

    .scanner-subtitle {
        margin:
            7px 0
            0;

        color: var(--scan-muted);

        font-size: 14px;
        line-height: 1.55;
    }


    /*
    |--------------------------------------------------------------------------
    | CONTENEDOR DEL ESCÁNER
    |--------------------------------------------------------------------------
    */

    .reader-shell {
        position: relative;

        width: 100%;

        padding: 12px;

        box-sizing: border-box;

        overflow: hidden;

        background:
            linear-gradient(
                145deg,
                #ffffff,
                #f8fcf9
            );

        border:
            1px solid
            #dfe9e2;

        border-radius: 20px;

        box-shadow:
            inset 0 0 0 1px
            rgba(255,255,255,.9),
            0 12px 30px
            rgba(35, 75, 47, .06);

        transition:
            transform .25s ease,
            border-color .25s ease,
            box-shadow .25s ease;
    }

    /*
     * Halo animado muy suave.
     */
    .reader-shell::before {
        content: "";

        position: absolute;

        inset: -40%;

        z-index: 0;

        background:
            conic-gradient(
                from 0deg,
                transparent,
                rgba(34,197,94,.11),
                transparent,
                rgba(197,154,69,.08),
                transparent
            );

        animation:
            scannerRotateGlow
            12s linear infinite;

        pointer-events: none;
    }

    .reader-inner {
        position: relative;

        z-index: 2;

        width: 100%;

        padding: 6px;

        box-sizing: border-box;

        background: #ffffff;

        border-radius: 15px;

        overflow: hidden;
    }


    /*
    |--------------------------------------------------------------------------
    | INSTRUCCIÓN
    |--------------------------------------------------------------------------
    */

    .reader-instruction {
        position: relative;

        z-index: 4;

        width: 100%;

        margin-bottom: 10px;

        padding: 10px 12px;

        box-sizing: border-box;

        border-radius: 12px;

        background:
            var(--scan-green-extra);

        color:
            var(--scan-green);

        font-size: 13px;

        line-height: 1.45;

        text-align: center;
    }


    /*
    |--------------------------------------------------------------------------
    | CÁMARA
    |--------------------------------------------------------------------------
    */

    #qr-reader-live {
        position: relative;

        width: 100% !important;
        max-width: 100% !important;

        min-width: 0 !important;

        overflow: hidden;

        border: 0 !important;

        border-radius: 14px;

        background: #ffffff;
    }

    #qr-reader-live > div {
        width: 100% !important;
        max-width: 100% !important;

        box-sizing: border-box !important;
    }

    #qr-reader-live video {
        display: block;

        width: 100% !important;
        max-width: 100% !important;

        height: auto !important;

        min-height: 290px;
        max-height: 520px;

        object-fit: cover;

        border-radius: 14px;

        margin: 0 auto;
    }

    #qr-reader-live canvas,
    #qr-reader-live img {
        max-width: 100% !important;
        height: auto !important;
    }


    /*
    |--------------------------------------------------------------------------
    | CONTROLES GENERADOS POR HTML5-QRCODE
    |--------------------------------------------------------------------------
    */

    #qr-reader-live select {
        width: 100% !important;

        padding: 11px 13px;

        margin: 8px 0;

        box-sizing: border-box;

        background: #ffffff;

        border:
            1px solid
            #dce7df;

        border-radius: 11px;

        color: var(--scan-text);

        font-size: 14px;

        outline: none;

        transition:
            border-color .2s ease,
            box-shadow .2s ease;
    }

    #qr-reader-live select:focus {
        border-color:
            var(--scan-green-soft);

        box-shadow:
            0 0 0 3px
            rgba(34,197,94,.10);
    }

    #qr-reader-live button {
        display: inline-flex;

        align-items: center;
        justify-content: center;

        max-width: 100%;

        padding: 10px 16px;

        margin: 6px 3px;

        border: 0;

        border-radius: 11px;

        background:
            var(--scan-green);

        color: #ffffff;

        font-weight: 650;

        cursor: pointer;

        transition:
            transform .18s ease,
            box-shadow .18s ease,
            background .18s ease;
    }

    #qr-reader-live button:hover {
        transform:
            translateY(-1px);

        box-shadow:
            0 8px 18px
            rgba(22,101,52,.18);
    }


    /*
    |--------------------------------------------------------------------------
    | LÍNEA DE ESCANEO ELEGANTE
    |--------------------------------------------------------------------------
    */

    .scanner-line {
        position: absolute;

        z-index: 15;

        left: 9%;
        right: 9%;

        top: 24%;

        height: 2px;

        pointer-events: none;

        border-radius: 999px;

        background:
            linear-gradient(
                90deg,
                transparent,
                rgba(34,197,94,.8),
                var(--scan-green),
                rgba(34,197,94,.8),
                transparent
            );

        box-shadow:
            0 0 14px
            rgba(34,197,94,.32);

        animation:
            scannerLineMove
            3.1s ease-in-out infinite;
    }


    /*
    |--------------------------------------------------------------------------
    | ESQUINAS DE ENCUADRE
    |--------------------------------------------------------------------------
    */

    .scanner-corners {
        position: absolute;

        z-index: 12;

        inset:
            78px
            10%
            24px;

        pointer-events: none;
    }

    .scanner-corners span {
        position: absolute;

        width: 32px;
        height: 32px;

        border-color:
            var(--scan-green-soft);

        border-style: solid;

        opacity: .8;
    }

    .scanner-corners .tl {
        top: 0;
        left: 0;

        border-width:
            3px 0 0 3px;

        border-radius:
            10px 0 0 0;
    }

    .scanner-corners .tr {
        top: 0;
        right: 0;

        border-width:
            3px 3px 0 0;

        border-radius:
            0 10px 0 0;
    }

    .scanner-corners .bl {
        bottom: 0;
        left: 0;

        border-width:
            0 0 3px 3px;

        border-radius:
            0 0 0 10px;
    }

    .scanner-corners .br {
        bottom: 0;
        right: 0;

        border-width:
            0 3px 3px 0;

        border-radius:
            0 0 10px 0;
    }


    /*
    |--------------------------------------------------------------------------
    | FEEDBACK
    |--------------------------------------------------------------------------
    */

    .scan-feedback {
        position: absolute;

        z-index: 40;

        top: 18px;
        left: 50%;

        width:
            calc(100% - 36px);

        max-width: 500px;

        padding: 11px 15px;

        box-sizing: border-box;

        border-radius: 13px;

        background: #ffffff;

        border:
            1px solid
            var(--scan-border);

        color:
            var(--scan-text);

        box-shadow:
            0 12px 32px
            rgba(31,41,55,.12);

        font-size: 13px;
        font-weight: 700;

        text-align: center;

        opacity: 0;

        transform:
            translate(-50%, -12px)
            scale(.97);

        pointer-events: none;

        transition:
            opacity .25s ease,
            transform .25s ease;
    }

    .scan-feedback.visible {
        opacity: 1;

        transform:
            translate(-50%, 0)
            scale(1);
    }

    .scan-feedback.ok {
        color: var(--scan-green);

        background:
            #f0fdf4;

        border-color:
            #bbf7d0;
    }

    .scan-feedback.duplicate {
        color:
            var(--scan-warning);

        background:
            var(--scan-warning-light);

        border-color:
            #fde68a;
    }

    .scan-feedback.error {
        color:
            var(--scan-error);

        background:
            var(--scan-error-light);

        border-color:
            #fecaca;
    }


    /*
    |--------------------------------------------------------------------------
    | ESTADOS
    |--------------------------------------------------------------------------
    */

    .reader-shell.scan-success {
        border-color:
            #86efac;

        box-shadow:
            0 0 0 4px
            rgba(34,197,94,.08),
            0 15px 35px
            rgba(22,101,52,.10);

        animation:
            scannerSuccessPulse
            .65s ease;
    }

    .reader-shell.scan-duplicate {
        border-color:
            #fcd34d;

        box-shadow:
            0 0 0 4px
            rgba(245,158,11,.08);
    }

    .reader-shell.scan-error {
        border-color:
            #fca5a5;

        box-shadow:
            0 0 0 4px
            rgba(239,68,68,.06);

        animation:
            scannerShake
            .35s ease;
    }


    /*
    |--------------------------------------------------------------------------
    | ÚLTIMO REGISTRO
    |--------------------------------------------------------------------------
    */

    .last-scanned {
        margin-top: 14px;

        padding: 13px 15px;

        box-sizing: border-box;

        background:
            var(--scan-green-extra);

        color:
            var(--scan-green);

        border:
            1px solid
            #d7f0dd;

        border-radius: 13px;

        font-size: 14px;
        font-weight: 650;

        text-align: center;

        animation:
            scannerResultIn
            .45s ease both;
    }


    /*
    |--------------------------------------------------------------------------
    | SUBIR IMAGEN
    |--------------------------------------------------------------------------
    */

    .scanner-file-section {
        margin-top: 18px;

        padding-top: 18px;

        border-top:
            1px solid
            var(--scan-border);
    }

    .scanner-file-title {
        display: block;

        margin-bottom: 4px;

        color:
            var(--scan-text);

        font-size: 14px;
        font-weight: 700;
    }

    .scanner-file-description {
        margin:
            0 0
            12px;

        color:
            var(--scan-muted);

        font-size: 13px;
        line-height: 1.45;
    }

    #qrFileInput {
        display: none;
    }


    /*
    |--------------------------------------------------------------------------
    | BOTONES PERSONALIZADOS
    |--------------------------------------------------------------------------
    */

    .scan-btn {
        position: relative;

        display: inline-flex;

        align-items: center;
        justify-content: center;

        width: 100%;

        min-height: 46px;

        padding:
            11px 18px;

        overflow: hidden;

        border: 0;

        border-radius:
            13px;

        background:
            var(--scan-green);

        color: #ffffff;

        font-size: 14px;
        font-weight: 700;

        cursor: pointer;

        box-shadow:
            0 8px 18px
            rgba(22,101,52,.15);

        transition:
            transform .2s ease,
            box-shadow .2s ease;
    }

    /*
     * Brillo deslizante.
     */
    .scan-btn::after {
        content: "";

        position: absolute;

        top: 0;
        left: -140%;

        width: 90%;
        height: 100%;

        transform: skewX(-25deg);

        background:
            linear-gradient(
                90deg,
                transparent,
                rgba(255,255,255,.30),
                transparent
            );

        transition:
            left .6s ease;
    }

    .scan-btn:hover {
        transform:
            translateY(-2px);

        box-shadow:
            0 12px 25px
            rgba(22,101,52,.20);
    }

    .scan-btn:hover::after {
        left: 140%;
    }

    .scan-btn:active {
        transform:
            scale(.985);
    }


    /*
    |--------------------------------------------------------------------------
    | ENTRADA MANUAL
    |--------------------------------------------------------------------------
    */

    .manual-form {
        display: flex;

        flex-direction: column;

        gap: 14px;
    }

    .manual-field {
        display: flex;

        flex-direction: column;

        gap: 7px;
    }

    .manual-field span {
        color:
            var(--scan-text);

        font-size: 13px;
        font-weight: 700;
    }

    .manual-input {
        width: 100%;

        min-height: 48px;

        padding:
            0 14px;

        box-sizing: border-box;

        background:
            #ffffff;

        border:
            1px solid
            #dfe7e1;

        border-radius:
            13px;

        color:
            var(--scan-text);

        font-size:
            16px;

        outline: none;

        transition:
            border-color .2s ease,
            box-shadow .2s ease,
            transform .2s ease;
    }

    .manual-input:focus {
        border-color:
            var(--scan-green-soft);

        box-shadow:
            0 0 0 4px
            rgba(34,197,94,.08);

        transform:
            translateY(-1px);
    }


    /*
    |--------------------------------------------------------------------------
    | RESULTADO
    |--------------------------------------------------------------------------
    */

    .result {
        margin-top:
            18px;

        width: 100%;

        word-break:
            break-word;
    }

    .result .ok,
    .result .info,
    .result .error {
        padding:
            14px 16px;

        border-radius:
            13px;

        line-height:
            1.5;

        animation:
            scannerResultIn
            .4s ease both;
    }

    .result .ok {
        background:
            var(--scan-green-extra);

        color:
            var(--scan-green);

        border:
            1px solid
            #bbf7d0;
    }

    .result .info {
        background:
            var(--scan-gold-light);

        color:
            #7c5a1e;

        border:
            1px solid
            #ead5a8;
    }

    .result .error {
        background:
            var(--scan-error-light);

        color:
            var(--scan-error);

        border:
            1px solid
            #fecaca;
    }


    /*
    |--------------------------------------------------------------------------
    | NOTA
    |--------------------------------------------------------------------------
    */

    .scanner-note {
        display: flex;

        align-items: center;

        gap: 10px;

        margin-top: 16px;

        padding:
            12px 14px;

        border:
            1px solid
            #dfeee2;

        border-radius:
            13px;

        background:
            var(--scan-bg-soft);

        color:
            var(--scan-muted);

        font-size:
            13px;

        line-height:
            1.45;
    }

    .scanner-note-dot {
        flex: 0 0 auto;

        width: 9px;
        height: 9px;

        border-radius:
            50%;

        background:
            var(--scan-green-soft);
    }


    /*
    |--------------------------------------------------------------------------
    | OCULTAR ELEMENTOS MOLESTOS HTML5-QRCODE
    |--------------------------------------------------------------------------
    */

    #reader .html5-qrcode-status,
    #reader .html5-qrcode-error,
    #reader .html5-qrcode-info {
        display:
            none !important;
    }


    /*
    |--------------------------------------------------------------------------
    | ANIMACIONES
    |--------------------------------------------------------------------------
    */

    @keyframes scannerCardIn {
        from {
            opacity: 0;

            transform:
                translateY(18px)
                scale(.985);
        }

        to {
            opacity: 1;

            transform:
                translateY(0)
                scale(1);
        }
    }

    @keyframes scannerFadeDown {
        from {
            opacity: 0;

            transform:
                translateY(-12px);
        }

        to {
            opacity: 1;

            transform:
                translateY(0);
        }
    }

    @keyframes scannerPulseDot {
        0% {
            box-shadow:
                0 0 0 0
                rgba(34,197,94,.35);
        }

        70% {
            box-shadow:
                0 0 0 8px
                rgba(34,197,94,0);
        }

        100% {
            box-shadow:
                0 0 0 0
                rgba(34,197,94,0);
        }
    }

    @keyframes scannerRotateGlow {
        from {
            transform:
                rotate(0deg);
        }

        to {
            transform:
                rotate(360deg);
        }
    }

    @keyframes scannerLineMove {
        0% {
            top: 27%;

            opacity: .4;
        }

        50% {
            top: 78%;

            opacity: 1;
        }

        100% {
            top: 27%;

            opacity: .4;
        }
    }

    @keyframes scannerSuccessPulse {
        0% {
            transform:
                scale(1);
        }

        50% {
            transform:
                scale(1.012);
        }

        100% {
            transform:
                scale(1);
        }
    }

    @keyframes scannerShake {
        0%,
        100% {
            transform:
                translateX(0);
        }

        30% {
            transform:
                translateX(-4px);
        }

        70% {
            transform:
                translateX(4px);
        }
    }

    @keyframes scannerResultIn {
        from {
            opacity: 0;

            transform:
                translateY(8px);
        }

        to {
            opacity: 1;

            transform:
                translateY(0);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TABLET
    |--------------------------------------------------------------------------
    */

    @media (
        max-width: 860px
    ) {
        .scanner-grid {
            grid-template-columns:
                1fr;
        }

        .scanner-card {
            padding:
                20px;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MÓVIL
    |--------------------------------------------------------------------------
    */

    @media (
        max-width: 600px
    ) {
        .scanner-page {
            padding:
                14px 8px
                30px;
        }

        .scanner-page-header {
            margin-bottom:
                18px;
        }

        .scanner-page-description {
            padding:
                0 10px;

            font-size:
                14px;
        }

        .scanner-grid {
            display:
                block;
        }

        .scanner-card {
            margin-bottom:
                14px;

            padding:
                14px;

            border-radius:
                19px;

            box-shadow:
                0 12px 30px
                rgba(41,65,49,.07);
        }

        .reader-shell {
            padding:
                7px;

            border-radius:
                17px;
        }

        .reader-inner {
            padding:
                4px;
        }

        #qr-reader-live video {
            width:
                100% !important;

            min-height:
                280px;

            max-height:
                62vh;

            border-radius:
                12px;

            object-fit:
                cover;
        }

        .reader-instruction {
            margin-bottom:
                6px;

            padding:
                9px;

            font-size:
                12px;
        }

        /*
         * La línea se mueve dentro
         * del área útil del teléfono.
         */
        .scanner-line {
            left:
                8%;

            right:
                8%;
        }

        .scanner-corners {
            inset:
                68px
                8%
                18px;
        }

        .scanner-corners span {
            width:
                26px;

            height:
                26px;
        }

        .scan-feedback {
            top:
                10px;

            width:
                calc(100% - 20px);

            padding:
                10px;

            font-size:
                12px;
        }

        #qr-reader-live button {
            width:
                100%;

            margin:
                5px 0;
        }

        #qr-reader-live select {
            font-size:
                16px;
        }

        .scanner-title {
            font-size:
                1.2rem;
        }

        .scan-btn {
            min-height:
                48px;

            font-size:
                15px;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MÓVILES PEQUEÑOS
    |--------------------------------------------------------------------------
    */

    @media (
        max-width: 380px
    ) {
        .scanner-card {
            padding:
                10px;
        }

        #qr-reader-live video {
            min-height:
                245px;
        }

        .scanner-corners {
            inset:
                64px
                7%
                15px;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESPETAR REDUCCIÓN DE MOVIMIENTO
    |--------------------------------------------------------------------------
    */

    @media (
        prefers-reduced-motion:
        reduce
    ) {
        *,
        *::before,
        *::after {
            animation-duration:
                .01ms !important;

            animation-iteration-count:
                1 !important;

            transition-duration:
                .01ms !important;
        }
    }

</style>


<div class="scanner-page">

    {{-- ============================================================= --}}
    {{-- CABECERA --}}
    {{-- ============================================================= --}}

    <div class="scanner-page-header">

        <div class="scanner-page-badge">
            Sistema de asistencia activo
        </div>

        <h1 class="scanner-page-title">
            Registro de asistencia
        </h1>

        <p class="scanner-page-description">
            Escanea el código QR del alumno o utiliza
            su número de cuenta para registrar la entrada.
        </p>

    </div>


    <div class="scanner-grid">


        {{-- ========================================================= --}}
        {{-- ESCÁNER --}}
        {{-- ========================================================= --}}

        <article class="scanner-card">

            <div class="scanner-header">

                <span class="scanner-eyebrow">
                    Cámara
                </span>

                <h2 class="scanner-title">
                    Escanear código QR
                </h2>

                <p class="scanner-subtitle">
                    Coloca el código dentro del área de lectura.
                    El registro se realizará automáticamente.
                </p>

            </div>


            <div
                id="reader"
                class="reader-shell"
            >

                {{-- Feedback --}}
                <div
                    id="scanFeedback"
                    class="scan-feedback"
                    aria-live="polite"
                ></div>


                <div class="reader-inner">

                    <div
                        id="readerInstruction"
                        class="reader-instruction"
                    >
                        Mantén el QR dentro del recuadro durante unos segundos.
                    </div>


                    {{-- Cámara --}}
                    <div id="qr-reader-live"></div>


                    {{-- Línea animada --}}
                    <div
                        class="scanner-line"
                        aria-hidden="true"
                    ></div>


                    {{-- Esquinas decorativas --}}
                    <div
                        class="scanner-corners"
                        aria-hidden="true"
                    >
                        <span class="tl"></span>
                        <span class="tr"></span>
                        <span class="bl"></span>
                        <span class="br"></span>
                    </div>

                </div>

            </div>


            {{-- Último registro --}}
            <div
                id="lastScanned"
                class="last-scanned"
                aria-live="polite"
                hidden
            ></div>


            {{-- Token --}}
            <input
                type="hidden"
                id="csrf_token"
                value="{{ csrf_token() }}"
            >


            {{-- Subir imagen --}}
            <div class="scanner-file-section">

                <span class="scanner-file-title">
                    Escanear desde una imagen
                </span>

                <p class="scanner-file-description">
                    Si no puedes utilizar la cámara,
                    selecciona una fotografía que contenga el QR.
                </p>


                <input
                    type="file"
                    id="qrFileInput"
                    accept="image/*"
                >


                <button
                    class="scan-btn"
                    type="button"
                    id="scanFileBtn"
                >
                    Seleccionar imagen QR
                </button>

            </div>


            <div class="scanner-note">

                <span class="scanner-note-dot"></span>

                <span>
                    Permite el acceso a la cámara cuando
                    el navegador lo solicite.
                </span>

            </div>

        </article>


        {{-- ========================================================= --}}
        {{-- MANUAL --}}
        {{-- ========================================================= --}}

        <article class="scanner-card">

            <div class="scanner-header">

                <span class="scanner-eyebrow">
                    Registro alternativo
                </span>

                <h2 class="scanner-title">
                    Entrada manual
                </h2>

                <p class="scanner-subtitle">
                    Utiliza esta opción cuando el código QR
                    no pueda ser leído correctamente.
                </p>

            </div>


            <div class="manual-form">

                <label class="manual-field">

                    <span>
                        Número de cuenta
                    </span>

                    <input
                        class="manual-input"
                        id="manualCode"
                        type="text"
                        autocomplete="off"
                        placeholder="Ej. 202207061"
                    >

                </label>


                <button
                    class="scan-btn"
                    type="button"
                    onclick="manualCheckin()"
                >
                    Registrar asistencia
                </button>


                <noscript>

                    <form
                        method="POST"
                        action="{{ route('api.checkin') }}"
                    >

                        @csrf

                        <input
                            class="manual-input"
                            type="text"
                            name="student_code"
                            placeholder="202207061"
                            required
                        >

                        <br><br>

                        <button
                            class="scan-btn"
                            type="submit"
                        >
                            Registrar asistencia
                        </button>

                    </form>

                </noscript>

            </div>


            <div
                class="result"
                id="result"
                aria-live="polite"
            ></div>

        </article>

    </div>

</div>

@endsection