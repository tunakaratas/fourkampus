// Access Everywhere tab switcher
(function () {
    const tabs = document.querySelectorAll('.access-tab');
    const previews = document.querySelectorAll('.device-preview');

    if (!tabs.length || !previews.length) {
        return;
    }

    function activatePlatform(platform) {
        tabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.platform === platform);
        });

        previews.forEach(preview => {
            preview.classList.toggle('active', preview.dataset.platform === platform);
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const platform = tab.dataset.platform || 'ios';
            activatePlatform(platform);
        });
    });
})();
// Counter Animation
function animateCounter(element, target, duration = 2000) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;

    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = formatNumber(target);
            clearInterval(timer);
        } else {
            element.textContent = formatNumber(Math.floor(current));
        }
    }, 16);
}

function formatNumber(num) {
    if (num >= 1000) {
        return (num / 1000).toFixed(0) + 'K+';
    }
    return num.toString();
}

// Intersection Observer for Counter Animation
const observerOptions = {
    threshold: 0.5,
    rootMargin: '0px'
};

const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const counter = entry.target;
            const target = parseInt(counter.getAttribute('data-count'));
            if (!counter.classList.contains('animated')) {
                counter.classList.add('animated');
                animateCounter(counter, target);
            }
        }
    });
}, observerOptions);

// Observe all stat numbers
document.querySelectorAll('.stat-number[data-count]').forEach(stat => {
    counterObserver.observe(stat);
});

// Mobile Navigation Toggle
const navToggle = document.querySelector('.nav-toggle');
const navMenu = document.querySelector('.header-nav-menu');
const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
const dropdownItems = document.querySelectorAll('.header-nav-item.has-dropdown');
const desktopMediaQuery = window.matchMedia('(min-width: 769px)');
const headerContainer = document.querySelector('.header-container');

function setKeyboardNavMode(isKeyboard) {
    if (!document.body) {
        return;
    }
    document.body.classList.toggle('keyboard-nav', isKeyboard);
}

document.addEventListener('keydown', (event) => {
    if (event.key === 'Tab') {
        setKeyboardNavMode(true);
    }
});

document.addEventListener('mousedown', () => {
    setKeyboardNavMode(false);
});

document.addEventListener('touchstart', () => {
    setKeyboardNavMode(false);
}, { passive: true });

function getHeaderOffset() {
    return (headerContainer?.offsetHeight || 0) + 16;
}

function openMobileMenu() {
    if (navMenu) {
        navMenu.classList.add('active');
    }
    if (mobileMenuOverlay) {
        mobileMenuOverlay.classList.add('active');
    }
    // Body scroll'u engelle
    document.body.style.overflow = 'hidden';
}

function closeMobileMenu() {
    if (navMenu) {
        navMenu.classList.remove('active');
    }
    if (mobileMenuOverlay) {
        mobileMenuOverlay.classList.remove('active');
    }
    // Body scroll'u geri aç
    document.body.style.overflow = '';
    resetDropdownState();
}

function scrollToTargetId(targetId, updateHash = true) {
    if (!targetId) {
        return false;
    }
    const target = document.getElementById(targetId);
    if (!target) {
        return false;
    }
    const elementTop = target.getBoundingClientRect().top + window.pageYOffset;
    const offsetTop = elementTop - getHeaderOffset();
    window.scrollTo({
        top: offsetTop,
        behavior: 'smooth'
    });
    if (updateHash) {
        history.replaceState(null, '', `#${targetId}`);
    }
    return true;
}

if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => {
        if (navMenu.classList.contains('active')) {
            closeMobileMenu();
        } else {
            openMobileMenu();
        }
    });
}

// Overlay'e tıklayınca menüyü kapat
if (mobileMenuOverlay) {
    mobileMenuOverlay.addEventListener('click', (e) => {
        e.stopPropagation();
        closeMobileMenu();
    });
}

// Menüye tıklayınca overlay'e event gitmemesi için
if (navMenu) {
    navMenu.addEventListener('click', (e) => {
        e.stopPropagation();
    });
}

