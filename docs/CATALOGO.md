# Búsqueda y filtros del catálogo

`tienda/public/catalogo.php` reúne los perfumes activos de categorías activas. Las URL anteriores de `categoria.php?c=...` siguen funcionando con el mismo buscador, restringido a esa categoría. No se necesita una migración de base de datos.

## Uso

- Buscar por nombre, marca o descripción corta/larga, hasta 100 caracteres. La colación del esquema permite buscar sin distinguir mayúsculas ni acentos. `%`, `_` y `!` se interpretan literalmente.
- Combinar categoría, marca, precio mínimo/máximo y disponibilidad. Los precios admiten punto o coma decimal, con dos decimales como máximo.
- Ordenar por nombre, precio ascendente/descendente o incorporación reciente. Los empates usan el identificador para que el orden sea estable.
- Recorrer resultados de doce en doce conservando los filtros en la URL. Aplicar nuevos filtros vuelve a la primera página. Limpiar filtros mantiene la categoría en las páginas de categoría.

## Precios y disponibilidad

Se conserva la regla actual del carrito: un `precio_oferta` positivo sustituye al precio normal, independientemente de `es_oferta`. Para un producto con variantes se muestra el menor precio de las variantes activas con stock. Si están todas agotadas, se muestra el menor precio activo junto a «Agotado». Si solo existen variantes inactivas, se marca agotado sin anunciar el precio base.

Los filtros de precio y la ordenación usan ese mismo importe «desde». No buscan cualquier capacidad dentro del rango: por ejemplo, un producto disponible desde 40 € queda fuera de un filtro mínimo de 50 €, aunque tenga otra capacidad de 70 €. Sin variantes se usan el precio y el stock del producto.

La ficha presenta primero la capacidad disponible más barata, deshabilita las agotadas y oculta las inactivas. No permite iniciar una compra desde un producto agotado. El servidor revalida precio, stock y categoría activa al añadir al carrito y al confirmar. Dentro de la transacción bloquea la categoría en modo compartido: permite compras simultáneas y evita que se retire antes de terminar la confirmación. Una categoría ya retirada bloquea también los carritos y resúmenes anteriores. Las fichas de productos o categorías inactivos devuelven 404.

## Validación y límites

Consultas preparadas, ordenaciones permitidas explícitamente y HTML escapado. Los parámetros no escalares, los precios inválidos y los intervalos invertidos devuelven 422; categorías, marcas o páginas inexistentes devuelven 404 antes de emitir HTML. Una búsqueda válida sin coincidencias devuelve 200 con una explicación.

La consulta utiliza agregación de variantes y búsqueda por fragmentos. Es adecuada para el catálogo del prototipo; faltan pruebas de carga e índices o búsqueda especializada para grandes volúmenes. El recuento y las filas se consultan por separado: los cambios concurrentes de catálogo pueden modificar el resultado entre peticiones.

Las búsquedas viajan en la URL para poder compartir filtros; no introducir datos personales en el buscador. La aplicación no crea un historial de búsquedas en la base de datos. La conservación de URL en el historial del navegador o en logs depende de su configuración.

La revisión visual cubre escritorio y el recorrido de búsqueda; quedan pendientes dispositivos móviles reales, lectores de pantalla y una auditoría completa de accesibilidad.
