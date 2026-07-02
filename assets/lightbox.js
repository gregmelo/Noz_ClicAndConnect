function initLightbox() {
    if (!document.getElementById('lightbox')) {
        var lb = document.createElement('div');
        lb.id = 'lightbox';
        lb.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;cursor:pointer;align-items:center;justify-content:center;';
        
        var img = document.createElement('img');
        img.id = 'lightbox-img';
        img.style.cssText = 'max-height:90vh;max-width:90vw;object-fit:contain;border-radius:8px;';
        
        var btn = document.createElement('button');
        btn.innerHTML = '✕';
        btn.style.cssText = 'position:absolute;top:20px;right:30px;background:none;border:none;color:white;font-size:40px;cursor:pointer;line-height:1;z-index:10001;';
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.stopImmediatePropagation();
            lb.style.display = 'none';
            document.body.style.overflow = '';
        }, true);
        
        var hint = document.createElement('p');
        hint.style.cssText = 'position:absolute;bottom:20px;color:rgba(255,255,255,0.6);font-size:14px;';
        hint.textContent = 'Cliquez n\'importe où pour fermer · Échap';
        
        lb.appendChild(img);
        lb.appendChild(btn);
        lb.appendChild(hint);
        
        lb.addEventListener('click', function(e) {
            if (e.target === lb || e.target === img) {
                lb.style.display = 'none';
                document.body.style.overflow = '';
            }
        }, true);
        
        document.body.appendChild(lb);
    }
    
    document.querySelectorAll('[data-lightbox-src]').forEach(function(el) {
        el.style.cursor = 'pointer';
        el.onclick = function(e) {
            e.stopPropagation();
            var lb = document.getElementById('lightbox');
            document.getElementById('lightbox-img').src = el.dataset.lightboxSrc;
            lb.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        };
    });
}

document.addEventListener('DOMContentLoaded', initLightbox);
document.addEventListener('turbo:load', initLightbox);
document.addEventListener('turbo:render', initLightbox);
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var lb = document.getElementById('lightbox');
        if (lb) { lb.style.display = 'none'; document.body.style.overflow = ''; }
    }
});
