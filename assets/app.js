// JS general. El mapa de las ambulancias está al final.

// modo claro / oscuro
var raiz = document.documentElement;
var botonTema = document.getElementById("tema");
if (botonTema) {
    botonTema.addEventListener("click", function () {
        var nuevo = "oscuro";
        if (raiz.dataset.tema == "oscuro") {
            nuevo = "claro";
        }
        raiz.dataset.tema = nuevo;
        // queda guardado para la próxima vez
        try { localStorage.setItem("tema", nuevo); } catch (e) {}
    });
}

// el monitor se frena si el usuario pidió menos movimiento
var monitor = document.querySelector(".monitor svg");
if (monitor && window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    monitor.pauseAnimations();
}

// el aviso se esconde solo a los 4 segundos
var aviso = document.querySelector(".mensaje");
if (aviso) {
    setTimeout(function () {
        aviso.style.display = "none";
    }, 4000);
}

// los form con data-confirmar preguntan antes de enviarse
document.addEventListener("submit", function (ev) {
    var texto = ev.target.dataset.confirmar;
    if (texto && !confirm(texto)) {
        ev.preventDefault();
    }
});

// botones data-imprimir
document.addEventListener("click", function (ev) {
    if (ev.target.hasAttribute && ev.target.hasAttribute("data-imprimir")) {
        window.print();
    }
});

// encuestas: formulario de preguntas
var tipoPregunta = document.getElementById("tipo-pregunta");
if (tipoPregunta) {
    var bloqueOpciones = document.getElementById("bloque-opciones");
    var listaOpciones = document.getElementById("lista-opciones");
    var ayudaTipo = document.getElementById("aviso-tipo");
    var ayudas = {
        escala: "Se crean solas las opciones: Muy mala, Mala, Regular, Buena y Muy buena.",
        si_no: "Se crean solas las opciones: Sí y No.",
        unica: "El paciente elige una sola de las opciones que usted escriba abajo.",
        abierta: "El paciente escribe su respuesta con sus propias palabras."
    };

    // las opciones solo se ven si el tipo es "unica"
    function actualizarTipo() {
        bloqueOpciones.hidden = (tipoPregunta.value != "unica");
        ayudaTipo.textContent = ayudas[tipoPregunta.value];
    }
    tipoPregunta.addEventListener("change", actualizarTipo);
    actualizarTipo();

    // agrega otro input de opción
    document.getElementById("agregar-opcion").addEventListener("click", function () {
        var campo = document.createElement("input");
        campo.type = "text";
        campo.name = "opciones[]";
        campo.maxLength = 200;
        campo.placeholder = "Otra opción";
        listaOpciones.appendChild(campo);
        campo.focus();
    });
}

// perfil: las dos contraseñas nuevas tienen que coincidir
var formClave = document.querySelector("form[data-clave]");
if (formClave) {
    var campoNueva = formClave.elements.nueva;
    var campoRepetir = formClave.elements.repetir;

    function comprobarClaves() {
        var distintas = (campoNueva.value != "" && campoRepetir.value != "" && campoNueva.value != campoRepetir.value);
        if (distintas) {
            campoRepetir.setCustomValidity("Las contraseñas no coinciden");
        } else {
            campoRepetir.setCustomValidity("");
        }
    }
    campoNueva.addEventListener("input", comprobarClaves);
    campoRepetir.addEventListener("input", comprobarClaves);
}


// ---- mapa de ambulancias (traslados.php): Leaflet + Geoapify ----
var selectorMapa = document.getElementById("mapa-ambulancia");

