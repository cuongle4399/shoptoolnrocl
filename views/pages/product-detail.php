<?php
$page_title = 'Chi tiết sản phẩm - ShopToolNro';
include '../layout/header.php';

require_once '../../config/database.php';
require_once '../../src/classes/ProductOptimized.php'; // Use optimized class
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->connect();
$productClass = new ProductOptimized($db);

// OPTIMIZED: 1 API call lấy cả product + durations
$product = $productClass->getProductDetail($_GET['id'] ?? 0);

if (!$product) {
    echo '<div class="alert alert-danger">Sản phẩm không tồn tại</div>';
    include '../layout/footer.php';
    exit;
}

// Durations đã có sẵn trong $product['durations']
$durations = $product['durations'] ?? [];
?>

<div class="main-content fade-in">
    <div class="grid-2">
        <div>
            <?php
                $demo_images = [];
                if (!empty($product['demo_image_url'])) {
                    $raw = $product['demo_image_url'];
                    $maybe = @json_decode($raw, true);
                    if (is_array($maybe)) $demo_images = $maybe;
                    elseif (!empty($raw)) $demo_images = [$raw];
                }
            ?>
            <?php if (!empty($demo_images)): ?>
                <h3 class="mt-0">Hình ảnh demo</h3>
                <?php if (count($demo_images) > 1): ?>
                    <div class="demo-carousel">
                        <img id="demoImage" src="<?php echo htmlspecialchars($demo_images[0]); ?>" class="img-full mt-10" alt="Demo">
                        <div class="carousel-controls">
                            <button class="carousel-btn prev" onclick="prevDemoImage()">❮</button>
                            <span class="carousel-counter"><span id="currentIndex">1</span>/<span id="totalCount"><?php echo count($demo_images); ?></span></span>
                            <button class="carousel-btn next" onclick="nextDemoImage()">❯</button>
                        </div>
                    </div>
                    <script>
                        const demoImages = <?php echo json_encode($demo_images); ?>;
                        let currentDemoIndex = 0;
                        
                        function updateDemoImage() {
                            document.getElementById('demoImage').src = demoImages[currentDemoIndex];
                            document.getElementById('currentIndex').textContent = currentDemoIndex + 1;
                        }
                        
                        window.prevDemoImage = function() {
                            currentDemoIndex = (currentDemoIndex - 1 + demoImages.length) % demoImages.length;
                            updateDemoImage();
                        }
                        
                        window.nextDemoImage = function() {
                            currentDemoIndex = (currentDemoIndex + 1) % demoImages.length;
                            updateDemoImage();
                        }
                    </script>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($demo_images[0]); ?>" class="img-full mt-10" alt="Demo">
                <?php endif; ?>
            <?php else: ?>
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" class="img-full" alt="<?php echo htmlspecialchars($product['name']); ?>">
            <?php endif; ?>
        </div>
        <div>
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            <div class="product-price display-price" id="displayPrice"><?php echo !empty($durations) ? number_format($durations[0]['price'] ?? 0, 0, ',', '.') : number_format($product['price'] ?? 0, 0, ',', '.'); ?> ₫</div>

            <?php if (!empty($durations)): ?>
                <div class="mt-15">
                    <label><strong>Chọn thời hạn</strong></label>
                    <div class="inline-group mt-8">
                        <?php foreach ($durations as $d): ?>
                            <label class="option-label">
                                <input type="radio" name="duration_id" value="<?php echo $d['id']; ?>" data-price="<?php echo $d['price']; ?>" <?php echo $d === reset($durations) ? 'checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($d['duration_label'] ?? ($d['duration_days'] . ' ngày')); ?> — <?php echo number_format($d['price'], 0, ',', '.'); ?> ₫</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="product-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            <?php endif; ?>
            
            <?php 
                $video_url = normalizeYoutubeEmbedUrl($product['tutorial_video_url'] ?? '');
                if ($video_url): ?>
                <h3 class="mt-20">Video hướng dẫn</h3>
                <iframe width="100%" height="315" src="<?php echo htmlspecialchars($video_url); ?>" frameborder="0" allowfullscreen class="mt-10 rounded"></iframe>
            <?php endif; ?>
            
            
            <div class="mt-30">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="btn btn-primary btn-fullwidth btn-lg" onclick="buyProduct(<?php echo $product['id']; ?>)">Mua ngay</button>
                <?php else: ?>
                    <p class="text-danger mb-3">Vui lòng <a href="/ShopToolNro/views/pages/login.php">đăng nhập</a> để mua sản phẩm</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function buyProduct(productId) {
    // Get selected duration if available
    const selected = document.querySelector('input[name="duration_id"]:checked');
    const durationParam = selected ? ('&duration_id=' + selected.value + '&duration_price=' + encodeURIComponent(selected.dataset.price)) : '';
    // Redirect to checkout with both id and price to ensure checkout displays chosen price
    window.location.href = '/ShopToolNro/views/pages/checkout.php?product_id=' + productId + durationParam;
}

// Update displayed price when duration changes
document.addEventListener('change', (e) => {
    if (e.target && e.target.name === 'duration_id') {
        const price = e.target.dataset.price;
        document.getElementById('displayPrice').textContent = new Intl.NumberFormat('vi-VN').format(price) + ' ₫';
    }
});
</script>

<style>
.demo-carousel {
    position: relative;
    margin-top: 10px;
}

.demo-carousel img {
    width: 100%;
    border-radius: 8px;
    display: block;
    transition: opacity 0.3s ease;
}

.carousel-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-top: 12px;
    padding: 10px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 8px;
}

.carousel-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    font-weight: bold;
}

.carousel-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
}

.carousel-btn:active {
    transform: scale(0.95);
}

.carousel-counter {
    font-weight: 600;
    color: #1f2937;
    font-size: 14px;
    background: white;
    padding: 6px 12px;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
</style>
</script>

<?php include '../layout/footer.php'; ?>