function blurActiveNavElement() {
    if (!navMenu) {
        return;
    }
    const activeElement = document.activeElement;
    if (activeElement && navMenu.contains(activeElement)) {
        activeElement.blur();
    }
}

function resetDropdownState() {
    dropdownItems.forEach(item => {
        item.classList.remove('dropdown-open');
        item.classList.remove('dropdown-hover');
        const trigger = item.querySelector('.nav-link');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
    blurActiveNavElement();
}

window.addEventListener('resize', () => {
    if (!window.matchMedia('(max-width: 768px)').matches) {
        closeMobileMenu();
        resetDropdownState();
    }
});

const dropdownTriggers = document.querySelectorAll('.header-nav-item.has-dropdown > .nav-link');
dropdownTriggers.forEach(trigger => {
    trigger.addEventListener('click', (event) => {
        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        if (!isMobile) {
            return;
        }

        event.preventDefault();
        const parentItem = trigger.closest('.header-nav-item');
        const shouldOpen = !parentItem.classList.contains('dropdown-open');

        dropdownItems.forEach(item => {
            if (item !== parentItem) {
                item.classList.remove('dropdown-open');
                const btn = item.querySelector('.nav-link');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                }
            }
        });

        parentItem.classList.toggle('dropdown-open', shouldOpen);
        trigger.setAttribute('aria-expanded', shouldOpen.toString());
    });
});

dropdownItems.forEach(item => {
    let hoverTimeout;
    const dropdownPanel = item.querySelector('.header-dropdown');

    function openDropdown() {
        closeOtherDesktopDropdowns(item);
        item.classList.add('dropdown-hover');
        const trigger = item.querySelector('.nav-link');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
    }

    function closeDropdown() {
        item.classList.remove('dropdown-hover');
        const trigger = item.querySelector('.nav-link');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }
    }

    function scheduleClose() {
        clearTimeout(hoverTimeout);
        hoverTimeout = setTimeout(() => {
            closeDropdown();
        }, 300);
    }

    item.addEventListener('mouseenter', () => {
        if (!desktopMediaQuery.matches) {
            return;
        }
        clearTimeout(hoverTimeout);
        openDropdown();
    });

    item.addEventListener('mouseleave', (event) => {
        if (!desktopMediaQuery.matches) {
            return;
        }
        const relatedTarget = event.relatedTarget;
        if (relatedTarget) {
            const isAnotherDropdown = relatedTarget.closest('.header-nav-item.has-dropdown') !== null;
            const isDropdownPanel = dropdownPanel && dropdownPanel.contains(relatedTarget);
            if (isAnotherDropdown || isDropdownPanel) {
                return;
            }
        }
        scheduleClose();
    });

    if (dropdownPanel) {
        dropdownPanel.addEventListener('mouseenter', () => {
            if (!desktopMediaQuery.matches) {
                return;
            }
            clearTimeout(hoverTimeout);
            openDropdown();
        });

        dropdownPanel.addEventListener('mouseleave', (event) => {
            if (!desktopMediaQuery.matches) {
                return;
            }
            const relatedTarget = event.relatedTarget;
            if (relatedTarget) {
                const isAnotherDropdown = relatedTarget.closest('.header-nav-item.has-dropdown') !== null;
                const isParentItem = item.contains(relatedTarget);
                if (isAnotherDropdown || isParentItem) {
                    return;
                }
            }
            scheduleClose();
        });
    }
});

function closeOtherDesktopDropdowns(currentItem) {
    dropdownItems.forEach(otherItem => {
        if (otherItem === currentItem) {
            return;
        }
        otherItem.classList.remove('dropdown-hover');
        const otherTrigger = otherItem.querySelector('.nav-link');
        if (otherTrigger) {
            otherTrigger.setAttribute('aria-expanded', 'false');
        }
        const activeElement = document.activeElement;
        if (activeElement && otherItem.contains(activeElement)) {
            activeElement.blur();
        }
    });
}

document.addEventListener('click', (event) => {
    if (!desktopMediaQuery.matches || !navMenu) {
        return;
    }
    if (!navMenu.contains(event.target)) {
        resetDropdownState();
    }
});


