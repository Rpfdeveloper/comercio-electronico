# Colecciones y marcas

**Administración → Colecciones y marcas** permite crear y editar las colecciones (tabla `categoria`) y las marcas. No hay borrado: los perfumes y los pedidos históricos las referencian.

## Uso

- **Colección:** nombre, enlace, descripción opcional, orden en la tienda (0–9999, menor primero) y visibilidad. Las nuevas se crean **ocultas**, aunque se manipule el formulario.
- **Ocultar una colección** retira de la tienda todos sus perfumes, aunque estén activos. Si tiene perfumes visibles, hay que marcar una confirmación.
- **Marca:** nombre, enlace, país y descripción opcionales. No tiene visibilidad propia: el filtro de marcas del catálogo solo muestra las que tienen perfumes visibles.
- **Enlaces:** minúsculas, números y guiones, únicos. Se fijan al crear para no romper direcciones publicadas ni favoritos.
- El listado muestra los perfumes activos y totales de cada una.

## Seguridad y consistencia

Misma sesión administrativa, CSRF, no-store y noindex que el resto del panel. Cada edición compara una huella de los valores mostrados: un formulario antiguo devuelve **409** sin sobrescribir. Los datos inválidos devuelven 422 y conservan lo escrito. Nombres y descripciones se muestran escapados.

## Actualización

No requiere migración. Desplegar `admin/colecciones.php`, `admin/coleccion.php`, `src/lib/admin_taxonomy.php`, `src/lib/admin_view.php` y `admin/assets/admin.css` (`?v=5`).

## Validación

`tests/admin_taxonomy.php`: 54 comprobaciones HTTP (acceso, validación de cada campo, enlaces duplicados y fijos, alta oculta, visibilidad en la tienda, confirmación al ocultar y ficha que deja de mostrarse, conflictos de versión, marcas, rutas inválidas y ausencia de borrado). **771 comprobaciones por entorno** en PHP 8.3, 8.4 y 8.5 con MariaDB 11.4.

## Límites

Sin historial de cambios, imágenes de colección ni fusión de marcas duplicadas. Las colecciones originales «Women/Men Perfumes» se siguen mostrando como «Mujer» y «Hombre».
