-- Ticketera QR — esquema SQLite.
-- Montos en pesos enteros. Fechas en texto 'YYYY-MM-DD HH:MM:SS', hora de Buenos Aires.
--
-- Un EVENTO es la propuesta (textos, imágenes, categoría, reseñas). Cada
-- FUNCIÓN es una fecha concreta con su lugar, aforo y tipos de entrada: un
-- mismo evento puede repetirse en otras fechas o en otros lugares. Las reseñas
-- se atan a la función a la que la persona asistió, así cada opinión queda
-- fechada y ubicada.

CREATE TABLE IF NOT EXISTS usuarios (
  id INTEGER PRIMARY KEY,
  nombre TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE COLLATE NOCASE,
  clave_hash TEXT NOT NULL,
  telefono TEXT,
  dni TEXT,
  rol TEXT NOT NULL DEFAULT 'cliente' CHECK (rol IN ('cliente','puerta','admin')),
  activo INTEGER NOT NULL DEFAULT 1,
  acepto_terminos TEXT,
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS categorias (
  id INTEGER PRIMARY KEY,
  nombre TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  orden INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS eventos (
  id INTEGER PRIMARY KEY,
  slug TEXT NOT NULL UNIQUE,
  titulo TEXT NOT NULL,
  bajada TEXT,
  descripcion TEXT,
  categoria_id INTEGER REFERENCES categorias(id) ON DELETE SET NULL,
  max_por_compra INTEGER NOT NULL DEFAULT 6,
  estado TEXT NOT NULL DEFAULT 'borrador' CHECK (estado IN ('borrador','publicado','cancelado')),
  visibilidad TEXT NOT NULL DEFAULT 'publica' CHECK (visibilidad IN ('publica','enlace')),
  organizador TEXT,
  edad_minima INTEGER,
  vestimenta TEXT,
  meta_titulo TEXT,
  meta_descripcion TEXT,
  meta_palabras TEXT,
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  actualizado TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS funciones (
  id INTEGER PRIMARY KEY,
  evento_id INTEGER NOT NULL REFERENCES eventos(id) ON DELETE CASCADE,
  inicio TEXT NOT NULL,
  fin TEXT,
  lugar TEXT,
  direccion TEXT,
  ciudad TEXT,
  lat REAL,
  lng REAL,
  aforo INTEGER NOT NULL DEFAULT 0,
  estado TEXT NOT NULL DEFAULT 'activa' CHECK (estado IN ('activa','cancelada')),
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS ix_funciones_evento ON funciones(evento_id, inicio);

CREATE TABLE IF NOT EXISTS evento_imagenes (
  id INTEGER PRIMARY KEY,
  evento_id INTEGER NOT NULL REFERENCES eventos(id) ON DELETE CASCADE,
  archivo TEXT NOT NULL,
  alt TEXT,
  orden INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS etiquetas (
  id INTEGER PRIMARY KEY,
  nombre TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS evento_etiquetas (
  evento_id INTEGER NOT NULL REFERENCES eventos(id) ON DELETE CASCADE,
  etiqueta_id INTEGER NOT NULL REFERENCES etiquetas(id) ON DELETE CASCADE,
  PRIMARY KEY (evento_id, etiqueta_id)
);

CREATE TABLE IF NOT EXISTS tipos_entrada (
  id INTEGER PRIMARY KEY,
  funcion_id INTEGER NOT NULL REFERENCES funciones(id) ON DELETE CASCADE,
  nombre TEXT NOT NULL,
  descripcion TEXT,
  precio INTEGER NOT NULL DEFAULT 0,
  cupo INTEGER NOT NULL DEFAULT 0,
  orden INTEGER NOT NULL DEFAULT 0,
  activo INTEGER NOT NULL DEFAULT 1,
  precio_oferta INTEGER,                     -- precio rebajado mientras no pase oferta_hasta
  oferta_hasta TEXT                          -- NULL = la oferta sigue hasta que se saque
);

CREATE TABLE IF NOT EXISTS ordenes (
  id INTEGER PRIMARY KEY,
  codigo TEXT NOT NULL UNIQUE,
  usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
  funcion_id INTEGER NOT NULL REFERENCES funciones(id),
  total INTEGER NOT NULL,
  estado TEXT NOT NULL DEFAULT 'pagada' CHECK (estado IN ('pendiente','pagada','cancelada')),
  medio_pago TEXT,
  cargo INTEGER NOT NULL DEFAULT 0,          -- cargo por servicio (pago con tarjeta)
  vence TEXT,                                -- transferencia: hasta cuándo se guarda la reserva
  comprobante TEXT,                          -- transferencia: archivo en DIR_DATOS/comprobantes
  comprobante_en TEXT,
  verificada_por INTEGER REFERENCES usuarios(id),
  verificada_en TEXT,
  nota TEXT,                                 -- motivo de rechazo o de vencimiento
  ocr TEXT,                                  -- texto leído del comprobante
  importe_cent INTEGER,                      -- en desuso (centavos únicos, descartado): NULL
  mp_pago_id TEXT,                           -- pago de Mercado Pago que la concilió
  conciliacion TEXT,                         -- cómo se confirmó: mp-automatica, mp-sugerida, manual
  cupon_id INTEGER REFERENCES cupones(id),
  descuento INTEGER NOT NULL DEFAULT 0,      -- lo que restó el cupón (total ya lo descuenta)
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS entradas (
  id INTEGER PRIMARY KEY,
  codigo TEXT NOT NULL UNIQUE,
  orden_id INTEGER NOT NULL REFERENCES ordenes(id),
  funcion_id INTEGER NOT NULL REFERENCES funciones(id),
  tipo_id INTEGER NOT NULL REFERENCES tipos_entrada(id),
  usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
  titular TEXT,
  precio INTEGER NOT NULL,
  estado TEXT NOT NULL DEFAULT 'valida' CHECK (estado IN ('valida','usada','anulada')),
  usada_en TEXT,
  usada_por INTEGER REFERENCES usuarios(id),
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS ix_entradas_funcion ON entradas(funcion_id, estado);
CREATE INDEX IF NOT EXISTS ix_entradas_usuario ON entradas(usuario_id);

-- Cada lectura en la puerta queda registrada, salga bien o mal: es lo que
-- permite reconstruir qué pasó si alguien dice «a mí me dejaron pasar».
CREATE TABLE IF NOT EXISTS escaneos (
  id INTEGER PRIMARY KEY,
  funcion_id INTEGER,
  entrada_id INTEGER,
  codigo_leido TEXT NOT NULL,
  resultado TEXT NOT NULL,
  usuario_id INTEGER,
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Sólo reseña quien tuvo una entrada usada (pasó por la puerta) en esa función.
CREATE TABLE IF NOT EXISTS resenas (
  id INTEGER PRIMARY KEY,
  funcion_id INTEGER NOT NULL REFERENCES funciones(id) ON DELETE CASCADE,
  usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
  puntaje INTEGER NOT NULL CHECK (puntaje BETWEEN 1 AND 5),
  texto TEXT,
  visible INTEGER NOT NULL DEFAULT 1,
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  UNIQUE (funcion_id, usuario_id)
);

-- Cola de correos. Se despacha respetando los topes del proveedor por casilla
-- (100/h, 800/día, 4000/semana, 15000/mes): si un tope está por llenarse, el
-- correo espera al próximo período en vez de arriesgar la casilla.
CREATE TABLE IF NOT EXISTS correos (
  id INTEGER PRIMARY KEY,
  para TEXT NOT NULL,
  asunto TEXT NOT NULL,
  html TEXT NOT NULL,
  texto TEXT NOT NULL,
  motivo TEXT,
  estado TEXT NOT NULL DEFAULT 'pendiente' CHECK (estado IN ('pendiente','enviado','error','omitido')),
  intentos INTEGER NOT NULL DEFAULT 0,
  error TEXT,
  adjuntos TEXT,
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime')),
  enviado TEXT
);
CREATE INDEX IF NOT EXISTS ix_correos_estado ON correos(estado, id);
CREATE INDEX IF NOT EXISTS ix_correos_enviado ON correos(enviado);

CREATE TABLE IF NOT EXISTS recuperos (
  token_hash TEXT PRIMARY KEY,
  usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  vence INTEGER NOT NULL,
  usado INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS intentos_ingreso (
  clave TEXT NOT NULL,
  creado INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS ajustes (
  clave TEXT PRIMARY KEY,
  valor TEXT
);

-- Desafíos del «No soy un robot» ya usados: cada uno sirve una sola vez.
CREATE TABLE IF NOT EXISTS captcha_usados (
  desafio TEXT PRIMARY KEY,
  vence INTEGER NOT NULL
);

-- Pagos que Mercado Pago informa (aviso o consulta). Se guardan todos, se
-- concilien o no: «entró plata y no sabemos de quién» también es un dato.
CREATE TABLE IF NOT EXISTS pagos_mp (
  id TEXT PRIMARY KEY, fecha TEXT, importe_cent INTEGER NOT NULL, estado TEXT, tipo TEXT,
  pagador TEXT, pagador_doc TEXT, descripcion TEXT, crudo TEXT,
  orden_id INTEGER REFERENCES ordenes(id), resultado TEXT, descartado INTEGER NOT NULL DEFAULT 0,
  recibido TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS ix_pagos_mp_importe ON pagos_mp(importe_cent, orden_id);
CREATE INDEX IF NOT EXISTS ix_ordenes_importe ON ordenes(importe_cent, estado);

-- Cupones de descuento: un código que el comprador escribe en el checkout.
-- Los usos se cuentan sobre las órdenes no canceladas (una cancelada lo libera).
CREATE TABLE IF NOT EXISTS cupones (
  id INTEGER PRIMARY KEY, codigo TEXT NOT NULL UNIQUE COLLATE NOCASE, tipo TEXT NOT NULL CHECK (tipo IN ('pct','monto')),
  valor INTEGER NOT NULL, evento_id INTEGER REFERENCES eventos(id) ON DELETE CASCADE, usos_max INTEGER, por_persona INTEGER NOT NULL DEFAULT 1,
  desde TEXT, vence TEXT, activo INTEGER NOT NULL DEFAULT 1, nota TEXT,
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Botón de arrepentimiento: cada pedido es un trámite con código. a_revisar =
-- fuera de la regla (10 días / 24 h antes), lo decide una persona; aceptado =
-- compra anulada y falta devolver el dinero; resuelto = nada más que hacer.
CREATE TABLE IF NOT EXISTS arrepentimientos (
  id INTEGER PRIMARY KEY, codigo TEXT NOT NULL UNIQUE, orden_id INTEGER NOT NULL REFERENCES ordenes(id),
  nombre TEXT, email TEXT NOT NULL, reintegro TEXT, comentario TEXT,
  estado TEXT NOT NULL CHECK (estado IN ('a_revisar','aceptado','resuelto','rechazado')),
  importe INTEGER NOT NULL DEFAULT 0, detalle TEXT, ip TEXT, nota TEXT,
  resuelto_por INTEGER REFERENCES usuarios(id), resuelto_en TEXT,
  creado TEXT NOT NULL DEFAULT (datetime('now','localtime')));
CREATE INDEX IF NOT EXISTS ix_arrepentimientos_orden ON arrepentimientos(orden_id);