// Close mobile menu when clicking on a link
const navLinks = document.querySelectorAll('.header-nav-menu a');
if (navMenu) {
    navLinks.forEach(link => {
        link.addEventListener('click', (event) => {
            const isTopLevel = link.classList.contains('nav-link');
            const isMobile = window.matchMedia('(max-width: 768px)').matches;
            if (isTopLevel && isMobile && event.defaultPrevented) {
                return;
            }
            closeMobileMenu();
        });
    });
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        if (e.defaultPrevented) {
            return;
        }
        const href = this.getAttribute('href');
        if (!href || href === '#' || href === '#!') {
            return;
        }
        const targetId = (this.getAttribute('data-anchor-target') || href.replace('#', '')).trim();
        if (!targetId) {
            return;
        }
        const didScroll = scrollToTargetId(targetId);
        if (didScroll) {
            e.preventDefault();
        }
    });
});

function handleInitialHash() {
    if (window.location.hash) {
        const id = window.location.hash.replace('#', '');
        if (id) {
            setTimeout(() => scrollToTargetId(id, false), 150);
        }
    }
}

window.addEventListener('load', handleInitialHash);
window.addEventListener('hashchange', () => {
    const id = window.location.hash.replace('#', '');
    if (id) {
        scrollToTargetId(id, false);
    }
});

// Contact Form Handler
const contactForm = document.getElementById('contactForm');
if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
        e.preventDefault();

        // Get form data
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;

        submitButton.textContent = 'Gönderiliyor...';
        submitButton.disabled = true;

        // API'ye gönder
        fetch('api/submit_contact_form.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    submitButton.innerHTML = '<i class="fas fa-check"></i> Gönderildi!';
                    submitButton.style.background = '#10b981';

                    // Show success message
                    const successMsg = document.createElement('div');
                    successMsg.style.cssText = 'background: #10b981; color: white; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem; text-align: center; font-weight: 600;';
                    successMsg.textContent = data.message || 'Mesajınız alındı! En kısa sürede size dönüş yapacağız.';
                    this.appendChild(successMsg);

                    // Reset form after 3 seconds
                    setTimeout(() => {
                        this.reset();
                        submitButton.textContent = originalText;
                        submitButton.disabled = false;
                        submitButton.style.background = '';
                        successMsg.remove();
                    }, 3000);
                } else {
                    // Show error message
                    const errorMsg = document.createElement('div');
                    errorMsg.style.cssText = 'background: #ef4444; color: white; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem; text-align: center; font-weight: 600;';
                    errorMsg.textContent = data.error || 'Bir hata oluştu. Lütfen tekrar deneyin.';
                    this.appendChild(errorMsg);

                    submitButton.textContent = originalText;
                    submitButton.disabled = false;

                    setTimeout(() => {
                        errorMsg.remove();
                    }, 5000);
                }
            })
            .catch(error => {
                console.error('Form submission error:', error);
                const errorMsg = document.createElement('div');
                errorMsg.style.cssText = 'background: #ef4444; color: white; padding: 1rem; border-radius: 0.5rem; margin-top: 1rem; text-align: center; font-weight: 600;';
                errorMsg.textContent = 'Bir hata oluştu. Lütfen tekrar deneyin.';
                this.appendChild(errorMsg);

                submitButton.textContent = originalText;
                submitButton.disabled = false;

                setTimeout(() => {
                    errorMsg.remove();
                }, 5000);
            });
    });
}

