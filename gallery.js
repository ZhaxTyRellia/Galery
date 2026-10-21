/**
 * Kawaii Gallery Hub - Interactive Media Player & Cute Animations 🌸✨
 */

let currentVisibleCards = [];
let currentPlayingIndex = -1;
let isLoopSingle = false;

// Mobile Navigation Toggle
function toggleTopMenu() {
    const nav = document.getElementById('topNav');
    const controls = document.getElementById('topControls');
    if (nav) nav.classList.toggle('open');
    if (controls) controls.classList.toggle('open');
}

// Search and Sort Filtering
function filterAndSortMedia() {
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;

    const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
    const sortOption = sortSelect ? sortSelect.value : 'date-desc';
    const cards = Array.from(grid.querySelectorAll('.media-card'));

    let visibleCount = 0;

    cards.forEach(card => {
        const title = (card.getAttribute('data-title') || '').toLowerCase();
        const folder = (card.getAttribute('data-folder') || '').toLowerCase();

        const matches = query === '' || title.includes(query) || folder.includes(query);

        if (matches) {
            card.style.display = 'flex';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Sort cards
    cards.sort((a, b) => {
        const nameA = a.getAttribute('data-title') || '';
        const nameB = b.getAttribute('data-title') || '';
        const timeA = parseInt(a.getAttribute('data-mtime') || '0', 10);
        const timeB = parseInt(b.getAttribute('data-mtime') || '0', 10);
        const sizeA = parseInt(a.getAttribute('data-size') || '0', 10);
        const sizeB = parseInt(b.getAttribute('data-size') || '0', 10);

        switch (sortOption) {
            case 'date-desc':
                return timeB - timeA;
            case 'date-asc':
                return timeA - timeB;
            case 'name-asc':
                return nameA.localeCompare(nameB);
            case 'name-desc':
                return nameB.localeCompare(nameA);
            case 'size-desc':
                return sizeB - sizeA;
            case 'size-asc':
                return sizeA - sizeB;
            default:
                return timeB - timeA;
        }
    });

    // Re-append sorted cards
    cards.forEach(card => grid.appendChild(card));

    // Update item counter badge
    const countBadge = document.getElementById('itemCount');
    if (countBadge) {
        countBadge.textContent = visibleCount;
    }

    updateVisibleCards();
}

function updateVisibleCards() {
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;
    currentVisibleCards = Array.from(grid.querySelectorAll('.media-card')).filter(card => card.style.display !== 'none');
}

// Open player by original card index
function openPlayerByIndex(originalIndex) {
    updateVisibleCards();
    const cardIndex = currentVisibleCards.findIndex(card => card.getAttribute('data-index') == originalIndex);
    if (cardIndex !== -1) {
        playMediaAtVisibleIndex(cardIndex);
    }
}

// Play media modal at index
function playMediaAtVisibleIndex(index) {
    if (index < 0 || index >= currentVisibleCards.length) return;
    currentPlayingIndex = index;

    const card = currentVisibleCards[index];
    const relPath = card.getAttribute('data-relpath');
    const type = card.getAttribute('data-type');
    const title = card.getAttribute('data-name') || 'Media';
    const folder = card.getAttribute('data-folder') || '';

    const modal = document.getElementById('mediaModal');
    const container = document.getElementById('modalContainer');
    if (!modal || !container) return;

    const mediaUrl = 'media_server.php?file=' + relPath;
    const itemPosition = (currentPlayingIndex + 1) + ' dari ' + currentVisibleCards.length;

    let mediaHtml = '';
    if (type === 'image') {
        mediaHtml = `
            <div class="modal-media-wrapper">
                <img src="${mediaUrl}" class="modal-content modal-img" alt="${escapeHtml(title)}">
            </div>
        `;
    } else if (type === 'video') {
        mediaHtml = `
            <div class="modal-media-wrapper">
                <video id="activeVideo" controls autoplay playsinline class="modal-content modal-video">
                    <source src="${mediaUrl}">
                    Browser Anda belum mendukung pemutaran video ini yaa~ 🥺
                </video>
            </div>
        `;
    }

    container.innerHTML = `
        ${mediaHtml}
        <div class="player-controls">
            <div class="player-info">
                <span class="player-title" title="${escapeHtml(title)}">🌸 ${escapeHtml(title)}</span>
                <span class="player-meta">${folder ? '📁 ' + escapeHtml(folder) + ' &bull; ' : ''}🎀 ${itemPosition}</span>
            </div>
            <div class="player-buttons">
                <button class="player-btn" onclick="playPrev()" title="Sebelumnya (Panah Kiri)">⏮ Prev</button>
                ${type === 'video' ? `<button id="loopBtn" class="player-btn ${isLoopSingle ? 'active' : ''}" onclick="toggleLoop()" title="Ulang video ini">🔁 Loop ${isLoopSingle ? 'Aktif 💖' : 'Manis'}</button>` : ''}
                <a href="${mediaUrl}" target="_blank" class="player-btn" title="Buka penuh di tab baru">↗ Buka Penuh ✨</a>
                <button class="player-btn" onclick="playNext()" title="Selanjutnya (Panah Kanan)">Next ⏭</button>
            </div>
        </div>
    `;

    if (type === 'video') {
        const videoElement = document.getElementById('activeVideo');
        if (videoElement) {
            videoElement.onended = function() {
                if (isLoopSingle) {
                    videoElement.currentTime = 0;
                    videoElement.play();
                } else {
                    playNext();
                }
            };
        }
    }

    modal.style.display = 'flex';
}

function toggleLoop() {
    isLoopSingle = !isLoopSingle;
    const btn = document.getElementById('loopBtn');
    if (btn) {
        btn.classList.toggle('active', isLoopSingle);
        btn.innerHTML = `🔁 Loop ${isLoopSingle ? 'Aktif 💖' : 'Manis'}`;
    }
}

function playNext() {
    if (currentVisibleCards.length === 0) return;
    const nextIndex = (currentPlayingIndex + 1) % currentVisibleCards.length;
    playMediaAtVisibleIndex(nextIndex);
}

function playPrev() {
    if (currentVisibleCards.length === 0) return;
    const prevIndex = (currentPlayingIndex - 1 + currentVisibleCards.length) % currentVisibleCards.length;
    playMediaAtVisibleIndex(prevIndex);
}

function closePlayer() {
    const modal = document.getElementById('mediaModal');
    const container = document.getElementById('modalContainer');
    if (!modal) return;

    // Pause any playing video to stop sound immediately
    const video = modal.querySelector('video');
    if (video) {
        video.pause();
        video.src = '';
    }

    modal.style.display = 'none';
    if (container) container.innerHTML = '';
    currentPlayingIndex = -1;
}

// Efficient Lazy Video Thumbnail Generator using IntersectionObserver
function setupVideoThumbnailObserver() {
    const videoCards = document.querySelectorAll('.media-card[data-type="video"]');
    if (!('IntersectionObserver' in window)) {
        videoCards.forEach(card => generateCardThumbnail(card));
        return;
    }

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                generateCardThumbnail(entry.target);
                obs.unobserve(entry.target);
            }
        });
    }, { rootMargin: '200px 0px' });

    videoCards.forEach(card => observer.observe(card));
}

