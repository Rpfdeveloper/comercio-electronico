    </main>
    <footer class="store-footer">
      <div class="container store-footer-grid">
        <div><p class="store-footer-brand"><?= h(APP_NAME) ?></p><p>Encuentra una fragancia que hable de ti.</p><p class="store-caption">Proyecto en desarrollo. No admite pagos reales.</p></div>
        <nav aria-label="Enlaces de la tienda"><a href="<?= h(url('catalogo.php')) ?>">Explorar catálogo</a><a href="<?= h(url('descubrir.php')) ?>">Encuentra tu perfume</a><a href="<?= h(url('favoritos.php')) ?>">Mis favoritos</a><a href="<?= h(url('cuenta.php')) ?>">Mi cuenta</a></nav>
        <section id="contacto" aria-labelledby="contact-title"><h2 id="contact-title">Contacto próximamente</h2><p>Estamos preparando nuestro canal de atención.</p></section>
      </div>
      <div class="container store-footer-bottom"><p>&copy; <?= date('Y') ?> <?= h(APP_NAME) ?> · Proyecto académico de Comercio Electrónico.</p><p>Imágenes del catálogo conservadas del proyecto original.</p></div>
    </footer>
    <script src="<?= asset('js/main.js?v=1.4') ?>" defer></script>
  </body>
</html>