// Header: keep top layer static on scroll
(function () {
    const ensureHeaderVisible = () => {
        const headerTopLayer = document.querySelector('.header-top-layer');
        if (headerTopLayer) {
            headerTopLayer.classList.remove('header-top-hidden');
            headerTopLayer.style.transform = 'translateY(0)';
            headerTopLayer.style.opacity = '1';
        }
    };


    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureHeaderVisible);
    } else {
        ensureHeaderVisible();
    }

    // Load Partner Logos
    (function () {
        const partnersGrid = document.getElementById('partnersGrid');
        const partnersSection = document.querySelector('.partners-section');
        if (!partnersGrid) return;

        // API'den partner logolarını çekmeyi dene
        fetch('api/get_partner_logos.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.logos && data.logos.length > 0) {
                    displayPartners(data.logos);
                } else {
                    // API'den veri gelmezse bölümü gizle
                    if (partnersSection) {
                        partnersSection.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.log('Partner logos API not available');
                // API hatası durumunda bölümü gizle
                if (partnersSection) {
                    partnersSection.style.display = 'none';
                }
            });

        function displayPartners(partners) {
            partnersGrid.innerHTML = '';
            partners.forEach(partner => {
                // Logo path kontrolü
                const logoPath = partner.logo_path || partner.logo;
                if (!logoPath || logoPath.trim() === '') {
                    return; // Logo yoksa atla
                }

                const partnerItem = document.createElement('div');
                partnerItem.className = 'partner-logo-item';

                const img = document.createElement('img');
                img.src = logoPath;
                img.alt = partner.partner_name || partner.name || 'Partner';
                img.title = partner.partner_name || partner.name || 'Partner';
                img.onerror = function () {
                    // Logo yüklenemezse bu item'ı gizle
                    partnerItem.style.display = 'none';
                };

                partnerItem.appendChild(img);

                if (partner.partner_website || partner.website) {
                    partnerItem.style.cursor = 'pointer';
                    partnerItem.addEventListener('click', () => {
                        window.open(partner.partner_website || partner.website, '_blank');
                    });
                }
                partnersGrid.appendChild(partnerItem);
            });

            // Eğer hiç logo gösterilmediyse bölümü gizle
            if (partnersGrid.children.length === 0 && partnersSection) {
                partnersSection.style.display = 'none';
            }
        }
    })();
})();

// Intersection Observer for fade-in animations
const fadeObserverOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
            if (entry.target.classList.contains('feature-showcase-item')) {
                entry.target.classList.add('visible');
            }
            observer.unobserve(entry.target);
        }
    });
}, fadeObserverOptions);

// Observe all feature cards, screenshots, and pricing cards
document.querySelectorAll('.problem-card, .screenshot-item, .pricing-card, .include-item').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), transform 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
    observer.observe(el);
});

// Observe feature showcase items separately
document.querySelectorAll('.feature-showcase-item').forEach(el => {
    observer.observe(el);
});

// Iframe height adjustment for preview pages
function adjustIframeHeight(iframe) {
    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        const height = iframeDoc.body.scrollHeight;
        iframe.style.height = height + 'px';
    } catch (e) {
        // Cross-origin restriction, set default height
        iframe.style.height = '600px';
    }
}

// Mobile iframe adjustment - fit to phone screen
function adjustMobileIframe(iframe) {
    const phoneScreen = iframe.closest('.phone-screen');
    if (!phoneScreen) return;

    // Set iframe to fill phone screen
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    iframe.style.minHeight = '100%';

    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        const iframeBody = iframeDoc.body;
        const iframeHtml = iframeDoc.documentElement;

        // Set viewport meta if not present
        let viewportMeta = iframeDoc.querySelector('meta[name="viewport"]');
        if (!viewportMeta) {
            viewportMeta = iframeDoc.createElement('meta');
            viewportMeta.setAttribute('name', 'viewport');
            viewportMeta.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
            iframeDoc.head.appendChild(viewportMeta);
        }

        // Ensure body fills the iframe
        iframeBody.style.margin = '0';
        iframeBody.style.padding = '0';
        iframeHtml.style.margin = '0';
        iframeHtml.style.padding = '0';
        iframeHtml.style.height = '100%';
        iframeBody.style.height = '100%';
        iframeBody.style.minHeight = '100%';

    } catch (e) {
        // Cross-origin restriction - CSS will handle it
        console.log('Cross-origin iframe, using CSS fallback');
    }
}

