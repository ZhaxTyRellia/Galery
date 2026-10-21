/**
 * Gallery Hub - Interactive Media Player & Controls
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
    const itemPosition = (currentPlayingIndex + 1) + ' / ' + currentVisibleCards.length;

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
                    Browser Anda tidak mendukung pemutar video ini.
                </video>
            </div>
        `;
    }

    container.innerHTML = `
        ${mediaHtml}
        <div class="player-controls">
            <div class="player-info">
                <span class="player-title" title="${escapeHtml(title)}">${escapeHtml(title)}</span>
                <span class="player-meta">${folder ? '📁 ' + escapeHtml(folder) + ' &bull; ' : ''}${itemPosition}</span>
            </div>
            <div class="player-buttons">
                <button class="player-btn" onclick="playPrev()" title="Previous (Left Arrow)">⏮ Prev</button>
                ${type === 'video' ? `<button id="loopBtn" class="player-btn ${isLoopSingle ? 'active' : ''}" onclick="toggleLoop()" title="Loop single video">🔁 Loop</button>` : ''}
                <a href="${mediaUrl}" target="_blank" class="player-btn" title="Open full media in new tab">↗ Full</a>
                <button class="player-btn" onclick="playNext()" title="Next (Right Arrow)">Next ⏭</button>
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
        // Fallback for older browsers
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
    tempVideo.src = 'media_server.php?file=' + videoUrl + '#t=1.5';
    tempVideo.muted = true;
    tempVideo.playsInline = true;

    tempVideo.onloadeddata = function() {
        const ctx = canvas.getContext('2d');
        canvas.width = tempVideo.videoWidth || 320;
        canvas.height = tempVideo.videoHeight || 180;
        ctx.drawImage(tempVideo, 0, 0, canvas.width, canvas.height);
        canvas.dataset.rendered = 'true';
        // Clean up temporary video resources
        tempVideo.src = '';
        tempVideo.load();
    };

    tempVideo.onerror = function() {
        // Leave placeholder canvas/icon
        canvas.dataset.rendered = 'failed';
    };
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

// Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    filterAndSortMedia();
    setupVideoThumbnailObserver();
});
