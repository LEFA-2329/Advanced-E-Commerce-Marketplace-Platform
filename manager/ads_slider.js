// Ads Slider Functionality
document.addEventListener('DOMContentLoaded', function() {
    const ads = [
        '../images/Adidas_Ultraboost.jpg',
        '../images/4k_ultra_hd_smart_tv.jpeg',
        '../images/nikeMec.jpg',
        '../images/Laptop Dell XPS 13.webp',
        '../images/smart_home_speaker.jpeg'
    ];

    let currentIndex = 0;
    const adContainer = document.getElementById('ad-slider');

    function showAd() {
        adContainer.innerHTML = `<img src="${ads[currentIndex]}" alt="Ad" class="img-fluid" />`;
        currentIndex = (currentIndex + 1) % ads.length;
    }

    showAd(); // Show the first ad immediately
    setInterval(showAd, 10000); // Change ad every 10 seconds
});