// Adjust all iframe heights when loaded
document.querySelectorAll('.screenshot-iframe:not(.screenshot-iframe-mobile), .feature-visual-iframe').forEach(iframe => {
    iframe.onload = function () {
        adjustIframeHeight(this);
    };

    // Fallback: set initial height
    if (iframe.classList.contains('screenshot-iframe')) {
        iframe.style.height = '500px';
    } else if (iframe.classList.contains('feature-visual-iframe')) {
        iframe.style.height = '500px';
    }
});

// Adjust mobile iframe
document.querySelectorAll('.screenshot-iframe-mobile').forEach(iframe => {
    iframe.onload = function () {
        adjustMobileIframe(this);
    };

    // Also adjust on resize
    window.addEventListener('resize', () => {
        setTimeout(() => adjustMobileIframe(iframe), 100);
    });

    // Initial adjustment
    setTimeout(() => adjustMobileIframe(iframe), 500);
});

// Add active state to nav links based on scroll position
const scrollTargets = document.querySelectorAll('[data-scroll-target]');
const dropdownLinks = document.querySelectorAll('.dropdown-link');
const navGroups = {
    solutions: ['hero', 'cozumler', 'referanslar', 'call-to-action', 'iletisim'],
    features: ['ozellikler', 'feature-etkinlik', 'feature-uyelik', 'feature-bildirim', 'feature-anket', 'feature-yonetim', 'feature-platform', 'feature-sms', 'feature-portal'],
    screens: ['ekranlar', 'screen-dashboard', 'screen-events', 'screen-members', 'screen-notifications', 'screen-surveys', 'screen-portal'],
    pricing: ['fiyatlandirma', 'paket-icerik', 'call-to-action', 'iletisim']
};

function updateNavActiveState() {
    let current = '';
    const headerOffset = getHeaderOffset();

    scrollTargets.forEach(target => {
        const sectionTop = target.offsetTop;
        if (window.pageYOffset + headerOffset + 40 >= sectionTop) {
            current = target.getAttribute('id');
        }
    });

    dropdownLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (!href || !href.startsWith('#')) {
            return;
        }
        link.classList.toggle('active', href === `#${current}`);
    });

    dropdownItems.forEach(item => {
        const trigger = item.querySelector('.nav-link');
        if (!trigger) {
            return;
        }
        const groupKey = item.dataset.group;
        const groupSections = navGroups[groupKey] || [];
        trigger.classList.toggle('active', groupSections.includes(current));
    });
}

window.addEventListener('scroll', updateNavActiveState);
updateNavActiveState();

// Add active class styling
const style = document.createElement('style');
style.textContent = `
    .header-nav-menu a.active {
        color: var(--primary-color, #6366f1);
        font-weight: 600;
    }
`;
document.head.appendChild(style);

// Dark Mode Toggle
(function () {
    // Function to apply dark mode to all iframes
    function applyDarkModeToIframes(isDark) {
        const iframes = document.querySelectorAll('iframe');
        iframes.forEach(iframe => {
            try {
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                if (iframeDoc && iframeDoc.body) {
                    if (isDark) {
                        iframeDoc.body.classList.add('dark-mode');
                    } else {
                        iframeDoc.body.classList.remove('dark-mode');
                    }
                }
            } catch (e) {
                // Cross-origin iframe, skip
                console.log('Cannot access iframe (cross-origin):', e);
            }
        });
    }

    function initDarkMode() {
        const darkModeToggle = document.getElementById('darkModeToggle');
        const darkModeIcon = document.getElementById('darkModeIcon');

        if (!darkModeToggle || !darkModeIcon) {
            console.log('Dark mode toggle elements not found');
            return;
        }

        console.log('Dark mode toggle initialized');

        // Check localStorage for saved preference (system theme ignored)
        const savedTheme = localStorage.getItem('theme');

        // Initialize theme - only saved preference, default to light
        let isDark = savedTheme === 'dark';

        // Apply theme
        if (isDark) {
            document.body.classList.add('dark-mode');
            darkModeIcon.classList.remove('fa-moon');
            darkModeIcon.classList.add('fa-sun');
            console.log('Dark mode initialized: dark');
        } else {
            document.body.classList.remove('dark-mode');
            darkModeIcon.classList.remove('fa-sun');
            darkModeIcon.classList.add('fa-moon');
            console.log('Dark mode initialized: light');
        }

        // Apply to iframes after a short delay to ensure they're loaded
        setTimeout(() => {
            applyDarkModeToIframes(isDark);
        }, 500);

        // Toggle dark mode
        darkModeToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const isDark = document.body.classList.toggle('dark-mode');
            console.log('Dark mode toggled:', isDark ? 'dark' : 'light');

            if (isDark) {
                darkModeIcon.classList.remove('fa-moon');
                darkModeIcon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                darkModeIcon.classList.remove('fa-sun');
                darkModeIcon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }

            // Apply to iframes
            applyDarkModeToIframes(isDark);
        });

        // Monitor iframe loads and apply dark mode
        const iframes = document.querySelectorAll('iframe');
        iframes.forEach(iframe => {
            iframe.addEventListener('load', () => {
                const isDark = document.body.classList.contains('dark-mode');
                applyDarkModeToIframes(isDark);
            });
        });

        // Use MutationObserver to watch for new iframes
        const observer = new MutationObserver(() => {
            const isDark = document.body.classList.contains('dark-mode');
            applyDarkModeToIframes(isDark);
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDarkMode);
    } else {
        initDarkMode();
    }
})();

