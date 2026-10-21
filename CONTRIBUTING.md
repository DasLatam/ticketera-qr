# Cómo contribuir

¡Gracias por sumarte! Algunas pautas cortas:

1. **Abrí un issue antes** de un cambio grande, para charlar el enfoque.
2. **Sin dependencias nuevas** salvo que sea imprescindible: la idea es que
   siga corriendo en un hosting compartido con PHP 8.2 y sin Composer.
3. **Cambios de esquema**: van en `MIGRACIONES` (`app/nucleo/base.php`) **y**
   en `app/nucleo/esquema.sql`. Nunca reordenar ni borrar migraciones.
4. **Probalo con datos de ejemplo**: `php scripts/semilla.php --ejemplo` y
   `./servir.sh`. Recorré la compra y la puerta antes de mandar el cambio.
5. **Nada de datos reales** en el repositorio: ni bases, ni comprobantes, ni
   claves, ni correos de personas. Los ejemplos usan `@demo.invalid`.
6. Textos de la interfaz en castellano rioplatense, claros y sin tecnicismos.
7. Un pull request por tema, con una descripción de qué cambia y cómo se probó.

Al contribuir aceptás que tu aporte se publique bajo la licencia del proyecto
(AGPL-3.0 o posterior).
