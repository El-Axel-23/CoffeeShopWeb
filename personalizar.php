<?php
require_once __DIR__ . '/Login/sesion.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (isset($_SESSION['id_usuario'])) {
    verificarSesion('Login/login.php');
}
require_once __DIR__ . '/config/conexion.php';

$idProducto = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idProducto || $idProducto <= 0) {
    header('Location: index.php#menu');
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id_producto = ? AND disponible = 1");
$stmt->execute([$idProducto]);
$producto = $stmt->fetch();
if (!$producto) {
    header('Location: index.php#menu');
    exit;

}
$titulo = 'Personalizar - Coffee Shop';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-6xl mx-auto w-full px-4 md:px-6 py-6">
    <a href="index.php#menu" class="text-xs text-secundario hover:text-cafe mb-5 inline-block">
        <i class="bi bi-arrow-left"></i> Menú / Personalización
    </a>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
        <div class="relative rounded-2xl overflow-hidden bg-crema min-h-64 md:min-h-[420px]">
            <?php if (!empty($producto['imagen_url']) && is_file(__DIR__ . '/' . $producto['imagen_url'])): ?>
                <img src="<?= htmlspecialchars($producto['imagen_url']) ?>" alt="<?= htmlspecialchars($producto['nombre']) ?>" class="absolute inset-0 w-full h-full object-cover">
            <?php else: ?>
                <div class="absolute inset-0 flex items-center justify-center text-6xl">☕</div>
            <?php endif; ?>
            <?php if (isset($_SESSION['id_usuario'])): ?>
                <?php
                if (!isset($_SESSION['csrf_favoritos'])) {
                    $_SESSION['csrf_favoritos'] = bin2hex(random_bytes(32));
                }
                $favoritoStmt = $pdo->prepare("SELECT 1 FROM favoritos WHERE id_usuario = ? AND id_producto = ?");
                $favoritoStmt->execute([$_SESSION['id_usuario'], $idProducto]);
                $esFavorito = (bool) $favoritoStmt->fetchColumn();
                ?>
                <form method="POST" action="Favoritos/<?= $esFavorito ? 'eliminar_favorito.php' : 'agregar_favorito.php' ?>" class="absolute top-3 right-3">
                    <input type="hidden" name="id_producto" value="<?= $idProducto ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_favoritos']) ?>">
                    <input type="hidden" name="origen" value="catalogo">
                    <button type="submit" aria-label="<?= $esFavorito ? 'Quitar favorito' : 'Agregar favorito' ?>" class="w-9 h-9 bg-white rounded-full flex items-center justify-center shadow-sm">
                        <i class="bi <?= $esFavorito ? 'bi-heart-fill text-cafe' : 'bi-heart text-moka' ?>"></i>
                    </button>
                </form>
            <?php else: ?>
                <a href="Login/login.php" class="absolute top-3 right-3 w-9 h-9 bg-white rounded-full flex items-center justify-center shadow-sm" aria-label="Inicia sesión para guardar favoritos">
                    <i class="bi bi-heart"></i>
                </a>
            <?php endif; ?>
        </div>
        <section class="flex flex-col gap-4">
            <div class="flex items-start justify-between gap-3">
                <h1 class="text-lg font-bold"><?= htmlspecialchars($producto['nombre']) ?></h1>
                <span class="font-bold text-sm whitespace-nowrap">$<?= number_format($producto['precio'], 2) ?></span>
            </div>
            <p class="text-sm leading-relaxed"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></p>
            <h2 class="font-semibold text-sm">Personalización</h2>
            <div>
                <label class="block text-xs font-semibold mb-2">Tamaño</label>
                <div class="flex flex-wrap gap-2 text-xs">
                    <label class="rounded-full bg-durazno px-3 py-2"><input type="radio" name="tamano" value="Chico" checked> Chico</label>
                    <label class="rounded-full bg-durazno px-3 py-2"><input type="radio" name="tamano" value="Mediano"> Mediano</label>
                    <label class="rounded-full bg-durazno px-3 py-2"><input type="radio" name="tamano" value="Grande"> Grande</label>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-2">Leche</label>
                <div class="flex flex-wrap gap-2 text-xs">
                    <label class="rounded-full bg-durazno px-3 py-2"><input type="radio" name="leche" value="Entera" checked> Entera</label>
                    <label class="rounded-full bg-durazno px-3 py-2"><input type="radio" name="leche" value="Deslactosada"> Deslactosada</label>
                    <label class="rounded-full bg-durazno px-3 py-2"><input type="radio" name="leche" value="Avena"> Avena</label>
                    <label class="rounded-full bg-durazno px-3 py-2"><input type="radio" name="leche" value="Almendra"> Almendra</label>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-2">Extra</label>
                <div class="flex flex-wrap gap-2 text-xs">
                    <label class="rounded-full bg-durazno px-3 py-2"><input type="checkbox" name="extras[]" value="Sirope Vainilla"> Sirope Vainilla</label>
                    <label class="rounded-full bg-durazno px-3 py-2"><input type="checkbox" name="extras[]" value="Canela"> Canela</label>
                </div>
            </div>
            <button type="button" onclick="alert('La pantalla de personalización ya está conectada. La integración del carrito se realizará en el siguiente paso.')" class="w-full rounded-lg bg-moka text-white py-3 text-xs font-semibold hover:opacity-90">
                AGREGAR AL CARRITO
            </button>
        </section>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
