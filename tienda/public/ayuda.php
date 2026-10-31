<?php
require_once __DIR__ . '/../config/init.php';
$pageTitle='Ayuda para descubrir y comprar';
$pageDescription='Cómo explorar el catálogo, guardar favoritos y utilizar el carrito del prototipo Perfume Shop.';
$pageCanonical='ayuda.php'; $pageIndexable=true;
require __DIR__ . '/includes/header.php';
?>
<div class="storefront help-page">
<section class="discovery-intro"><p class="store-eyebrow">TE ACOMPAÑAMOS</p><h1>Explora con claridad.</h1><p class="store-lead">Lo que puedes hacer hoy y lo que seguimos preparando.</p></section>
<div class="help-links"><a class="btn" href="<?= h(url('descubrir.php')) ?>">Encontrar un perfume</a><a class="btn store-secondary" href="<?= h(url('favoritos.php')) ?>">Volver a mis favoritos</a></div>
<section class="help-questions" aria-label="Preguntas frecuentes">
<details open><summary>¿Es una tienda abierta a compras reales?</summary><p>Esta web es un proyecto en desarrollo. Puedes explorar productos y recorrer el proceso de un pedido de prueba, pero no admite pagos reales ni ofrece un servicio de envío. No introduzcas datos personales reales para probarla.</p></details>
<details><summary>¿Cómo funciona el asesor?</summary><p>Elige una colección, escribe una palabra y, si quieres, indica un presupuesto. La guía filtra el catálogo disponible. No hay una IA conectada ni se inventan características o afinidades. Puedes omitir preferencias y cambiar entre el modo paso a paso y el formulario completo.</p></details>
<details><summary>¿Puedo guardar perfumes para después?</summary><p>Sí. Sin iniciar sesión, tus favoritos se guardan en este navegador. Con una cuenta del prototipo puedes recuperarlos en otros dispositivos. Si has elegido perfumes antes de acceder, podrás unirlos a tu cuenta de forma explícita. Guardarlos no reserva stock.</p><a href="<?= h(url('favoritos.php')) ?>">Abrir mis favoritos →</a></details>
<details><summary>¿Qué significan los formatos y los precios?</summary><p>Cada capacidad tiene su propio precio y sus existencias. El precio «desde» corresponde a la opción más económica disponible; si no queda ninguna, se muestra el mínimo de las opciones activas. En este prototipo, el carrito desglosa el precio de los productos, el IVA y el total antes de confirmar.</p></details>
<details><summary>¿Por qué puede cambiar mi carrito?</summary><p>Añadir un perfume no reserva unidades. Al revisar y confirmar, se vuelven a comprobar el precio, el formato y las existencias. Si algo ha cambiado, tendrás que revisar el carrito antes de continuar.</p><a href="<?= h(url('carrito.php')) ?>">Revisar mi carrito →</a></details>
<details><summary>¿Cómo accedo a mi cuenta?</summary><p>«Mi cuenta» corresponde a clientes y usa correo y contraseña. Las cuentas actuales se crean durante un pedido de prueba con la opción de registro. El área de administración tiene un acceso separado en el pie de la web.</p><a href="<?= h(url('cuenta.php')) ?>">Ir a mi cuenta →</a></details>
<details><summary>¿Cómo puedo contactar?</summary><p>Contacto próximamente. Todavía no hay un canal de atención operativo; lo mostraremos aquí cuando esté preparado.</p></details>
</section>
<aside class="store-discovery"><div><p class="store-eyebrow">TU PRÓXIMO DESCUBRIMIENTO</p><h2>Vuelve a lo que te gusta.</h2><p>Tu selección te espera. Explora el catálogo y guarda las fragancias que quieras revisar con calma.</p><a class="btn" href="<?= h(url('catalogo.php')) ?>">Explorar el catálogo →</a></div></aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
