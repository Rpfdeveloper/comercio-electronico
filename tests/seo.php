<?php
declare(strict_types=1);
require_once __DIR__ . '/support.php';
require_once __DIR__ . '/../tienda/src/lib/seo.php';
$originalPublicUrl = getenv('TIENDA_PUBLIC_URL');
foreach (['http://shop.example.invalid', 'https://127.0.0.1', 'https://localhost', 'https://user:pass@example.invalid', 'https://shop.example.invalid/?x=1', 'https://shop.example.invalid/#x', 'https://shop.example.invalid/../x', 'https://shop.example.invalid:443'] as $bad) {
    putenv('TIENDA_PUBLIC_URL=' . $bad);
    check(seo_public_base() === '', 'configuracion SEO rechaza URL insegura o ambigua');
}
putenv('TIENDA_PUBLIC_URL=https://shop.example.invalid/tienda/public/');
check(seo_absolute('producto.php?p=floral') === 'https://shop.example.invalid/tienda/public/producto.php?p=floral', 'URL canonica parte solo de configuracion');
check(seo_description('  Perfume <b>floral</b>  suave ') === 'Perfume floral suave', 'descripcion normaliza texto sin etiquetas');
check(preg_match('//u', seo_description(str_repeat('á',180))) === 1 && str_ends_with(seo_description(str_repeat('á',180)), '…'), 'recorta descripcion sin romper Unicode');
putenv($originalPublicUrl === false ? 'TIENDA_PUBLIC_URL' : 'TIENDA_PUBLIC_URL=' . $originalPublicUrl);
$b = new TestBrowser();
$body = $b->request('public/index.php');
check(str_contains($b->headers['x-robots-tag'] ?? '', 'noindex') && str_contains($body, 'name="robots" content="noindex'), 'prototipo no indexable por defecto');
$b->request('public/sitemap.php');
check($b->status === 503, 'sitemap desactivado antes del lanzamiento');
// Servidor local aislado para probar la futura configuracion de produccion.
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$socket) { throw new RuntimeException('No se pudo reservar puerto local'); }
$address = stream_socket_get_name($socket,false); fclose($socket);
$temp = sys_get_temp_dir() . '/perfume-seo-' . bin2hex(random_bytes(5));
mkdir($temp,0700);
$env = array_merge(getenv(), ['TIENDA_DEBUG'=>'false','TIENDA_INDEXABLE'=>'true','TIENDA_PUBLIC_URL'=>'https://shop.example.invalid/tienda/public']);
unset($env['PHP_CLI_SERVER_WORKERS']);
$server = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $temp, '-d', 'display_errors=1', '-d', 'error_reporting=E_ALL', '-S', $address, '-t', realpath(__DIR__ . '/../tienda')],
    [0=>['pipe','r'],1=>['file',$temp . '/server.txt','a'],2=>['file',$temp . '/server.txt','a']], $pipes, null, $env);