function generateCardThumbnail(card) {
    const canvas = card.querySelector('.thumb-canvas');
    const videoUrl = card.getAttribute('data-relpath');
    if (!canvas || !videoUrl || canvas.dataset.rendered === 'true') return;

    const tempVideo = document.createElement('video');
    tempVideo.preload = 'metadata';
    tempVideo.src = 'media_server.php?file=' + videoUrl;
    tempVideo.muted = true;
    tempVideo.playsInline = true;

    let hasDrawn = false;
    const draw = () => {
        if (hasDrawn) return;
        hasDrawn = true;
        try {
            const ctx = canvas.getContext('2d');
            canvas.width = tempVideo.videoWidth || 320;
            canvas.height = tempVideo.videoHeight || 180;
            ctx.drawImage(tempVideo, 0, 0, canvas.width, canvas.height);
            canvas.dataset.rendered = 'true';
        } catch (e) {
            canvas.dataset.rendered = 'failed';
        }
        tempVideo.src = '';
        tempVideo.remove();
    };

    tempVideo.onloadeddata = function() {
        if (tempVideo.duration && tempVideo.duration > 1) {
            tempVideo.currentTime = Math.min(1.5, tempVideo.duration / 2);
        } else {
            draw();
        }
    };

    tempVideo.onseeked = function() {
        draw();
    };

    tempVideo.onerror = function() {
        canvas.dataset.rendered = 'failed';
        tempVideo.remove();
    };

    setTimeout(() => {
        if (!hasDrawn) {
            tempVideo.src = '';
            tempVideo.remove();
        }
    }, 8000);
}

