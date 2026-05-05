// Enhanced Ads Slider Functionality for Customers
document.addEventListener('DOMContentLoaded', function() {
    const ads = [
        {
            image: '../images/Adidas_Ultraboost.jpg',
            title: '🔥 FLASH SALE! 🔥',
            subtitle: 'Up to 50% OFF on Sports Shoes',
            offer: 'Limited Time Offer - Ends Soon!'
        },
        {
            image: '../images/4k_ultra_hd_smart_tv.jpeg',
            title: '📺 BIG SCREEN DEALS',
            subtitle: '4K Ultra HD Smart TVs',
            offer: 'Free Installation + 2 Year Warranty'
        },
        {
            image: '../images/nikeMec.jpg',
            title: '🏃‍♂️ FITNESS FRENZY',
            subtitle: 'Premium Athletic Wear',
            offer: 'Buy 2 Get 1 FREE - This Week Only!'
        },
        {
            image: '../images/Laptop Dell XPS 13.webp',
            title: '💻 TECH TUESDAY',
            subtitle: 'Premium Laptops & Accessories',
            offer: '0% Interest Financing Available'
        },
        {
            image: '../images/smart_home_speaker.jpeg',
            title: '🏠 SMART HOME UPGRADE',
            subtitle: 'Voice-Controlled Speakers',
            offer: 'Free Shipping + 30-Day Trial'
        }
    ];

    let currentIndex = 0;
    const adContainer = document.getElementById('ad-slider');

    function showAd() {
        const ad = ads[currentIndex];
        adContainer.innerHTML = `
            <div class="ad-slide">
                <img src="${ad.image}" alt="Promotional Ad" class="ad-image" />
                <div class="ad-overlay">
                    <div class="ad-content">
                        <h3 class="ad-title">${ad.title}</h3>
                        <p class="ad-subtitle">${ad.subtitle}</p>
                        <p class="ad-offer">${ad.offer}</p>
                        <button class="ad-cta">SHOP NOW</button>
                    </div>
                </div>
                <div class="special-offer">🔥 HOT DEAL</div>
                <div class="offer-countdown">⏰ 24H LEFT</div>
                <div class="ad-navigation">
                    ${ads.map((_, index) => 
                        `<div class="ad-dot ${index === currentIndex ? 'active' : ''}" data-index="${index}"></div>`
                    ).join('')}
                </div>
            </div>
        `;
        
        // Setup navigation dots
        const dots = document.querySelectorAll('.ad-dot');
        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                currentIndex = parseInt(dot.dataset.index);
                showAd();
            });
        });
        
        currentIndex = (currentIndex + 1) % ads.length;
    }

    // Touch/swipe support for mobile
    let touchStartX = 0;
    let touchEndX = 0;

    adContainer.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    }, false);

    adContainer.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);

    function handleSwipe() {
        const swipeThreshold = 50;
        if (touchEndX < touchStartX - swipeThreshold) {
            // Swipe left - next ad
            currentIndex = (currentIndex + 1) % ads.length;
            showAd();
        }
        if (touchEndX > touchStartX + swipeThreshold) {
            // Swipe right - previous ad
            currentIndex = (currentIndex - 1 + ads.length) % ads.length;
            showAd();
        }
    }

    showAd(); // Show the first ad immediately
    setInterval(showAd, 10000); // Change ad every 10 seconds
});
