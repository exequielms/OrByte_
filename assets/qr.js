// Generador de códigos QR hecho a mano (modo byte, corrección de errores M, versiones 1 a 10).
// QR.crear(texto) devuelve una matriz de 0 y 1, o null si el texto no entra.
// Al final del archivo está lo que dibuja el QR en qr.php.
var QR = (function () {
    // tablas para multiplicar en GF(256), las usa Reed-Solomon
    var EXP = [], LOG = [];
    (function () {
        var x = 1;
        for (var i = 0; i < 255; i++) { EXP[i] = x; LOG[x] = i; x <<= 1; if (x & 256) x ^= 0x11d; }
        for (var j = 255; j < 512; j++) EXP[j] = EXP[j - 255];
    })();

    // por versión: ec = bytes de corrección por bloque, g = [cantidad de bloques, bytes de datos por bloque]
    var VERSIONES = [null,
        { ec: 10, g: [[1, 16]] }, { ec: 16, g: [[1, 28]] }, { ec: 26, g: [[1, 44]] },
        { ec: 18, g: [[2, 32]] }, { ec: 24, g: [[2, 43]] }, { ec: 16, g: [[4, 27]] },
        { ec: 18, g: [[4, 31]] }, { ec: 22, g: [[2, 38], [2, 39]] },
        { ec: 22, g: [[3, 36], [2, 37]] }, { ec: 26, g: [[4, 43], [1, 44]] }
    ];
    // posiciones de los patrones de alineación de cada versión
    var ALINEAR = [null, [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50]];

    // multiplicación en el campo de Galois
    function mul(a, b) { return a && b ? EXP[LOG[a] + LOG[b]] : 0; }

    // polinomio generador de Reed-Solomon de grado n
    function generador(n) {
        var g = [1];
        for (var i = 0; i < n; i++) {
            var sig = [];
            for (var k = 0; k <= g.length; k++) sig.push(0);
            for (var j = 0; j < g.length; j++) {
                sig[j] ^= g[j];
                sig[j + 1] ^= mul(g[j], EXP[i]);
            }
            g = sig;
        }
        return g;
    }

    // bytes de corrección de errores de un bloque de datos
    function resto(datos, n) {
        var g = generador(n), r = datos.slice();
        for (var k = 0; k < n; k++) r.push(0);
        for (var i = 0; i < datos.length; i++) {
            var c = r[i];
            if (c) for (var j = 0; j < g.length; j++) r[i + j] ^= mul(g[j], c);
        }
        return r.slice(datos.length);
    }

    // texto a bytes UTF-8 (si el navegador no tiene TextEncoder se hace a mano)
    function bytesDe(texto) {
        if (typeof TextEncoder !== "undefined") return Array.prototype.slice.call(new TextEncoder().encode(texto));
        var s = unescape(encodeURIComponent(texto)), r = [];
        for (var i = 0; i < s.length; i++) r.push(s.charCodeAt(i));
        return r;
    }

    // cuántos bytes de datos entran en la versión v
    function capacidad(v) {
        var t = 0;
        VERSIONES[v].g.forEach(function (x) { t += x[0] * x[1]; });
        return t;
    }

    // arma la lista de bytes: modo, largo, datos, terminador y relleno
    function palabras(bytes, v) {
        var bits = [];
        function poner(valor, cant) { for (var i = cant - 1; i >= 0; i--) bits.push((valor >> i) & 1); }
        poner(4, 4);
        poner(bytes.length, v < 10 ? 8 : 16);
        bytes.forEach(function (b) { poner(b, 8); });
        var total = capacidad(v) * 8;
        for (var t = 0; t < 4 && bits.length < total; t++) bits.push(0);
        while (bits.length % 8) bits.push(0);
        var datos = [];
        for (var i = 0; i < bits.length; i += 8) {
            var b = 0;
            for (var j = 0; j < 8; j++) b = (b << 1) | bits[i + j];
            datos.push(b);
        }
        var relleno = [0xEC, 0x11], k = 0;
        while (datos.length < capacidad(v)) datos.push(relleno[k++ % 2]);
        return datos;
    }

    // parte los datos en bloques, les calcula la corrección y los mezcla como pide el estándar
    function intercalar(datos, v) {
        var info = VERSIONES[v], bloques = [], pos = 0;
        info.g.forEach(function (grupo) {
            for (var i = 0; i < grupo[0]; i++) {
                var d = datos.slice(pos, pos + grupo[1]);
                pos += grupo[1];
                bloques.push({ d: d, e: resto(d, info.ec) });
            }
        });
        var salida = [], maximo = 0;
        bloques.forEach(function (b) { if (b.d.length > maximo) maximo = b.d.length; });
        for (var i = 0; i < maximo; i++) bloques.forEach(function (b) { if (i < b.d.length) salida.push(b.d[i]); });
        for (var j = 0; j < info.ec; j++) bloques.forEach(function (b) { salida.push(b.e[j]); });
        return salida;
    }

    function matriz(n, valor) {
        var m = [];
        for (var i = 0; i < n; i++) { m.push([]); for (var j = 0; j < n; j++) m[i].push(valor); }
        return m;
    }

    function copiar(m) { return m.map(function (f) { return f.slice(); }); }

    function bit(x, i) { return (x >> i) & 1; }

    // escribe los bits de formato (nivel de corrección + máscara) al lado de los buscadores
    function formato(m, n, mascara) {
        var datos = (0 << 3) | mascara, resto10 = datos;
        for (var i = 0; i < 10; i++) resto10 = (resto10 << 1) ^ ((resto10 >>> 9) * 0x537);
        var bits = ((datos << 10) | resto10) ^ 0x5412;
        function p(x, y, v) { m[y][x] = v; }
        var k;
        for (k = 0; k <= 5; k++) p(8, k, bit(bits, k));
        p(8, 7, bit(bits, 6));
        p(8, 8, bit(bits, 7));
        p(7, 8, bit(bits, 8));
        for (k = 9; k < 15; k++) p(14 - k, 8, bit(bits, k));
        for (k = 0; k < 8; k++) p(n - 1 - k, 8, bit(bits, k));
        for (k = 8; k < 15; k++) p(8, n - 15 + k, bit(bits, k));
        p(8, n - 8, 1);
    }

    var PATRON_A = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
    var PATRON_B = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];

    // puntaje de penalización para elegir la mejor máscara (las 4 reglas del estándar)
    function penalidad(m, n) {
        var total = 0, i, j, k;
        for (var eje = 0; eje < 2; eje++) {
            for (i = 0; i < n; i++) {
                var corrida = 1;
                for (j = 1; j < n; j++) {
                    var a = eje ? m[j][i] : m[i][j], b = eje ? m[j - 1][i] : m[i][j - 1];
                    if (a === b) { corrida++; if (corrida === 5) total += 3; else if (corrida > 5) total++; }
                    else corrida = 1;
                }
                for (j = 0; j + 11 <= n; j++) {
                    var p1 = true, p2 = true;
                    for (k = 0; k < 11; k++) {
                        var v = eje ? m[j + k][i] : m[i][j + k];
                        if (v !== PATRON_A[k]) p1 = false;
                        if (v !== PATRON_B[k]) p2 = false;
                    }
                    if (p1) total += 40;
                    if (p2) total += 40;
                }
            }
        }
        var oscuros = 0;
        for (i = 0; i < n; i++) for (j = 0; j < n; j++) {
            if (m[i][j]) oscuros++;
            if (i + 1 < n && j + 1 < n && m[i][j] === m[i + 1][j] && m[i][j] === m[i][j + 1] && m[i][j] === m[i + 1][j + 1]) total += 3;
        }
        total += 10 * Math.floor(Math.abs(oscuros * 100 / (n * n) - 50) / 5);
        return total;
    }

    // las 8 máscaras posibles
    function enMascara(mascara, r, c) {
        switch (mascara) {
            case 0: return (r + c) % 2 === 0;
            case 1: return r % 2 === 0;
            case 2: return c % 3 === 0;
            case 3: return (r + c) % 3 === 0;
            case 4: return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0;
            case 5: return (r * c) % 2 + (r * c) % 3 === 0;
            case 6: return ((r * c) % 2 + (r * c) % 3) % 2 === 0;
            default: return ((r + c) % 2 + (r * c) % 3) % 2 === 0;
        }
    }

    // arma la matriz completa: elige la versión, dibuja lo fijo, mete los datos y prueba las 8 máscaras
    function crear(texto) {
        var bytes = bytesDe(texto), v = 0;
        for (var t = 1; t <= 10; t++) {
            if (4 + (t < 10 ? 8 : 16) + bytes.length * 8 <= capacidad(t) * 8) { v = t; break; }
        }
        if (!v) return null;

        var n = 17 + 4 * v, m = matriz(n, 0), res = matriz(n, false), i, j, dr, dc;

        function fijar(x, y, valor) { m[y][x] = valor; res[y][x] = true; }

        function buscador(fila, col) {
            for (dr = -1; dr <= 7; dr++) for (dc = -1; dc <= 7; dc++) {
                var y = fila + dr, x = col + dc;
                if (y < 0 || x < 0 || y >= n || x >= n) continue;
                var negro = (dr >= 0 && dr <= 6 && (dc === 0 || dc === 6)) || (dc >= 0 && dc <= 6 && (dr === 0 || dr === 6)) || (dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4);
                fijar(x, y, negro ? 1 : 0);
            }
        }
        // los 3 cuadrados de las esquinas
        buscador(0, 0); buscador(0, n - 7); buscador(n - 7, 0);

        // líneas punteadas de sincronización
        for (i = 8; i < n - 8; i++) { fijar(i, 6, i % 2 === 0 ? 1 : 0); fijar(6, i, i % 2 === 0 ? 1 : 0); }

        // cuadritos de alineación (menos los que chocan con las esquinas)
        var pos = ALINEAR[v];
        pos.forEach(function (fy, a) {
            pos.forEach(function (fx, b) {
                var ultimo = pos.length - 1;
                if ((a === 0 && b === 0) || (a === 0 && b === ultimo) || (a === ultimo && b === 0)) return;
                for (var y = -2; y <= 2; y++) for (var x = -2; x <= 2; x++) {
                    fijar(fx + x, fy + y, Math.max(Math.abs(y), Math.abs(x)) !== 1 ? 1 : 0);
                }
            });
        });

        // se reservan las zonas del formato
        for (i = 0; i < 9; i++) { res[i][8] = true; res[8][i] = true; }
        for (i = 0; i < 8; i++) { res[8][n - 1 - i] = true; res[n - 1 - i][8] = true; }
        fijar(8, n - 8, 1);

        // desde la versión 7 va también la información de versión
        if (v >= 7) {
            var r12 = v;
            for (i = 0; i < 12; i++) r12 = (r12 << 1) ^ ((r12 >>> 11) * 0x1F25);
            var bitsV = (v << 12) | r12;
            for (i = 0; i < 18; i++) {
                var a = n - 11 + (i % 3), b = Math.floor(i / 3), valor = bit(bitsV, i);
                fijar(a, b, valor);
                fijar(b, a, valor);
            }
        }

        var cw = intercalar(palabras(bytes, v), v), flujo = [];
        cw.forEach(function (c) { for (var k = 7; k >= 0; k--) flujo.push((c >> k) & 1); });

        // los datos se colocan en zigzag, de a dos columnas, de abajo hacia arriba y al revés
        var idx = 0, arriba = true;
        for (var col = n - 1; col >= 1; col -= 2) {
            if (col === 6) col = 5;
            for (i = 0; i < n; i++) {
                var fila = arriba ? n - 1 - i : i;
                for (j = 0; j < 2; j++) {
                    var cc = col - j;
                    if (!res[fila][cc]) m[fila][cc] = idx < flujo.length ? flujo[idx++] : 0;
                }
            }
            arriba = !arriba;
        }

        // se prueban las 8 máscaras y se queda la de menos penalidad
        var mejor = null, mejorPuntos = Infinity;
        for (var mk = 0; mk < 8; mk++) {
            var c = copiar(m);
            for (i = 0; i < n; i++) for (j = 0; j < n; j++) if (!res[i][j] && enMascara(mk, i, j)) c[i][j] ^= 1;
            formato(c, n, mk);
            var puntos = penalidad(c, n);
            if (puntos < mejorPuntos) { mejorPuntos = puntos; mejor = c; }
        }
        return mejor;
    }

    return { crear: crear };
})();