// Utility: HTML escaping
function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('mediaModal');
    const isModalOpen = modal && modal.style.display === 'flex';

    if (e.key === 'Escape') {
        closePlayer();
    } else if (isModalOpen) {
        if (e.key === 'ArrowRight') {
            playNext();
        } else if (e.key === 'ArrowLeft') {
            playPrev();
        } else if (e.key === ' ' || e.code === 'Space') {
            const video = document.getElementById('activeVideo');
            if (video && e.target.tagName !== 'INPUT') {
                e.preventDefault();
                if (video.paused) {
                    video.play();
                } else {
                    video.pause();
                }
            }
        }
    }
});

// Close modal when clicking on backdrop
document.addEventListener('click', function(e) {
    const modal = document.getElementById('mediaModal');
    if (e.target === modal) {
        closePlayer();
    }
});

/* ================= KAWAII FLOATING CANVAS ANIMATION ================= */
function initKawaiiCanvas() {
    const canvas = document.getElementById('kawaiiCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let width = canvas.width = window.innerWidth;
    let height = canvas.height = window.innerHeight;

    window.addEventListener('resize', () => {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    });

    const particles = [];
    const particleCount = 28;
    const emojis = ['🌸', '✨', '💖', '🫧', '🎀', '🍬'];

    for (let i = 0; i < particleCount; i++) {
        particles.push({
            x: Math.random() * width,
            y: Math.random() * height,
            size: 14 + Math.random() * 16,
            speedY: 0.3 + Math.random() * 0.8,
            speedX: (Math.random() - 0.5) * 0.6,
            rotation: Math.random() * 360,
            rotationSpeed: (Math.random() - 0.5) * 1.5,
            opacity: 0.25 + Math.random() * 0.45,
            emoji: emojis[Math.floor(Math.random() * emojis.length)]
        });
    }

    function animate() {
        ctx.clearRect(0, 0, width, height);

        particles.forEach(p => {
            p.y -= p.speedY;
            p.x += Math.sin(p.y * 0.01) * 0.5 + p.speedX;
            p.rotation += p.rotationSpeed;

            // Reset when reaching top
            if (p.y < -40) {
                p.y = height + 40;
                p.x = Math.random() * width;
            }
            if (p.x < -40) p.x = width + 40;
            if (p.x > width + 40) p.x = -40;

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate((p.rotation * Math.PI) / 180);
            ctx.globalAlpha = p.opacity;
            ctx.font = `${p.size}px sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(p.emoji, 0, 0);
            ctx.restore();
        });

        requestAnimationFrame(animate);
    }

    animate();
}

/* ================= INTERACTIVE CLICK SPARKLE BURST ================= */
function initClickSparkles() {
    const sparkles = ['✨', '💖', '🌸', '🍬', '⭐'];

    document.addEventListener('click', function(e) {
        // Do not trigger on button/links directly if it impairs clicking
        const count = 4;
        for (let i = 0; i < count; i++) {
            const span = document.createElement('span');
            span.className = 'sparkle-burst';
            span.textContent = sparkles[Math.floor(Math.random() * sparkles.length)];

            span.style.left = e.clientX + 'px';
            span.style.top = e.clientY + 'px';

            const angle = (Math.PI * 2 * i) / count + (Math.random() - 0.5);
            const dist = 30 + Math.random() * 35;
            const dx = Math.cos(angle) * dist;
            const dy = Math.sin(angle) * dist;

            span.style.setProperty('--dx', dx + 'px');
            span.style.setProperty('--dy', dy + 'px');

            document.body.appendChild(span);
            setTimeout(() => span.remove(), 900);
        }
    });
}

// Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    filterAndSortMedia();
    setupVideoThumbnailObserver();
    initKawaiiCanvas();
    initClickSparkles();
});