if (selectorMapa && window.L) {

    // datos fijos
    var CLAVE_GEOAPIFY = "4572a7318c164b3b873097a1f7b2c618";
    var HOSPITAL = [-34.8886, -56.1665];   // Hospital de Clínicas, de acá salen por defecto

    // elementos de la página
    var infoMapa = document.getElementById("mapa-info");
    var campoDestino = document.getElementById("sim-destino");
    var panelSim = document.getElementById("sim-panel");

    // el mapa
    var mapa = L.map("visualizar").setView(HOSPITAL, 13);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap"
    }).addTo(mapa);

    // acá se dibuja todo (ambulancias, rutas y destinos) para poder limpiarlo junto
    var capaMapa = L.featureGroup().addTo(mapa);

    // estado del mapa
    var numeroConsulta = 0;     // para ignorar respuestas viejas si cambian de ambulancia
    var simulacion = null;      // la animacion en marcha
    var puntoElegido = null;    // dónde hizo clic
    var pinElegido = null;      // su marcador
    var guardadoLugares = {};   // cache de lugares
    var guardadoRutas = {};     // cache de rutas

    // el form del mapa no se envía
    document.getElementById("form-mapa").addEventListener("submit", function (ev) {
        ev.preventDefault();
    });


    // iconos
    var coloresEstado = {
        "Disponible": "#1a8a4a",
        "En servicio": "var(--acento)",
        "Mantenimiento": "#7a8a99"
    };

    // icono de la ambulancia, el color cambia con el estado
    function iconoAmbulancia(estado) {
        var color = coloresEstado[estado];
        if (!color) {
            color = "var(--acento)";
        }
        var html = '<div style="width:38px;height:38px;border-radius:50%;background:' + color + ';border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center">';
        html += '<svg viewBox="0 0 24 24" width="22" height="22">';
        html += '<path style="fill:#fff" d="M1.5 6.5h12v9.5h-12z"/>';
        html += '<path style="fill:#fff" d="M13.5 9h4.2l3.3 3.5V16h-7.5z"/>';
        html += '<path style="fill:' + color + '" d="M6.6 8h1.8v1.8h1.8v1.8H8.4v1.8H6.6v-1.8H4.8V9.8h1.8z"/>';
        html += '<circle cx="6" cy="17" r="2.2" style="fill:#fff;stroke:' + color + ';stroke-width:1"/>';
        html += '<circle cx="17" cy="17" r="2.2" style="fill:#fff;stroke:' + color + ';stroke-width:1"/>';
        html += '</svg></div>';
        return L.divIcon({ html: html, className: "", iconSize: [38, 38], iconAnchor: [19, 19], popupAnchor: [0, -20] });
    }

    // gota para el destino
    function iconoDestino() {
        var html = '<svg viewBox="0 0 30 40" width="30" height="40" style="filter:drop-shadow(0 2px 3px rgba(0,0,0,.45))">';
        html += '<path style="fill:var(--primario);stroke:#fff;stroke-width:2" d="M15 38C15 38 3 23 3 14a12 12 0 0 1 24 0c0 9-12 24-12 24z"/>';
        html += '<circle cx="15" cy="14" r="4.5" style="fill:#fff"/></svg>';
        return L.divIcon({ html: html, className: "", iconSize: [30, 40], iconAnchor: [15, 38], popupAnchor: [0, -34] });
    }

    // contenido del popup del marcador
    function crearGlobo(titulo, detalle) {
        var caja = document.createElement("div");
        var negrita = document.createElement("strong");
        negrita.textContent = titulo;
        caja.appendChild(negrita);
        if (detalle) {
            caja.appendChild(document.createElement("br"));
            caja.appendChild(document.createTextNode(detalle));
        }
        return caja;
    }

    function ponerAmbulancia(punto, nombre, estado, detalle) {
        var marcador = L.marker(punto, { icon: iconoAmbulancia(estado) });
        marcador.bindPopup(crearGlobo(nombre, detalle));
        marcador.addTo(capaMapa);
        return marcador;
    }

    function ponerDestino(punto, texto) {
        var marcador = L.marker(punto, { icon: iconoDestino() });
        marcador.bindPopup(crearGlobo("Destino", texto));
        marcador.addTo(capaMapa);
        return marcador;
    }


    // ---- Geoapify: buscar lugares y calcular rutas ----

    // busca un lugar por nombre, devuelve [lat, lon] o null
    async function buscarLugar(texto) {
        if (!texto) {
            return null;
        }
        // si ya se busco antes se reusa
        if (guardadoLugares[texto]) {
            return guardadoLugares[texto];
        }
        var url = "https://api.geoapify.com/v1/geocode/search?text=" + encodeURIComponent(texto + ", Uruguay") +
                  "&filter=countrycode:uy&lang=es&limit=1&format=json&apiKey=" + CLAVE_GEOAPIFY;
        var respuesta = await fetch(url);
        var datos = await respuesta.json();

        var punto = null;
        if (datos.results && datos.results.length > 0) {
            punto = [datos.results[0].lat, datos.results[0].lon];
        }
        guardadoLugares[texto] = punto;
        return punto;
    }

    // distancia en metros entre dos puntos (Haversine)
    function distanciaMetros(a, b) {
        var grados = Math.PI / 180;
        var difLat = (b[0] - a[0]) * grados;
        var difLon = (b[1] - a[1]) * grados;
        var h = Math.sin(difLat / 2) * Math.sin(difLat / 2) +
                Math.cos(a[0] * grados) * Math.cos(b[0] * grados) * Math.sin(difLon / 2) * Math.sin(difLon / 2);
        return 12742000 * Math.asin(Math.sqrt(h));
    }

    // Pide la ruta a Geoapify y devuelve:
    //   puntos (lista de [lat, lon]), dist y tiempo acumulados hasta cada punto,
    //   y distTotal / tiempoTotal
    async function calcularRuta(origen, destino) {
        var clave = origen.join() + "|" + destino.join();
        if (guardadoRutas[clave]) {
            return guardadoRutas[clave];
        }

        var url = "https://api.geoapify.com/v1/routing?waypoints=" + origen[0] + "," + origen[1] + "|" + destino[0] + "," + destino[1] +
                  "&mode=drive&units=metric&lang=es&apiKey=" + CLAVE_GEOAPIFY;
        var respuesta = await fetch(url);
        var json = await respuesta.json();

        if (!json.features || json.features.length == 0) {
            throw new Error("sin ruta");
        }
        var ruta = json.features[0];

        // vienen como [lon, lat] y hay que darlas vuelta. A veces vienen en varias líneas y a veces en una
        var coordenadas = ruta.geometry.coordinates;
        var lineas = [];
        if (Array.isArray(coordenadas[0][0])) {
            lineas = coordenadas;
        } else {
            lineas = [coordenadas];
        }
        var puntos = [];
        for (var i = 0; i < lineas.length; i++) {
            for (var j = 0; j < lineas[i].length; j++) {
                puntos.push([lineas[i][j][1], lineas[i][j][0]]);
            }
        }

        // distancia acumulada
        var dist = [0];
        for (var k = 1; k < puntos.length; k++) {
            dist.push(dist[k - 1] + distanciaMetros(puntos[k - 1], puntos[k]));
        }
        var distTotal = dist[dist.length - 1];

        // tiempo acumulado, primero parejo (misma velocidad en todo el recorrido)
        var tiempo = [];
        for (var m = 0; m < dist.length; m++) {
            if (distTotal > 0) {
                tiempo.push(dist[m] / distTotal * ruta.properties.time);
            } else {
                tiempo.push(0);
            }
        }

        // si Geoapify trae los tramos (steps) se usa el tiempo de cada uno, así la velocidad
        // cambia según la calle. Si algo no cuadra se queda con el tiempo parejo.
        var tramos = null;
        if (lineas.length == 1 && ruta.properties.legs && ruta.properties.legs[0]) {
            tramos = ruta.properties.legs[0].steps;
        }
        if (tramos && tramos.length > 0) {
            var tiempoTramos = [];
            var acumulado = 0;
            var datosOk = true;

            for (var t = 0; t < tramos.length; t++) {
                var desde = tramos[t].from_index;
                var hasta = tramos[t].to_index;
                if (desde < 0 || hasta >= puntos.length || hasta < desde) {
                    datosOk = false;
                    break;
                }
                var largoTramo = dist[hasta] - dist[desde];
                for (var p = desde; p <= hasta; p++) {
                    var avance = 0;
                    if (largoTramo > 0) {
                        avance = (dist[p] - dist[desde]) / largoTramo * tramos[t].time;
                    }
                    tiempoTramos[p] = acumulado + avance;
                }
                acumulado = acumulado + tramos[t].time;
            }

            // chequeo que todos los puntos tengan un número válido
            for (var q = 0; q < puntos.length; q++) {
                if (typeof tiempoTramos[q] != "number" || isNaN(tiempoTramos[q])) {
                    datosOk = false;
                }
            }
            if (datosOk && acumulado > 0) {
                tiempo = tiempoTramos;
            }
        }

        var resultado = {
            puntos: puntos,
            dist: dist,
            tiempo: tiempo,
            distTotal: distTotal,
            tiempoTotal: tiempo[tiempo.length - 1]
        };
        guardadoRutas[clave] = resultado;
        return resultado;
    }

    // dónde está la ambulancia a los X segundos de viaje.
    // Devuelve { pos, indice del punto anterior, dist recorrida }
    function posicionEn(ruta, segundos) {
        var ultimo = ruta.tiempo.length - 1;

        // si ya pasó todo el tiempo está en el último punto
        if (segundos >= ruta.tiempo[ultimo]) {
            return { pos: ruta.puntos[ultimo], indice: ultimo, dist: ruta.dist[ultimo] };
        }

        // entre qué dos puntos está
        var i = 0;
        while (i < ultimo - 1 && ruta.tiempo[i + 1] <= segundos) {
            i = i + 1;
        }

        // y qué fracción (0 a 1) de ese tramo ya recorrió
        var duracionTramo = ruta.tiempo[i + 1] - ruta.tiempo[i];
        var fraccion = 0;
        if (duracionTramo > 0) {
            fraccion = (segundos - ruta.tiempo[i]) / duracionTramo;
        }

        var lat = ruta.puntos[i][0] + (ruta.puntos[i + 1][0] - ruta.puntos[i][0]) * fraccion;
        var lon = ruta.puntos[i][1] + (ruta.puntos[i + 1][1] - ruta.puntos[i][1]) * fraccion;
        var dist = ruta.dist[i] + (ruta.dist[i + 1] - ruta.dist[i]) * fraccion;
        return { pos: [lat, lon], indice: i, dist: dist };
    }

    // segundos a texto: "45 s", "12 min", "1 h 5 min"
    function textoDuracion(segundos) {
        segundos = Math.max(0, Math.round(segundos));
        if (segundos < 60) {
            return segundos + " s";
        }
        var minutos = Math.round(segundos / 60);
        if (minutos < 60) {
            return minutos + " min";
        }
        return Math.floor(minutos / 60) + " h " + (minutos % 60) + " min";
    }


    // ---- mostrar ambulancias ----

    // las ambulancias del select (sin "Seleccione" ni "Todas")
    function listaAmbulancias() {
        var lista = [];
        for (var i = 0; i < selectorMapa.options.length; i++) {
            var opcion = selectorMapa.options[i];
            if (opcion.value != "" && opcion.value != "todas") {
                lista.push(opcion);
            }
        }
        return lista;
    }

    // las que están en el hospital se separan en círculo para que no se tapen
    function posicionEnHospital(n) {
        var angulo = n * 2.4;
        var radio = 0.0007;
        if (n == 0) {
            radio = 0;
        }
        return [HOSPITAL[0] + Math.sin(angulo) * radio, HOSPITAL[1] + Math.cos(angulo) * radio];
    }

    // Dibuja la ambulancia en el hospital (sin traslado) o sobre su ruta.
    // Devuelve "sin traslado", "sin destino" o { ruta, traslado }
    async function mostrarAmbulancia(opcion, n, grosor) {
        var nombre = opcion.dataset.nombre;
        var estado = opcion.dataset.estado;

        // sin traslado se queda en el hospital
        if (!opcion.dataset.destino) {
            ponerAmbulancia(posicionEnHospital(n), nombre, estado, estado + " - en el Hospital de Clínicas");
            return "sin traslado";
        }

        var origen = await buscarLugar(opcion.dataset.origen);
        if (!origen) {
            origen = HOSPITAL;
        }
        var destino = await buscarLugar(opcion.dataset.destino);
        if (!destino) {
            ponerAmbulancia(origen, nombre, estado, "No se encontró el destino \"" + opcion.dataset.destino + "\"");
            return "sin destino";
        }

        var ruta = await calcularRuta(origen, destino);
        var estadoTraslado = opcion.dataset.traslado;
        var posicion = origen;
        var detalle = "Traslado " + estadoTraslado + " hacia " + opcion.dataset.destino;

        if (estadoTraslado == "Llegado") {
            posicion = destino;
        } else if (estadoTraslado == "En curso" && opcion.dataset.salida) {
            // posición estimada con el tiempo que pasó desde la salida
            var segundos = Date.now() / 1000 - Number(opcion.dataset.salida);
            segundos = Math.max(segundos, 0);
            segundos = Math.min(segundos, ruta.tiempoTotal);
            posicion = posicionEn(ruta, segundos).pos;
            detalle = detalle + " - posición estimada por la hora de salida";
        }

        L.polyline(ruta.puntos, { color: "#1190d1", weight: grosor, opacity: 0.85 }).addTo(capaMapa);
        ponerDestino(destino, opcion.dataset.destino);
        ponerAmbulancia(posicion, nombre, estado, detalle);
        return { ruta: ruta, traslado: estadoTraslado };
    }

    // limpia el mapa y corta la simulación si había una
    function limpiarMapa() {
        if (simulacion) {
            cancelAnimationFrame(simulacion.animacion);
            simulacion = null;
        }
        capaMapa.clearLayers();
        if (pinElegido) {
            mapa.removeLayer(pinElegido);
            pinElegido = null;
        }
        puntoElegido = null;
        panelSim.hidden = true;
    }

    // ---- cuando eligen otra ambulancia ----
    selectorMapa.addEventListener("change", async function () {
        numeroConsulta = numeroConsulta + 1;
        var miConsulta = numeroConsulta;
        limpiarMapa();
        campoDestino.value = "";

        var opcion = selectorMapa.selectedOptions[0];
        if (!opcion || !opcion.value) {
            infoMapa.textContent = "Seleccione una ambulancia para ver su traslado en el mapa.";
            mapa.setView(HOSPITAL, 13);
            return;
        }

        try {
            infoMapa.textContent = "Cargando mapa...";

            // todas las ambulancias
            if (opcion.value == "todas") {
                var lista = listaAmbulancias();
                var contadorHospital = 0;
                var pendientes = [];
                for (var i = 0; i < lista.length; i++) {
                    var n = 0;
                    if (!lista[i].dataset.destino) {
                        n = contadorHospital;
                        contadorHospital = contadorHospital + 1;
                    }
                    // si una falla devuelve "error" y las demás siguen
                    pendientes.push(mostrarAmbulancia(lista[i], n, 3).catch(function () {
                        return "error";
                    }));
                }
                var resultados = await Promise.all(pendientes);
                if (miConsulta != numeroConsulta) {
                    return;   // ya eligió otra cosa
                }

                var enViaje = 0;
                for (var r = 0; r < resultados.length; r++) {
                    if (resultados[r] && resultados[r].ruta) {
                        enViaje = enViaje + 1;
                    }
                }
                infoMapa.textContent = lista.length + " ambulancias: " + enViaje + " con traslado activo y " + (lista.length - enViaje) +
                                       " en el hospital. Las posiciones en viaje son estimadas (el sistema no tiene GPS).";
                if (capaMapa.getLayers().length > 0) {
                    mapa.fitBounds(capaMapa.getBounds().pad(0.15));
                }
                return;
            }

            // una sola
            campoDestino.value = opcion.dataset.destino;
            var resultado = await mostrarAmbulancia(opcion, 0, 5);
            if (miConsulta != numeroConsulta) {
                return;
            }

            if (resultado && resultado.ruta) {
                mapa.fitBounds(capaMapa.getBounds().pad(0.15));
                infoMapa.textContent = opcion.dataset.nombre + " (traslado " + resultado.traslado + "): " +
                                       (resultado.ruta.distTotal / 1000).toFixed(1) + " km, aprox. " +
                                       Math.round(resultado.ruta.tiempoTotal / 60) + " min hasta " + opcion.dataset.destino + ".";
            } else {
                mapa.setView(capaMapa.getBounds().getCenter(), 15);
                if (resultado == "sin destino") {
                    infoMapa.textContent = "No se encontró el destino en el mapa.";
                } else {
                    infoMapa.textContent = opcion.dataset.nombre + ": sin traslado activo (" + opcion.dataset.estado +
                                           "). Escriba un destino y use \"Simular recorrido\".";
                }
            }
        } catch (error) {
            if (miConsulta == numeroConsulta) {
                infoMapa.textContent = "No se pudo cargar el mapa. Intente de nuevo.";
            }
        }
    });


    // ---- elegir destino con un clic ----
    mapa.on("click", function (ev) {
        if (simulacion) {
            return;   // mientras simula no se puede elegir otro punto
        }
        var opcion = selectorMapa.selectedOptions[0];
        if (!opcion || !opcion.value || opcion.value == "todas") {
            return;
        }
        if (pinElegido) {
            mapa.removeLayer(pinElegido);
        }
        puntoElegido = [ev.latlng.lat, ev.latlng.lng];
        pinElegido = L.marker(puntoElegido, { icon: iconoDestino() }).addTo(mapa);
        campoDestino.value = "Punto elegido en el mapa";
    });

    // si escribe un destino se descarta el punto del clic
    campoDestino.addEventListener("input", function () {
        puntoElegido = null;
        if (pinElegido) {
            mapa.removeLayer(pinElegido);
            pinElegido = null;
        }
    });


    // ---- simulación ----

    // texto en una celda del panel
    function escribirCelda(id, texto) {
        document.getElementById(id).textContent = texto;
    }

    document.getElementById("sim-detener").addEventListener("click", function () {
        if (!simulacion) {
            return;
        }
        cancelAnimationFrame(simulacion.animacion);
        simulacion = null;
        infoMapa.textContent = "Simulación detenida.";
    });

    document.getElementById("sim-iniciar").addEventListener("click", async function () {
        var opcion = selectorMapa.selectedOptions[0];
        if (!opcion || !opcion.value || opcion.value == "todas") {
            infoMapa.textContent = "Elija una ambulancia para simular su recorrido.";
            return;
        }

        numeroConsulta = numeroConsulta + 1;
        var miConsulta = numeroConsulta;
        var puntoClic = puntoElegido;
        if (simulacion) {
            cancelAnimationFrame(simulacion.animacion);
            simulacion = null;
        }

        try {
            infoMapa.textContent = "Calculando recorrido...";

            // destino: el clic o, si no hay, lo escrito
            var destino = puntoClic;
            if (!destino) {
                destino = await buscarLugar(campoDestino.value.trim());
            }
            if (miConsulta != numeroConsulta) {
                return;
            }
            if (!destino) {
                infoMapa.textContent = "Escriba un destino válido o haga clic en el mapa.";
                return;
            }

            var origen = await buscarLugar(opcion.dataset.origen);
            if (!origen) {
                origen = HOSPITAL;
            }
            var ruta = await calcularRuta(origen, destino);
            if (miConsulta != numeroConsulta) {
                return;
            }

            // ruta completa en color claro y lo recorrido más fuerte
            capaMapa.clearLayers();
            if (pinElegido) {
                mapa.removeLayer(pinElegido);
                pinElegido = null;
            }
            var textoDestino = campoDestino.value.trim();
            if (puntoClic) {
                textoDestino = "Punto elegido en el mapa";
            }
            L.polyline(ruta.puntos, { color: "#1190d1", weight: 5, opacity: 0.35 }).addTo(capaMapa);
            var recorrido = L.polyline([ruta.puntos[0]], { color: "#1190d1", weight: 5 }).addTo(capaMapa);
            ponerDestino(destino, textoDestino);
            var ambulancia = ponerAmbulancia(ruta.puntos[0], opcion.dataset.nombre, "En servicio", "Simulación hacia " + textoDestino);
            mapa.fitBounds(capaMapa.getBounds().pad(0.15));

            // la simulación dura lo que elija (20, 40 o 90 s), así que el viaje se acelera por este factor
            var duracionElegida = Number(document.getElementById("sim-duracion").value);
            var factor = ruta.tiempoTotal / duracionElegida;
            var horaInicio = performance.now();

            panelSim.hidden = false;
            infoMapa.textContent = "Simulando el recorrido de " + opcion.dataset.nombre +
                                   " (el viaje real dura aprox. " + textoDuracion(ruta.tiempoTotal) + ").";
            simulacion = { animacion: 0 };

            // se llama en cada cuadro de la animacion
            function pasoDeSimulacion(ahora) {
                if (!simulacion) {
                    return;   // la pararon
                }
                var segundos = (ahora - horaInicio) / 1000 * factor;
                segundos = Math.min(segundos, ruta.tiempoTotal);
                var p = posicionEn(ruta, segundos);

                // mueve la ambulancia y dibuja lo recorrido
                ambulancia.setLatLng(p.pos);
                recorrido.setLatLngs(ruta.puntos.slice(0, p.indice + 1).concat([p.pos]));

                // velocidad promedio hasta ahora (m/s)
                var velocidad = ruta.distTotal / ruta.tiempoTotal;
                if (segundos > 1 && p.dist > 0) {
                    velocidad = p.dist / segundos;
                }
                var falta = Math.max(ruta.distTotal - p.dist, 0) / velocidad;

                escribirCelda("sim-vel", (velocidad * 3.6).toFixed(0) + " km/h");
                escribirCelda("sim-rec", (p.dist / 1000).toFixed(1) + " de " + (ruta.distTotal / 1000).toFixed(1) + " km");

                // llegó al final
                if (segundos >= ruta.tiempoTotal) {
                    escribirCelda("sim-resta", "0 min");
                    escribirCelda("sim-llega", "Llegó a destino");
                    infoMapa.textContent = opcion.dataset.nombre + " llegó a destino.";
                    simulacion = null;
                    return;
                }

                escribirCelda("sim-resta", textoDuracion(falta));
                var horaLlegada = new Date(Date.now() + falta * 1000);
                escribirCelda("sim-llega", horaLlegada.toLocaleTimeString("es-UY", { hour: "2-digit", minute: "2-digit" }));
                simulacion.animacion = requestAnimationFrame(pasoDeSimulacion);
            }
            simulacion.animacion = requestAnimationFrame(pasoDeSimulacion);

        } catch (error) {
            if (miConsulta == numeroConsulta) {
                infoMapa.textContent = "No se pudo calcular el recorrido. Intente de nuevo.";
            }
        }
    });
}
