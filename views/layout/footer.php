</main>
<footer class="site-footer">
    <div class="container flex-between">
        <p>&copy; 2025 ShopToolNro — Code by Cuong Le</p>
    </div>
</footer>

<!-- Product Modal (used by product quick view) -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productTitle">Sản phẩm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <img id="productDemoImage" src="" alt="Demo" class="img-full" />
                        <div id="productTutorial" style="margin-top:12px;"></div>
                    </div>
                    <div class="col-md-6">
                        <h4 id="productPrice"></h4>
                        <p id="productCategory" class="text-muted"></p>
                        <p id="productDescription"></p>
                        <div id="productDurationContainer"></div>
                        <div id="productButtons" style="margin-top:12px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/ShopToolNro/assets/js/api.js"></script>
<script src="/ShopToolNro/assets/js/main.js"></script>
<script src="/ShopToolNro/assets/js/mobile-performance.js"></script>
<script src="/ShopToolNro/assets/js/mobile-responsive.js"></script>

<!-- Service Worker Registration (Temporarily Disabled for Testing) -->
<script>
// Unregister existing service workers to prevent caching issues
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for(let registration of registrations) {
            registration.unregister();
            console.log('🗑️ Service Worker unregistered');
        }
    });
}

// Uncomment to enable Service Worker after testing
/*
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/ShopToolNro/sw.js')
            .then(registration => {
                console.log('✅ Service Worker registered:', registration.scope);
            })
            .catch(error => {
                console.log('❌ Service Worker registration failed:', error);
            });
    });
}

// Install PWA prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Show install button (optional)
    const installBtn = document.getElementById('installBtn');
    if (installBtn) {
        installBtn.style.display = 'block';
        installBtn.addEventListener('click', () => {
            installBtn.style.display = 'none';
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('✅ PWA installed');
                }
                deferredPrompt = null;
            });
        });
    }
});
*/
</script>

<!-- Theme button and choices are handled centrally in /assets/js/main.js. Removing duplicate click handlers to avoid double-toggle issues. -->
<!-- main.js updates the button text and wires theme controls on DOMContentLoaded -->
</body>
</html>