$oldBase=$base; $base='http://' . $address;
$prefix='seo-' . bin2hex(random_bytes(4)); $category=null; $empty=null; $brand=null; $ids=[]; $activeCategories=[];
try {
    fclose($pipes[0]);
    $ready=false;
    for ($i=0;$i<80;++$i) {
        $connection=@stream_socket_client('tcp://' . $address,$errno,$errstr,.1);
        if ($connection) { fclose($connection);$ready=true;break; }
        usleep(25000);
    }
    check($ready, 'servidor SEO de pruebas disponible');
    foreach ([1,0] as $withProducts) {
        $pdo->prepare('INSERT INTO categoria(nombre,slug,activa) VALUES (?,?,1)')->execute(['Colección SEO', $prefix . '-' . $withProducts]);
        if ($withProducts) { $category=(int)$pdo->lastInsertId(); } else { $empty=(int)$pdo->lastInsertId(); }
    }
    $pdo->prepare('INSERT INTO marca(nombre,slug) VALUES (?,?)')->execute(['Marca SEO',$prefix]); $brand=(int)$pdo->lastInsertId();
    for ($i=0;$i<13;++$i) {
        $pdo->prepare('INSERT INTO producto(id_categoria,id_marca,nombre,slug,descripcion_larga,precio,stock,activo) VALUES (?,?,?,?,?,20,1,1)')
            ->execute([$category,$brand,'Perfume SEO '.$i,$prefix.'-'.$i,'Descripción <b>floral</b> única.']);
        $ids[]=(int)$pdo->lastInsertId();
    }
    $body=$b->request('public/index.php?utm_source=prueba');
    check($b->headers['x-robots-tag']==='index, follow' && str_contains($body,'rel="canonical" href="https://shop.example.invalid/tienda/public/index.php"'), 'portada lista usa canonica sin seguimiento');
    $body=$b->request('public/producto.php?p='.$prefix.'-0');
    check(str_contains($body,'<title>Perfume SEO 0 · Marca SEO') && str_contains($body,'<h1>Perfume SEO 0</h1>'), 'ficha tiene titulo y encabezado propios');
    check(str_contains($body,'name="description" content="Perfume SEO 0. Descripción floral única."'), 'ficha tiene descripcion propia sin HTML');
    check(str_contains($body,'property="og:url"') && $b->headers['x-robots-tag']==='index, follow', 'ficha publica preparada para compartir e indexar');
    $body=$b->request('public/categoria.php?c='.$prefix.'-1&page=2');
    check(str_contains($body,'categoria.php?c='.$prefix.'-1&amp;page=2"') && str_contains($body,'Página 2 · Perfume Shop</title>'), 'pagina dos tiene canonica y titulo propios');
    $body=$b->request('public/catalogo.php?c='.$prefix.'-1');
    check(str_contains($body,'rel="canonical" href="https://shop.example.invalid/tienda/public/categoria.php?c='.$prefix.'-1"'), 'duplicado de categoria apunta a ruta preferida');
    foreach (['public/catalogo.php?q=floral','public/catalogo.php?orden=precio-asc','public/catalogo.php?stock=1','public/descubrir.php?buscar=1&q=floral','public/favoritos.php','public/carrito.php','public/acceso.php','public/cuenta.php','admin/login.php','public/categoria.php?c='.$prefix.'-0','public/producto.php?p=missing-'.$prefix,'public/catalogo.php?page=999'] as $path) {
        $b->request($path);
        check(str_contains($b->headers['x-robots-tag'] ?? '', 'noindex'), 'excluye de indexacion '.$path);
    }
    $pdo->prepare('UPDATE producto SET activo=0 WHERE id=?')->execute([$ids[0]]);
    $xml=$b->request('public/sitemap.php');
    check($b->status===200 && str_contains($b->headers['content-type'],'application/xml'), 'sitemap XML activo solo con configuracion lista');
    check(!str_contains($xml,'producto.php?p='.$prefix.'-0<') && str_contains($xml,'producto.php?p='.$prefix.'-1<'), 'sitemap contiene solo productos activos');
    check(!str_contains($xml,'favoritos.php') && !str_contains($xml,'carrito.php') && !str_contains($xml,'categoria.php?c='.$prefix.'-0<'), 'sitemap excluye paginas personales y categorias vacias');
    preg_match_all('~<loc>(.*?)</loc>~',$xml,$matches);
    check(count($matches[1])===count(array_unique($matches[1])), 'sitemap sin URLs duplicadas');
    foreach ($matches[1] as $encoded) {
        $location=html_entity_decode($encoded,ENT_XML1|ENT_QUOTES,'UTF-8');
        $path='public/'.substr($location,strlen('https://shop.example.invalid/tienda/public/'));
        $body=$b->request($path);
        if ($b->status!==200 || $b->headers['x-robots-tag']!=='index, follow' || !str_contains($body,'rel="canonical" href="'.htmlspecialchars($location,ENT_QUOTES,'UTF-8').'"')) { throw new RuntimeException('Sitemap incoherente con pagina publica'); }
    }
    check(true,'cada URL del sitemap responde 200 indexable con canonica identica');
    $pdo->prepare('UPDATE categoria SET activa=0 WHERE id=?')->execute([$category]);
    $xml=$b->request('public/sitemap.php');
    check(!str_contains($xml,$prefix),'sitemap retira categorias inactivas y sus productos');
    $activeCategories=$pdo->query('SELECT id FROM categoria WHERE activa=1')->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec('UPDATE categoria SET activa=0 WHERE activa=1');
    $xml=$b->request('public/sitemap.php');
    check($b->status===200 && !str_contains($xml,'catalogo.php') && !str_contains($xml,'producto.php') && str_contains($xml,'index.php'), 'sitemap vacio no incluye catalogo sin contenido');
    echo "PASS $checks comprobaciones de SEO\n";
} finally {
    foreach ($activeCategories as $id) { $pdo->prepare('UPDATE categoria SET activa=1 WHERE id=?')->execute([$id]); }
    $base=$oldBase;
    proc_terminate($server); proc_close($server);
    $logs=file_get_contents($temp.'/server.txt');
    foreach (glob($temp.'/*') as $file) { unlink($file); } rmdir($temp);
    foreach ($ids as $id) { $pdo->prepare('DELETE FROM producto WHERE id=?')->execute([$id]); }
    foreach ([$category,$empty] as $id) { if ($id!==null) { $pdo->prepare('DELETE FROM categoria WHERE id=?')->execute([$id]); } }
    if ($brand!==null) { $pdo->prepare('DELETE FROM marca WHERE id=?')->execute([$brand]); }
    if (preg_match('/PHP (Warning|Fatal error|Deprecated)|Uncaught/',$logs)) { throw new RuntimeException('Error en log SEO'); }
}
