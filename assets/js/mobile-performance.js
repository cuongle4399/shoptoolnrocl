/* ===========================
   MOBILE PERFORMANCE JAVASCRIPT
   Tối ưu hóa JavaScript cho thiết bị di động
   =========================== */

// MOBILE DETECTION
const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

// PERFORMANCE OPTIMIZATION
if (isMobile) {
    // Disable hover effects on mobile
    document.body.classList.add('mobile-device');
    
    // Prevent 300ms click delay
    document.addEventListener('touchstart', function() {}, {passive: true});
}

// DEBOUNCE FUNCTION FOR PERFORMANCE - Tăng delay để giảm CPU
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// THROTTLE FUNCTION FOR SCROLL EVENTS - Tăng limit để giảm CPU
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// LAZY LOADING IMAGES
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                const src = img.getAttribute('data-src');
                if (src) {
                    img.src = src;
                    img.removeAttribute('data-src');
                    img.classList.add('loaded');
                }
                observer.unobserve(img);
            }
        });
    }, {
        rootMargin: '50px 0px',
        threshold: 0.01
    });
    
    // Observe all lazy images
    function observeLazyImages() {
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Run on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeLazyImages);
    } else {
        observeLazyImages();
    }
}

// SMOOTH SCROLL OPTIMIZATION
if (isMobile) {
    // Use native smooth scroll when possible
    document.documentElement.style.scrollBehavior = 'smooth';
}

// MOBILE TOUCH SWIPE DETECTION
class SwipeDetector {
    constructor(element) {
        this.element = element;
        this.startX = 0;
        this.startY = 0;
        this.threshold = 50;
        
        this.element.addEventListener('touchstart', this.handleTouchStart.bind(this), {passive: true});
        this.element.addEventListener('touchend', this.handleTouchEnd.bind(this), {passive: true});
    }
    
    handleTouchStart(e) {
        this.startX = e.touches[0].clientX;
        this.startY = e.touches[0].clientY;
    }
    
    handleTouchEnd(e) {
        const endX = e.changedTouches[0].clientX;
        const endY = e.changedTouches[0].clientY;
        
        const diffX = endX - this.startX;
        const diffY = endY - this.startY;
        
        if (Math.abs(diffX) > Math.abs(diffY)) {
            if (Math.abs(diffX) > this.threshold) {
                if (diffX > 0) {
                    this.onSwipeRight?.();
                } else {
                    this.onSwipeLeft?.();
                }
            }
        }
    }
}

// MOBILE SIDEBAR SWIPE
if (isMobile) {
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay') || createOverlay();
    
    function createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        overlay.addEventListener('click', () => {
            sidebar?.classList.remove('active');
            overlay.classList.remove('active');
        });
        
        return overlay;
    }
    
    if (sidebar) {
        const swipeDetector = new SwipeDetector(document.body);
        
        // Swipe gestures removed as per user request to avoid accidental sidebar opening
        /*
        swipeDetector.onSwipeRight = () => {
            if (window.scrollX < 10) { // Near left edge
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
            }
        };
        
        swipeDetector.onSwipeLeft = () => {
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        };
        */
    }
    
    // Sidebar toggle button
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        });
    }
}

// MOBILE SCROLL PERFORMANCE
let ticking = false;
const scrollCallbacks = [];

function addScrollCallback(callback) {
    scrollCallbacks.push(callback);
}

window.addEventListener('scroll', () => {
    if (!ticking) {
        window.requestAnimationFrame(() => {
            scrollCallbacks.forEach(callback => callback());
            ticking = false;
        });
        ticking = true;
    }
}, {passive: true});

// MOBILE HEADER HIDE ON SCROLL - Tắt trên mobile để giảm lag
if (isMobile) {
    const header = document.querySelector('.topbar');
    let lastScrollTop = 0;
    let scrollThreshold = 80; // Tăng threshold
    
    if (header) {
        // Throttle nhiều hơn trên mobile: 150ms
        addScrollCallback(throttle(() => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (Math.abs(scrollTop - lastScrollTop) < scrollThreshold) {
                return;
            }
            
            // Tắt hide header trên mobile để tránh lag
            // if (scrollTop > lastScrollTop && scrollTop > 150) {
            //     header.style.transform = 'translateY(-100%)';
            // } else {
            //     header.style.transform = 'translateY(0)';
            // }
            
            lastScrollTop = scrollTop;
        }, 150)); // Tăng từ 100ms lên 150ms
    }
}

// MOBILE FORM OPTIMIZATION
if (isMobile) {
    // Prevent zoom on input focus
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (input.type !== 'submit' && input.type !== 'button') {
            const fontSize = window.getComputedStyle(input).fontSize;
            if (parseFloat(fontSize) < 16) {
                input.style.fontSize = '16px';
            }
        }
    });
}

