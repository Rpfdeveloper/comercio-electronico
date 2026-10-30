    </main>

    <!-- Pie de página con créditos y contexto académico -->
    <footer class="container"
            style="text-align:center; margin-top:2rem; padding:1rem 0;
                   border-top:1px solid #e5e7eb; color:#666; font-size:0.9rem;">
        <p>
            &copy; <?= date('Y') ?> <?= h(APP_NAME) ?>.
            Imágenes de stock con licencia libre.
            Las marcas y nombres de perfumes mencionados son propiedad de sus respectivos titulares.
        </p>
        <p style="font-size:0.8rem; margin-top:0.5rem;">
            Proyecto académico desarrollado en el marco de la asignatura
            <em>Comercio Electrónico (UOC)</em>.
        </p>
    </footer>

    <!-- JavaScript principal de la tienda -->
    <script src="<?= asset('js/main.js') ?>"></script>
  </body>
</html>