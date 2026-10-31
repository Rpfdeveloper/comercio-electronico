# Carrito y proceso de compra

## Comportamiento

El carrito muestra productos, IVA, envío y total. «Continuar con la compra» guarda primero las cantidades del formulario, comprueba disponibilidad y abre los datos de envío. Una cantidad de cero elimina la línea; si no quedan productos, vuelve al carrito vacío. «Eliminar» funciona aunque otro campo de cantidad esté vacío o sea inválido.

El checkout permite comprar como invitado o con una cuenta. Incluye etiquetas, autocompletado, errores junto al campo y un resumen de errores con enlaces. Al fallar, el foco se sitúa en ese resumen. Los datos válidos se conservan para corregirlos y la contraseña nunca se devuelve al HTML. JavaScript facilita mostrar/ocultar la contraseña y evita pedirla a invitados; el servidor mantiene las mismas validaciones sin JavaScript.

El resumen permite revisar dirección y productos antes de confirmar. Modificar el carrito o enviar un formulario inválido anula el resumen anterior. La confirmación vuelve a comprobar categorías, precios y stock dentro de una transacción. Las cantidades no disponibles permiten regresar al carrito para corregirlas.

## Validación y límites

Se rechazan campos obligatorios vacíos, correos inválidos, tipos no escalares, texto UTF-8 incorrecto, controles y longitudes que excedan la base de datos. Los límites se cuentan en caracteres Unicode: correo 160, nombre 120, dirección 200, código postal 12, población/provincia/país 120. La provincia es opcional. Se mantienen las reglas y límites de acceso de cuentas existentes.

No requiere migraciones. Se conserva la regla del prototipo: IVA fijo del 21 % añadido al importe de productos y envío de 0 €. Los importes se redondean a dos decimales con el mismo cálculo en todos los pasos. La política comercial configurable y la revisión de precios/impuestos para producción siguen pendientes.

El carrito continúa ligado a la sesión y el stock se descuenta al registrar el pedido. No hay pagos, reservas temporales ni envío real de correos.

## Comprobaciones

47 regresiones HTTP nuevas, además de las 239 existentes. Comprueban cantidades al continuar, errores sin cambios en el carrito, límites Unicode, errores asociados a campos, resúmenes invalidados, escape HTML, ausencia de contraseñas reflejadas, importes persistidos, stock, confirmación repetida y recuperación del carrito.

Revisión visual con datos ficticios: escritorio y marcos de navegador de 320, 390 y 768 píxeles, sin desbordamiento horizontal en carrito ni formulario. Comprobados foco en errores, enlaces a campos, selector invitado/cuenta y mostrar/ocultar contraseña. Se corrigió el desbordamiento móvil del contador de unidades. Faltan dispositivos reales, lectores de pantalla y auditoría completa de accesibilidad.

[Resultados de validación](PROGRESO.md) · [Ejecutar las pruebas](../tests/README.md).