// Mobile app screen carousel
(function () {
    const carousel = document.getElementById('appCarousel');
    const track = document.getElementById('appCarouselTrack');
    const counterEl = document.getElementById('carouselCounter');
    const emptyEl = document.getElementById('carouselEmpty');
    const prevBtn = carousel ? carousel.querySelector('.carousel-control.prev') : null;
    const nextBtn = carousel ? carousel.querySelector('.carousel-control.next') : null;
    const viewport = carousel ? carousel.querySelector('.carousel-viewport') : null;
    const platformButtons = document.querySelectorAll('.app-platform-btn');

    if (!carousel || !track || !counterEl || !emptyEl || !viewport) {
        return;
    }

    const buildScreens = (basePath, count) => {
        return Array.from({ length: count }, (_, i) => `${basePath}/screenshot-${i + 1}.png`);
    };

    const screenSets = {
        ios: buildScreens('images/mobile-apps/ios', 8),
        android: buildScreens('images/mobile-apps/android', 8)
    };

    let currentPlatform = 'ios';
    let currentIndex = 0;
    let currentTranslate = 0;
    let isDragging = false;
    let dragStartX = 0;
    let dragStartTranslate = 0;
    let preventClick = false;

    function getSlideMetrics() {
        const slides = Array.from(track.children);
        if (!slides.length) {
            return null;
        }
        const slideWidth = slides[0].getBoundingClientRect().width;
        const trackStyle = getComputedStyle(track);
        const gapValue = parseFloat(trackStyle.columnGap || trackStyle.gap || trackStyle.rowGap);
        const gap = Number.isFinite(gapValue) ? gapValue : 0;
        return {
            slideWidth,
            gap,
            step: slideWidth + gap
        };
    }

    function updateCounter(total) {
        const current = total > 0 ? currentIndex + 1 : 0;
        counterEl.textContent = `${current} / ${total}`;
    }

    function setEmptyState(show, platform) {
        if (show) {
            emptyEl.textContent = platform === 'android'
                ? 'Android ekranlari yakinda.'
                : 'Bu platform icin ekran goruntusu bulunamadi.';
            emptyEl.classList.add('active');
        } else {
            emptyEl.classList.remove('active');
        }
    }

    function updateView() {
        const slides = Array.from(track.children);
        const total = slides.length;

        if (!total) {
            track.style.transform = 'translateX(0)';
            updateCounter(0);
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = true;
            return;
        }

        const metrics = getSlideMetrics();
        const step = metrics ? metrics.step : 0;
        const offset = currentIndex * step;
        currentTranslate = -offset;
        track.style.transform = `translateX(${currentTranslate}px)`;

        slides.forEach((slide, index) => {
            slide.classList.toggle('is-active', index === currentIndex);
            slide.classList.toggle('is-near', Math.abs(index - currentIndex) === 1);
        });

        updateCounter(total);
        const disableNav = total <= 1;
        if (prevBtn) prevBtn.disabled = disableNav;
        if (nextBtn) nextBtn.disabled = disableNav;
    }

    function normalizeSlides() {
        const slides = Array.from(track.children);
        if (!slides.length) {
            setEmptyState(true, currentPlatform);
            updateView();
            return;
        }
        setEmptyState(false, currentPlatform);
        if (currentIndex >= slides.length) {
            currentIndex = slides.length - 1;
        }
        updateView();
    }

    function renderSlides(platform) {
        track.innerHTML = '';
        currentIndex = 0;
        currentTranslate = 0;

        const images = screenSets[platform] || [];
        if (!images.length) {
            setEmptyState(true, platform);
            updateCounter(0);
            if (prevBtn) prevBtn.disabled = true;
            if (nextBtn) nextBtn.disabled = true;
            return;
        }

        setEmptyState(false, platform);

        images.forEach((src, idx) => {
            const slide = document.createElement('div');
            slide.className = 'carousel-slide';
            const frame = document.createElement('div');
            frame.className = 'carousel-frame';
            const img = document.createElement('img');
            img.src = src;
            img.alt = `${platform.toUpperCase()} ekran ${idx + 1}`;
            img.loading = 'lazy';
            img.draggable = false;
            img.addEventListener('dragstart', (event) => {
                event.preventDefault();
            });
            img.addEventListener('error', () => {
                slide.remove();
                normalizeSlides();
            });
            frame.appendChild(img);
            slide.appendChild(frame);
            slide.addEventListener('click', () => {
                if (preventClick || isDragging) {
                    return;
                }
                const slides = Array.from(track.children);
                currentIndex = slides.indexOf(slide);
                if (currentIndex < 0) currentIndex = 0;
                updateView();
            });
            track.appendChild(slide);
        });

        currentIndex = 0;
        normalizeSlides();
    }

    function setPlatform(platform) {
        currentPlatform = platform;
        platformButtons.forEach(button => {
            const isActive = button.dataset.platform === platform;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        renderSlides(platform);
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            const total = track.children.length;
            if (!total) return;
            currentIndex = (currentIndex - 1 + total) % total;
            updateView();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            const total = track.children.length;
            if (!total) return;
            currentIndex = (currentIndex + 1) % total;
            updateView();
        });
    }

    function startDrag(clientX) {
        if (!track.children.length) {
            return;
        }
        isDragging = true;
        preventClick = false;
        dragStartX = clientX;
        dragStartTranslate = currentTranslate;
        track.style.transition = 'none';
        viewport.classList.add('is-dragging');
    }

    function moveDrag(clientX) {
        if (!isDragging) {
            return;
        }
        const delta = clientX - dragStartX;
        if (Math.abs(delta) > 6) {
            preventClick = true;
        }
        track.style.transform = `translateX(${dragStartTranslate + delta}px)`;
    }

    function endDrag(clientX) {
        if (!isDragging) {
            return;
        }
        isDragging = false;
        viewport.classList.remove('is-dragging');
        track.style.transition = '';
        const metrics = getSlideMetrics();
        const total = track.children.length;
        if (!metrics || !total) {
            updateView();
            preventClick = false;
            return;
        }
        const delta = clientX - dragStartX;
        const newTranslate = dragStartTranslate + delta;
        const nextIndex = Math.round(-newTranslate / metrics.step);
        currentIndex = Math.min(Math.max(nextIndex, 0), total - 1);
        updateView();
        setTimeout(() => {
            preventClick = false;
        }, 0);
    }

    carousel.addEventListener('contextmenu', (event) => {
        if (event.target.closest('.carousel-frame')) {
            event.preventDefault();
        }
    });

    const supportsPointerEvents = 'PointerEvent' in window;

    if (supportsPointerEvents) {
        viewport.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) {
                return;
            }
            startDrag(event.clientX);
            if (typeof viewport.setPointerCapture === 'function') {
                viewport.setPointerCapture(event.pointerId);
            }
        });

        viewport.addEventListener('pointermove', (event) => {
            moveDrag(event.clientX);
        });

        const finishPointerDrag = (event) => {
            if (!isDragging) {
                return;
            }
            endDrag(event.clientX);
            if (typeof viewport.releasePointerCapture === 'function') {
                viewport.releasePointerCapture(event.pointerId);
            }
        };

        viewport.addEventListener('pointerup', finishPointerDrag);
        viewport.addEventListener('pointercancel', finishPointerDrag);
    } else {
        const handleMouseMove = (event) => {
            moveDrag(event.clientX);
        };

        const handleMouseUp = (event) => {
            endDrag(event.clientX);
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
        };

        viewport.addEventListener('mousedown', (event) => {
            if (event.button !== 0) {
                return;
            }
            startDrag(event.clientX);
            document.addEventListener('mousemove', handleMouseMove);
            document.addEventListener('mouseup', handleMouseUp);
        });

        viewport.addEventListener('touchstart', (event) => {
            if (event.touches.length !== 1) {
                return;
            }
            startDrag(event.touches[0].clientX);
        }, { passive: true });

        viewport.addEventListener('touchmove', (event) => {
            if (!isDragging || event.touches.length !== 1) {
                return;
            }
            moveDrag(event.touches[0].clientX);
            event.preventDefault();
        }, { passive: false });

        const handleTouchEnd = (event) => {
            const touch = event.changedTouches[0];
            const clientX = touch ? touch.clientX : dragStartX;
            endDrag(clientX);
        };

        viewport.addEventListener('touchend', handleTouchEnd);
        viewport.addEventListener('touchcancel', handleTouchEnd);
    }

    platformButtons.forEach(button => {
        button.addEventListener('click', () => {
            const platform = button.dataset.platform || 'ios';
            if (platform !== currentPlatform) {
                setPlatform(platform);
            }
        });
    });

    window.addEventListener('resize', () => {
        updateView();
    });

    setPlatform(currentPlatform);
})();

