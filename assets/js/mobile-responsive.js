/* ===========================
   MOBILE TABLE WRAPPER
   Tự động wrap tables để thêm horizontal scroll
   =========================== */

document.addEventListener('DOMContentLoaded', function() {
    // Chỉ chạy trên mobile
    if (window.innerWidth <= 768) {
        wrapTablesForMobile();
    }
    
    // Re-check khi resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth <= 768) {
                wrapTablesForMobile();
            }
        }, 250);
    });
    
    // ===== SIDEBAR FIX - Chỉ dùng button toggle, BỎ swipe =====
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('mainSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    
    if (sidebarToggle && sidebar) {
        // Click button để toggle
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
        
        // Click backdrop để đóng
        if (backdrop) {
            backdrop.addEventListener('click', function(e) {
                e.preventDefault();
                closeSidebar();
            });
        }
        
        // Click link trong sidebar thì đóng sidebar
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeSidebar();
            });
        });
    }
    
    function toggleSidebar() {
        const isOpen = sidebar.classList.contains('open');
        if (isOpen) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
    
    function openSidebar() {
        sidebar.classList.add('open');
        document.body.classList.add('sidebar-open');
        if (backdrop) backdrop.classList.add('visible');
    }
    
    function closeSidebar() {
        sidebar.classList.remove('open');
        document.body.classList.remove('sidebar-open');
        if (backdrop) backdrop.classList.remove('visible');
    }
    
    // ESC key để đóng
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});

function wrapTablesForMobile() {
    // Tìm tất cả tables chưa được wrap
    const tables = document.querySelectorAll('table:not(.table-wrapper table)');
    
    tables.forEach(function(table) {
        // Kiểm tra xem table đã được wrap chưa
        if (!table.parentElement.classList.contains('table-wrapper')) {
            // Tạo wrapper div
            const wrapper = document.createElement('div');
            wrapper.className = 'table-wrapper';
            
            // Wrap table
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
            
            console.log('Wrapped table for mobile scrolling');
        }
    });
}

/* ===========================
   PREVENT HORIZONTAL SCROLL
   Ngăn scroll ngang không mong muốn
   =========================== */

// Ngăn body scroll ngang
document.body.style.overflowX = 'hidden';
document.documentElement.style.overflowX = 'hidden';

/* ===========================
   MOBILE TOUCH IMPROVEMENTS
   =========================== */

if (window.innerWidth <= 768) {
    // Thêm touch feedback cho buttons
    document.addEventListener('touchstart', function(e) {
        if (e.target.matches('button, .btn, a.btn')) {
            e.target.classList.add('touching');
        }
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        if (e.target.matches('button, .btn, a.btn')) {
            setTimeout(function() {
                e.target.classList.remove('touching');
            }, 150);
        }
    }, { passive: true });
}

/* ===========================
   AUTO-SCALE LARGE CONTENT
   Tự động scale nội dung quá rộng
   =========================== */

function checkAndScaleWideContent() {
    if (window.innerWidth <= 768) {
        const viewportWidth = window.innerWidth;
        
        // Kiểm tra các elements có thể vượt quá viewport
        const wideElements = document.querySelectorAll('img, iframe, video, .card, .modal-content');
        
        wideElements.forEach(function(el) {
            const elWidth = el.offsetWidth;
            
            if (elWidth > viewportWidth) {
                const scale = (viewportWidth - 40) / elWidth; // 40px padding
                el.style.maxWidth = '100%';
                el.style.height = 'auto';
                
                console.log('Scaled wide element:', el);
            }
        });
    }
}

// Chạy khi load
window.addEventListener('load', checkAndScaleWideContent);

// Chạy khi resize
window.addEventListener('resize', function() {
    clearTimeout(this.resizeTimer);
    this.resizeTimer = setTimeout(checkAndScaleWideContent, 250);
});

/* ===========================
   FORM INPUT FIX
   Fix iOS zoom khi focus input
   =========================== */

if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(function(input) {
        // Đảm bảo font-size >= 16px để tránh zoom
        const computedSize = window.getComputedStyle(input).fontSize;
        const size = parseFloat(computedSize);
        
        if (size < 16) {
            input.style.fontSize = '16px';
        }
    });
}

/* ===========================
   VIEWPORT HEIGHT FIX
   Fix cho mobile browsers (address bar)
   =========================== */

function setMobileVH() {
    const vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
}

if (window.innerWidth <= 768) {
    setMobileVH();
    window.addEventListener('resize', setMobileVH);
}

/* ===========================
   DEBUG HELPER
   Log elements vượt quá viewport
   =========================== */

if (window.location.search.includes('debug=mobile')) {
    function findOverflowElements() {
        const all = document.querySelectorAll('*');
        const overflowing = [];
        
        all.forEach(function(el) {
            if (el.scrollWidth > document.documentElement.clientWidth) {
                overflowing.push({
                    element: el,
                    width: el.scrollWidth,
                    tag: el.tagName,
                    classes: el.className
                });
            }
        });
        
        if (overflowing.length > 0) {
            console.group('Elements causing horizontal scroll:');
            overflowing.forEach(function(item) {
                console.log(`${item.tag}.${item.classes} - Width: ${item.width}px`, item.element);
            });
            console.groupEnd();
        }
    }
    
    window.addEventListener('load', findOverflowElements);
}
