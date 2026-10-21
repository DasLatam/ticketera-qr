<?php
$terminado = !$sel || terminada($sel);
$cancelada = $sel && ($sel['estado'] === 'cancelada' || $e['estado'] === 'cancelado');
$agotado = $sel && $sel['disponibles'] <= 0;
$venta_abierta = $sel && $e['estado'] === 'publicado' && !$terminado && !$cancelada && !$agotado && !$sin_medio;
[$txt_stock, $cls_stock] = $sel ? estado_stock((int)$sel['disponibles'], (int)$sel['capacidad']) : ['Sin fechas', 'agotado'];
$dir = $sel ? trim(($sel['direccion'] ?? '') . ', ' . ($sel['ciudad'] ?? ''), ', ') : '';
$destino = $sel && $sel['lat'] !== null ? $sel['lat'] . ',' . $sel['lng'] : $dir;
$url_compartir = $canonical . ($sel ? '?f=' . $sel['id'] : '');
$texto_compartir = $e['titulo'] . ($sel ? ' · ' . ucfirst(fecha_corta($sel['inicio'])) . ' h' . ($sel['lugar'] ? ' · ' . $sel['lugar'] : '') : '');
?>
<article class="evento">
  <div class="contenedor">
    <a class="volver" href="/">← Todos los eventos</a>
    <?php if ($e['estado'] === 'borrador'): ?><div class="aviso aviso-info">Vista previa: este evento es un borrador y el público no lo ve.</div><?php endif; ?>
    <?php if ($e['estado'] === 'cancelado'): ?><div class="aviso aviso-error">Este evento fue cancelado.</div><?php endif; ?>
    <?php if ($e['visibilidad'] === 'enlace'): ?><div class="aviso aviso-info">Evento reservado: sólo se accede con este enlace.</div><?php endif; ?>
  </div>

  <div class="contenedor evento-grilla">
    <div class="evento-principal">
      <?php if ($imagenes): ?>
      <div class="galeria" data-galeria>
        <div class="galeria-pista">
          <?php foreach ($imagenes as $i => $img): ?>
            <img src="<?= e(imagen_url($img['archivo'])) ?>" alt="<?= e($img['alt'] ?: $e['titulo']) ?>" width="1800" height="1200"<?= $i ? ' loading="lazy"' : ' fetchpriority="high"' ?>>
          <?php endforeach; ?>
        </div>
        <?php if (count($imagenes) > 1): ?>
        <div class="galeria-puntos">
          <?php foreach ($imagenes as $i => $img): ?><button type="button" aria-label="Imagen <?= $i + 1 ?>"<?= $i ? '' : ' aria-current="true"' ?>></button><?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <header class="evento-cabecera">
        <div class="evento-etiquetas-sup">
          <?php if ($e['categoria']): ?><a class="tarjeta-cat" href="/?cat=<?= e($e['categoria_slug']) ?>"><?= e($e['categoria']) ?></a><?php endif; ?>
          <?php if ($puntaje['n']): ?><a class="puntaje-en-linea" href="#resenas"><?= estrellas($puntaje['prom']) ?> <b><?= e(str_replace('.', ',', (string)$puntaje['prom'])) ?></b> <span class="tenue">· <?= $puntaje['n'] ?> <?= $puntaje['n'] === 1 ? 'reseña' : 'reseñas' ?></span></a><?php endif; ?>
        </div>
        <?php if ($e['organizador']): ?><p class="evento-organiza"><span><?= e($e['organizador']) ?></span> presenta</p><?php endif; ?>
        <h1 class="evento-titulo"><?= e($e['titulo']) ?></h1>
        <?php if ($e['bajada']): ?><p class="evento-bajada"><?= e($e['bajada']) ?></p><?php endif; ?>
      </header>

      <div class="compartir" data-compartir data-url="<?= e($url_compartir) ?>" data-texto="<?= e($texto_compartir) ?>">
        <span class="compartir-titulo">Compartir</span>
        <a class="compartir-btn" href="https://wa.me/?text=<?= e(rawurlencode($texto_compartir . ' ' . $url_compartir)) ?>" target="_blank" rel="noopener" aria-label="Compartir por WhatsApp" title="WhatsApp">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.3.8 3.2.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.2-.2-.5-.3Z"/></svg></a>
        <button type="button" class="compartir-btn" data-red="instagram" aria-label="Compartir en Instagram" title="Instagram">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 8.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4ZM17.3 5.5a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4ZM21.9 7.1a5.8 5.8 0 0 0-1.6-4.1 5.8 5.8 0 0 0-4.1-1.6C14.6 1.3 9.4 1.3 7.8 1.4a5.8 5.8 0 0 0-4.1 1.6 5.8 5.8 0 0 0-1.6 4.1c-.1 1.6-.1 6.8 0 8.4a5.8 5.8 0 0 0 1.6 4.1 5.8 5.8 0 0 0 4.1 1.6c1.6.1 6.8.1 8.4 0a5.8 5.8 0 0 0 4.1-1.6 5.8 5.8 0 0 0 1.6-4.1c.1-1.6.1-6.8 0-8.4Zm-2.1 10.2a3.3 3.3 0 0 1-1.9 1.9c-1.3.5-4.4.4-5.9.4s-4.6.1-5.9-.4a3.3 3.3 0 0 1-1.9-1.9c-.5-1.3-.4-4.4-.4-5.9s-.1-4.6.4-5.9a3.3 3.3 0 0 1 1.9-1.9c1.3-.5 4.4-.4 5.9-.4s4.6-.1 5.9.4a3.3 3.3 0 0 1 1.9 1.9c.5 1.3.4 4.4.4 5.9s.1 4.6-.4 5.9Z"/></svg></button>
        <button type="button" class="compartir-btn" data-red="tiktok" aria-label="Compartir en TikTok" title="TikTok">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.6 2h-3.3v13.5a2.9 2.9 0 1 1-2.9-2.9c.3 0 .6 0 .8.1V9.3a6.3 6.3 0 1 0 5.4 6.2V8.7a8.1 8.1 0 0 0 4.7 1.5V6.9A4.8 4.8 0 0 1 16.6 2Z"/></svg></button>
        <a class="compartir-btn" href="https://twitter.com/intent/tweet?text=<?= e(rawurlencode($texto_compartir)) ?>&url=<?= e(rawurlencode($url_compartir)) ?>" target="_blank" rel="noopener" aria-label="Compartir en X" title="X">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.8 2.5h3.1l-6.8 7.7 8 10.6h-6.3l-4.9-6.4-5.6 6.4H2.2l7.2-8.3L1.8 2.5h6.4l4.4 5.9 5.2-5.9Zm-1.1 16.4h1.7L7.4 4.3H5.6l11.1 14.6Z"/></svg></a>
        <button type="button" class="compartir-btn compartir-copiar" data-red="copiar" aria-label="Copiar el enlace">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.6 13.4a4 4 0 0 0 5.7 0l3.5-3.5a4 4 0 0 0-5.7-5.7l-1 1 1.4 1.4 1-1a2 2 0 1 1 2.9 2.9l-3.5 3.5a2 2 0 0 1-2.9 0l-1.4 1.4Zm2.8-2.8a4 4 0 0 0-5.7 0l-3.5 3.5a4 4 0 0 0 5.7 5.7l1-1-1.4-1.4-1 1a2 2 0 1 1-2.9-2.9L9.1 12a2 2 0 0 1 2.9 0l1.4-1.4Z"/></svg>
          <span>Copiar link</span></button>
        <span class="compartir-aviso" data-compartir-aviso role="status"></span>
      </div>

      <?php if (count($proximas) > 1 || ($sel && terminada($sel) && $proximas)): ?>
      <section class="fechas" id="fechas" aria-label="Fechas">
        <h2 class="seccion-titulo">Fechas</h2>
        <div class="fechas-lista">
          <?php foreach ($proximas as $fn): [$tx, $cl] = estado_stock((int)$fn['disponibles'], (int)$fn['capacidad']); $t = strtotime($fn['inicio']); ?>
            <a class="fecha-opcion<?= $sel && $fn['id'] === $sel['id'] ? ' es-elegida' : '' ?><?= $fn['disponibles'] <= 0 ? ' es-agotada' : '' ?>" href="?f=<?= (int)$fn['id'] ?>#entradas"<?= $sel && $fn['id'] === $sel['id'] ? ' aria-current="true"' : '' ?>>
              <span class="fo-dia"><?= DIAS[date('w', $t)] ?> <b><?= date('j', $t) ?></b> <?= MESES[date('n', $t) - 1] ?></span>
              <span class="fo-hora"><?= date('H:i', $t) ?> h</span>
              <span class="fo-lugar"><?= e($fn['lugar'] ?: $fn['ciudad']) ?></span>
              <span class="fo-stock stock-<?= $cl ?>"><?= e($tx) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($sel): ?>
      <dl class="datos">
        <div><dt>Fecha</dt><dd><?= e(ucfirst(fecha_larga($sel['inicio']))) ?></dd></div>
        <div><dt>Horario</dt><dd><?= e(hora($sel['inicio'])) ?> h<?= $sel['fin'] ? ' a ' . e(hora($sel['fin'])) . ' h' : '' ?></dd></div>
        <?php if ($sel['lugar']): ?><div><dt>Lugar</dt><dd><?= e($sel['lugar']) ?><?php if ($dir): ?><br><span class="tenue"><?= e($dir) ?></span><?php endif; ?></dd></div><?php endif; ?>
        <?php if ($e['edad_minima']): ?><div><dt>Edad</dt><dd>Mayores de <?= (int)$e['edad_minima'] ?></dd></div><?php endif; ?>
        <?php if ($e['vestimenta']): ?><div><dt>Vestimenta</dt><dd><?= e($e['vestimenta']) ?></dd></div><?php endif; ?>
        <?php if ($e['organizador']): ?><div><dt>Organiza</dt><dd><?= e($e['organizador']) ?></dd></div><?php endif; ?>
      </dl>
      <?php endif; ?>

      <div class="texto"><?= parrafos($e['descripcion']) ?></div>

      <?php if ($sel && ($sel['lat'] !== null || $dir)): ?>
      <section class="como-llegar" aria-label="Cómo llegar">
        <h2 class="seccion-titulo">Cómo llegar</h2>
        <?php if ($sel['lat'] !== null): ?>
          <div class="mapa-micro" data-mapa-micro data-lat="<?= e($sel['lat']) ?>" data-lng="<?= e($sel['lng']) ?>" role="img" aria-label="Mapa de <?= e($sel['lugar']) ?>"></div>
        <?php endif; ?>
        <div class="llegar-pie">
          <span><strong><?= e($sel['lugar']) ?></strong><?= $dir ? '<br><span class="tenue">' . e($dir) . '</span>' : '' ?></span>
          <span class="llegar-links">
            <a class="btn btn-secundario btn-chico" href="https://www.google.com/maps/dir/?api=1&destination=<?= e(rawurlencode($destino)) ?>" target="_blank" rel="noopener">Google Maps</a>
            <?php if ($sel['lat'] !== null): ?><a class="btn btn-secundario btn-chico" href="https://www.openstreetmap.org/directions?to=<?= e($sel['lat'] . ',' . $sel['lng']) ?>#map=16/<?= e($sel['lat'] . '/' . $sel['lng']) ?>" target="_blank" rel="noopener">OpenStreetMap</a>
            <a class="btn btn-secundario btn-chico" href="https://waze.com/ul?ll=<?= e($sel['lat'] . ',' . $sel['lng']) ?>&navigate=yes" target="_blank" rel="noopener">Waze</a><?php endif; ?>
          </span>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($etiquetas): ?>
      <div class="nube nube-evento">
        <?php foreach ($etiquetas as $g): ?><a class="etiqueta" href="/?etiqueta=<?= e($g['slug']) ?>">#<?= e($g['nombre']) ?></a><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <section class="resenas" id="resenas">
        <h2 class="seccion-titulo">Reseñas</h2>
        <?php if ($puntaje['n']): ?>
        <div class="resenas-resumen">
          <div class="rr-total"><b><?= e(str_replace('.', ',', (string)$puntaje['prom'])) ?></b><?= estrellas($puntaje['prom']) ?><span class="tenue chico"><?= $puntaje['n'] ?> <?= $puntaje['n'] === 1 ? 'reseña' : 'reseñas' ?> de asistentes</span></div>
          <div class="rr-barras">
            <?php for ($i = 5; $i >= 1; $i--): $pct = round($distribucion[$i] / $puntaje['n'] * 100); ?>
              <div class="rr-fila"><span><?= $i ?></span><span class="rr-barra"><i style="width:<?= $pct ?>%"></i></span><span class="tenue"><?= $distribucion[$i] ?></span></div>
            <?php endfor; ?>
          </div>
        </div>
        <?php else: ?>
          <p class="tenue">Todavía no hay reseñas. Las escriben quienes asistieron, después de cada función.</p>
        <?php endif; ?>

        <?php if ($para_resenar): ?>
        <form class="resena-form" method="post" action="/evento/<?= e($e['slug']) ?>/resena">
          <?= campo_csrf() ?>
          <h3>¿Cómo estuvo?</h3>
          <?php if (count($para_resenar) > 1): ?>
            <label class="campo"><span>Función a la que fuiste</span>
              <select name="funcion_id"><?php foreach ($para_resenar as $fn): ?><option value="<?= (int)$fn['id'] ?>"><?= e(ucfirst(fecha_corta($fn['inicio']))) ?> h · <?= e($fn['lugar']) ?></option><?php endforeach; ?></select></label>
          <?php else: ?>
            <input type="hidden" name="funcion_id" value="<?= (int)$para_resenar[0]['id'] ?>">
            <p class="tenue chico">Fuiste el <?= e(fecha_corta($para_resenar[0]['inicio'])) ?> h<?= $para_resenar[0]['lugar'] ? ' a ' . e($para_resenar[0]['lugar']) : '' ?>.</p>
          <?php endif; ?>
          <fieldset class="elegir-estrellas">
            <legend class="sr">Puntaje</legend>
            <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" name="puntaje" value="<?= $i ?>" id="p<?= $i ?>" required><label for="p<?= $i ?>" title="<?= $i ?> de 5">★</label><?php endfor; ?>
          </fieldset>
          <label class="campo"><span>Contanos <i>opcional</i></span><textarea name="texto" rows="3" maxlength="1200"></textarea></label>
          <button class="btn" type="submit">Publicar reseña</button>
        </form>
        <?php endif; ?>

        <?php if ($resenas): ?>
        <ul class="lista-resenas" data-resenas>
          <?php foreach ($resenas as $i => $r): ?>
          <li class="resena"<?= $i >= 5 ? ' hidden' : '' ?>>
            <div class="resena-cabecera">
              <span class="avatar"><?= e(mb_strtoupper(mb_substr($r['nombre'], 0, 1))) ?></span>
              <span><strong><?= e(explode(' ', $r['nombre'])[0]) ?></strong><span class="tenue chico">Asistió el <?= e(fecha_corta($r['inicio'])) ?> h<?= $r['lugar'] ? ' · ' . e($r['lugar']) : '' ?></span></span>
              <?= estrellas((float)$r['puntaje'], 'chicas') ?>
            </div>
            <?php if ($r['texto']): ?><p><?= nl2br(e($r['texto'])) ?></p><?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($resenas) > 5): ?><button type="button" class="btn btn-secundario btn-chico" data-mas-resenas>Ver las <?= count($resenas) ?> reseñas</button><?php endif; ?>
        <?php endif; ?>
      </section>
    </div>

    <aside class="compra" id="entradas">
      <form class="caja-compra" method="post" action="/evento/<?= e($e['slug']) ?>/comprar" data-compra data-max="<?= (int)$e['max_por_compra'] ?>">
        <?= campo_csrf() ?>
        <input type="hidden" name="funcion_id" value="<?= (int)($sel['id'] ?? 0) ?>">
        <h2 class="caja-titulo">Entradas</h2>
        <?php if ($sel): ?>
          <p class="caja-funcion"><b><?= e(ucfirst(fecha_corta($sel['inicio']))) ?> h</b><?= $sel['lugar'] ? '<br>' . e($sel['lugar']) : '' ?>
            <span class="stock stock-<?= $cls_stock ?> stock-en-linea"><?= e($terminado ? 'Finalizada' : ($cancelada ? 'Cancelada' : $txt_stock)) ?></span>
            <?php if (count($proximas) > 1): ?><br><a class="enlace-tenue" href="#fechas"><?= count($proximas) === 2 ? 'Ver la otra fecha' : 'Ver las otras ' . (count($proximas) - 1) . ' fechas' ?></a><?php endif; ?></p>
        <?php endif; ?>
        <?php if ($mias): ?><p class="ya-tenes">Ya tenés <?= $mias ?> <?= $mias === 1 ? 'entrada' : 'entradas' ?> para este evento. <a href="/mis-entradas">Verlas</a></p><?php endif; ?>
        <?php if (!$sel || !$tipos): ?>
          <p class="tenue">Todavía no hay entradas a la venta.</p>
        <?php else: ?>
        <ul class="tipos">
          <?php foreach ($tipos as $t): $sin = $t['quedan'] <= 0 || !$venta_abierta; ?>
          <li class="tipo<?= $sin ? ' tipo-sin' : '' ?>" data-precio="<?= (int)$t['precio'] ?>">
            <div class="tipo-info">
              <span class="tipo-nombre"><?= e($t['nombre']) ?></span>
              <?php if ($t['descripcion']): ?><span class="tipo-desc"><?= e($t['descripcion']) ?></span><?php endif; ?>
              <span class="tipo-precio"><?php if ($t['en_oferta']): ?><s class="precio-lista"><?= e(pesos((int)$t['precio_lista'])) ?></s> <?php endif; ?><?= (int)$t['precio'] ? e(pesos((int)$t['precio'])) : 'Sin cargo' ?></span>
              <?php if ($t['en_oferta']): ?><span class="oferta-hasta">Oferta<?= $t['oferta_hasta'] ? ' hasta el ' . e(fecha_corta($t['oferta_hasta'])) . ' h' : '' ?></span><?php endif; ?>
            </div>
            <?php if ($t['quedan'] <= 0): ?>
              <span class="stock stock-agotado stock-en-linea">Agotado</span>
            <?php elseif ($venta_abierta): ?>
              <div class="contador" data-contador>
                <button type="button" data-menos aria-label="Una menos">−</button>
                <input type="number" name="cant[<?= (int)$t['id'] ?>]" value="0" min="0" max="<?= min((int)$t['quedan'], (int)$e['max_por_compra']) ?>" inputmode="numeric" aria-label="Cantidad de <?= e($t['nombre']) ?>">
                <button type="button" data-mas aria-label="Una más">+</button>
              </div>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if ($venta_abierta && $tipos): ?>
          <div class="caja-total"><span>Total</span><strong data-total>$ 0</strong></div>
          <button class="btn btn-acento btn-bloque btn-grande" type="submit" data-comprar disabled>Comprar</button>
          <p class="caja-nota">Pagás por transferencia, <strong>sin cargo</strong>. Hasta <?= (int)$e['max_por_compra'] ?> por compra.<?php if ($sel['disponibles'] <= 30): ?> Quedan <?= (int)$sel['disponibles'] ?> lugares.<?php endif; ?> Recibís un QR por entrada.</p>
        <?php elseif ($terminado && $sel): ?>
          <p class="caja-cerrada"><?= $proximas ? 'Esta función ya terminó. Elegí otra fecha.' : 'El evento ya terminó.' ?></p>
        <?php elseif ($cancelada): ?>
          <p class="caja-cerrada">Función cancelada.</p>
        <?php elseif ($sin_medio): ?>
          <p class="caja-cerrada">La venta online cerró: falta poco para la función y el pago por transferencia necesita tiempo para verificarse.<?= count($proximas) > 1 ? ' Mirá las otras fechas.' : '' ?></p>
        <?php elseif ($agotado): ?>
          <p class="caja-cerrada"><?= count($proximas) > 1 ? 'Agotada. Probá otra fecha.' : 'Entradas agotadas.' ?></p>
        <?php endif; ?>
      </form>
    </aside>
  </div>
</article>
<?php if ($venta_abierta && $tipos): $desde = min(array_column(array_filter($tipos, fn($t) => $t['quedan'] > 0), 'precio') ?: [0]); ?>
<a class="barra-compra" href="#entradas" data-barra-compra>
  <span><span class="tenue chico"><?= e(ucfirst(fecha_corta($sel['inicio']))) ?> h</span><strong><?= $desde ? 'Desde ' . e(pesos((int)$desde)) : 'Sin cargo' ?></strong></span>
  <span class="btn btn-acento">Comprar</span>
</a>
<?php endif; ?>
<?php if ($sel && $sel['lat'] !== null): ?><script src="<?= estatico('mapa.js') ?>" defer></script><?php endif; ?>
