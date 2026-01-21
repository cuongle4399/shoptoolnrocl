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

<script src="/ShopToolNro/assets/js/main.js"></script>
<!-- Theme button and choices are handled centrally in /assets/js/main.js. Removing duplicate click handlers to avoid double-toggle issues. -->
<!-- main.js updates the button text and wires theme controls on DOMContentLoaded -->
</body>
</html>