// Pricing duration toggle
(function () {
    const pricingSection = document.getElementById('fiyatlandirma');
    if (!pricingSection) {
        return;
    }

    const durationButtons = Array.from(pricingSection.querySelectorAll('.pricing-duration-btn'));
    const pricingCards = Array.from(pricingSection.querySelectorAll('.pricing-card'));

    if (!durationButtons.length || !pricingCards.length) {
        return;
    }

    function formatPrice(value) {
        return `₺${value.toLocaleString('tr-TR')}`;
    }

    function setDuration(months) {
        durationButtons.forEach(button => {
            const isActive = parseInt(button.dataset.months, 10) === months;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive.toString());
        });

        pricingCards.forEach(card => {
            const priceEl = card.querySelector('.price-amount');
            const periodEl = card.querySelector('.price-period');
            if (!priceEl) {
                return;
            }

            const isFree = card.dataset.free === 'true';
            const monthly = Number(card.dataset.monthly);

            if (isFree || !Number.isFinite(monthly) || monthly <= 0) {
                priceEl.textContent = card.dataset.freeLabel || 'ÜCRETSİZ';
                if (periodEl) {
                    periodEl.textContent = '';
                }
                return;
            }

            const total = monthly * months;
            priceEl.textContent = formatPrice(total);
            if (periodEl) {
                periodEl.textContent = months === 1 ? '/ay' : `/${months} ay`;
            }
        });
    }

    pricingSection.addEventListener('click', (event) => {
        const button = event.target.closest('.pricing-duration-btn');
        if (!button) {
            return;
        }
        const months = parseInt(button.dataset.months, 10) || 1;
        setDuration(months);
    });

    const initialButton = pricingSection.querySelector('.pricing-duration-btn.active') || durationButtons[0];
    const initialMonths = initialButton ? parseInt(initialButton.dataset.months, 10) || 1 : 1;
    setDuration(initialMonths);
})();
