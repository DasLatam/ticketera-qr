# Seguridad

## Cómo reportar una vulnerabilidad

**No abras un issue público.** Usá el reporte privado de GitHub:
pestaña **Security › Report a vulnerability** del repositorio
(GitHub Security Advisories). Contanos qué encontraste, cómo reproducirlo y
qué impacto tiene. Te respondemos por ese mismo canal.

## Alcance

Interesa especialmente: acceso a entradas, comprobantes o datos de otras
personas; validar dos veces la misma entrada en la puerta; sobreventa;
saltear el «No soy un robot»; inyección SQL o XSS; lectura de la carpeta de datos.

## Recomendaciones para quien lo instala

- La carpeta de datos (`DIR_DATOS`) **siempre fuera** de la carpeta pública.
- `MODO_DEMO = false` y **https** en producción.
- `config.local.php`, `secretos.php` y la base nunca en un repositorio.
- Mantené PHP actualizado.