// parte de la página: dibuja el QR en qr.php (en node no se ejecuta)
if (typeof document !== "undefined") (function () {
    var caja = document.getElementById("qr-caja");
    if (!caja) return;

    var campo = document.getElementById("qr-base");
    var imagen = document.getElementById("qr");
    var direccion = document.getElementById("qr-direccion");
    var aviso = document.getElementById("qr-aviso");
    var ruta = caja.dataset.ruta;
    var matrizActual = null;

    // true si la dirección es de esta misma compu (localhost), el celular no la podría abrir
    function esLocal(texto) { return /^(https?:\/\/)?(localhost|127\.|\[?::1)/i.test(texto.trim()); }

    // dirección del servidor + la ruta de la página que va en el QR
    function direccionCompleta() {
        var base = campo.value.trim().replace(/\/+$/, "");
        if (!/^https?:\/\//i.test(base)) base = "http://" + base;
        return base + "/" + ruta;
    }

    // dibuja el QR como un SVG (un cuadradito por cada módulo negro)
    function dibujar() {
        var url = direccionCompleta();
        matrizActual = QR.crear(url);
        aviso.hidden = !esLocal(campo.value);
        direccion.textContent = url;
        if (!matrizActual) {
            imagen.textContent = "La dirección es demasiado larga para un código QR.";
            return;
        }
        var n = matrizActual.length, margen = 4, total = n + margen * 2, trazo = "";
        for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) {
            if (matrizActual[r][c]) trazo += "M" + (c + margen) + " " + (r + margen) + "h1v1h-1z";
        }
        imagen.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + total + " " + total + '" shape-rendering="crispEdges" role="img" aria-label="Código QR"><rect width="' + total + '" height="' + total + '" fill="#fff"/><path d="' + trazo + '" fill="#000"/></svg>';
    }

    // se acuerda de la última dirección que escribieron
    try {
        var guardada = localStorage.getItem("qr_base");
        if (guardada && esLocal(campo.value)) campo.value = guardada;
    } catch (e) {}

    campo.addEventListener("input", function () {
        try { localStorage.setItem("qr_base", campo.value); } catch (e) {}
        dibujar();
    });

    document.getElementById("qr-imprimir").addEventListener("click", function () { window.print(); });

    // descargar: se dibuja el QR en un canvas y se baja como PNG
    document.getElementById("qr-descargar").addEventListener("click", function () {
        if (!matrizActual) return;
        var n = matrizActual.length, escala = 12, margen = 4, lado = (n + margen * 2) * escala;
        var lienzo = document.createElement("canvas");
        lienzo.width = lado; lienzo.height = lado;
        var g = lienzo.getContext("2d");
        g.fillStyle = "#fff"; g.fillRect(0, 0, lado, lado);
        g.fillStyle = "#000";
        for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) {
            if (matrizActual[r][c]) g.fillRect((c + margen) * escala, (r + margen) * escala, escala, escala);
        }
        lienzo.toBlob(function (blob) {
            var enlace = document.createElement("a");
            enlace.href = URL.createObjectURL(blob);
            enlace.download = caja.dataset.archivo + ".png";
            enlace.click();
        });
    });

    dibujar();
})();