// MOBILE TOUCH FEEDBACK
if (isTouch) {
    document.addEventListener('touchstart', function(e) {
        const target = e.target.closest('.btn, .card, a, button');
        if (target) {
            target.classList.add('touch-active');
        }
    }, {passive: true});
    
    document.addEventListener('touchend', function(e) {
        const target = e.target.closest('.btn, .card, a, button');
        if (target) {
            setTimeout(() => {
                target.classList.remove('touch-active');
            }, 200);
        }
    }, {passive: true});
}

// MOBILE PULL TO REFRESH (Disabled - Causes lag)
// Tắt pull to refresh vì gây lag khi scroll
if (false && isMobile && 'serviceWorker' in navigator) {
    let startY = 0;
    let isPulling = false;
    
    document.addEventListener('touchstart', (e) => {
        if (window.scrollY === 0) {
            startY = e.touches[0].pageY;
            isPulling = true;
        }
    }, {passive: true});
    
    document.addEventListener('touchmove', (e) => {
        if (isPulling) {
            const currentY = e.touches[0].pageY;
            const pullDistance = currentY - startY;
            
            if (pullDistance > 80) {
                // Show refresh indicator
                document.body.classList.add('pull-to-refresh');
            }
        }
    }, {passive: true});
    
    document.addEventListener('touchend', () => {
        if (document.body.classList.contains('pull-to-refresh')) {
            document.body.classList.remove('pull-to-refresh');
            // Trigger refresh
            window.location.reload();
        }
        isPulling = false;
    }, {passive: true});
}

// MOBILE IMAGE LAZY LOADING WITH BLUR EFFECT
function lazyLoadImages() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('fade-in');
                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px'
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for older browsers
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
}

// MOBILE PERFORMANCE MONITORING
if (isMobile && 'performance' in window) {
    window.addEventListener('load', () => {
        setTimeout(() => {
            const perfData = performance.timing;
            const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
            const connectTime = perfData.responseEnd - perfData.requestStart;
            const renderTime = perfData.domComplete - perfData.domLoading;
            
            console.log('📱 Mobile Performance Metrics:');
            console.log(`⏱️ Page Load Time: ${pageLoadTime}ms`);
            console.log(`🔌 Connect Time: ${connectTime}ms`);
            console.log(`🎨 Render Time: ${renderTime}ms`);
            
            // Send to analytics if needed
        }, 0);
    });
}

// MOBILE NETWORK OPTIMIZATION
if ('connection' in navigator) {
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    
    if (connection) {
        // Adjust quality based on connection
        if (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g') {
            document.body.classList.add('slow-connection');
            console.log('🐌 Slow connection detected - reducing quality');
        }
        
        connection.addEventListener('change', () => {
            console.log(`📶 Connection changed: ${connection.effectiveType}`);
        });
    }
}

// MOBILE VIRTUAL KEYBOARD HANDLING
if (isMobile) {
    let originalHeight = window.innerHeight;
    
    window.addEventListener('resize', debounce(() => {
        const currentHeight = window.innerHeight;
        
        if (currentHeight < originalHeight) {
            // Keyboard opened
            document.body.classList.add('keyboard-open');
        } else {
            // Keyboard closed
            document.body.classList.remove('keyboard-open');
            originalHeight = currentHeight;
        }
    }, 100));
}

// MOBILE CACHE OPTIMIZATION
if ('caches' in window) {
    const CACHE_NAME = 'mobile-cache-v1';
    const urlsToCache = [
        '/assets/css/mobile-performance.css',
        '/assets/js/mobile-performance.js'
    ];
    
    // Cache important resources
    caches.open(CACHE_NAME).then(cache => {
        return cache.addAll(urlsToCache);
    });
}

// EXPORT FOR USE IN OTHER MODULES
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        isMobile,
        isTouch,
        debounce,
        throttle,
        SwipeDetector,
        lazyLoadImages
    };
}

// MOBILE READY EVENT
document.addEventListener('DOMContentLoaded', () => {
    console.log('📱 Mobile optimizations loaded');
    
    if (isMobile) {
        console.log('✅ Mobile device detected');
        console.log('🚀 Performance mode: ACTIVE');
    }
    
    // Initialize lazy loading
    lazyLoadImages();
});

// MOBILE ORIENTATION CHANGE
if (isMobile) {
    window.addEventListener('orientationchange', debounce(() => {
        console.log('📱 Orientation changed');
        // Reload layouts or adjust if needed
        window.dispatchEvent(new Event('resize'));
    }, 200));
}

// CSS TOUCH ACTIVE STATE
const style = document.createElement('style');
style.textContent = `
    .touch-active {
        opacity: 0.7 !important;
        transform: scale(0.98) !important;
        transition: all 0.1s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    
    .mobile-device *:hover {
        /* Disable hover effects on mobile */
    }
    
    .keyboard-open {
        /* Adjust layout when keyboard is open */
    }
    
    .pull-to-refresh::before {
        content: '↓ Kéo để làm mới';
        position: fixed;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        padding: 8px 16px;
        background: var(--accent);
        color: white;
        border-radius: 0 0 8px 8px;
        font-size: 12px;
        z-index: 9999;
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from { transform: translate(-50%, -100%); }
        to { transform: translate(-50%, 0); }
    }
`;
document.head.appendChild(style);
