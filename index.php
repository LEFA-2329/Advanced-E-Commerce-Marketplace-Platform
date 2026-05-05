<?php
session_start();
require_once 'db_connection.php';

// Fetch some promotional products for the ad slider
$promo_stmt = $pdo->prepare("
    SELECT p.*, pr.discount_percent, pr.promotion_type
    FROM products p 
    LEFT JOIN promotions pr ON p.product_id = pr.product_id 
    WHERE pr.is_active = true 
    AND p.stock_quantity > 0 
    AND pr.start_date <= CURRENT_DATE 
    AND (pr.end_date IS NULL OR pr.end_date >= CURRENT_DATE)
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$promo_stmt->execute();
$promotional_products = $promo_stmt->fetchAll();

// Count total products and stores for statistics
$product_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE stock_quantity > 0");
$product_count_stmt->execute();
$total_products = $product_count_stmt->fetchColumn();

$store_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE is_active = true");
$store_count_stmt->execute();
$total_stores = $store_count_stmt->fetchColumn();

// Get some featured categories
$categories_stmt = $pdo->prepare("SELECT category, COUNT(*) as product_count FROM products GROUP BY category ORDER BY product_count DESC LIMIT 6");
$categories_stmt->execute();
$featured_categories = $categories_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StoreHub - Your Ultimate Online Marketplace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="modern-styles.css">
    <style>
        /* Professional Color Scheme */
        :root {
            --primary-dark: #1a365d;
            --primary: #2d3748;
            --primary-light: #4a5568;
            --accent: #3182ce;
            --accent-light: #4299e1;
            --success: #38a169;
            --warning: #d69e2e;
            --danger: #e53e3e;
            --text-dark: #2d3748;
            --text-light: #718096;
            --white: #ffffff;
            --gray-50: #f7fafc;
            --gray-100: #edf2f7;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e0;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--text-dark);
            min-height: 100vh;
            line-height: 1.6;
        }

        .main-header {
            background: var(--white);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .main-nav {
            display: flex;
            gap: 1.5rem;
        }

        .main-nav a {
            color: var(--primary);
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 600;
            border: 2px solid transparent;
        }

        .main-nav a:hover {
            background: var(--accent);
            color: var(--white);
            transform: translateY(-2px);
        }

        .hero {
            padding: 160px 0 100px;
            text-align: center;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--accent) 100%);
            color: var(--white);
            margin-top: 80px;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, var(--white), var(--gray-200), var(--accent-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero p {
            font-size: 1.4rem;
            opacity: 0.95;
            margin-bottom: 2.5rem;
            font-weight: 300;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 4rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1.2rem 2.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1.1rem;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(49, 130, 206, 0.3);
        }

        .btn-primary:hover {
            background: var(--accent-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(49, 130, 206, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--accent);
            border: 2px solid var(--accent);
        }

        .btn-secondary:hover {
            background: var(--accent);
            color: var(--white);
            transform: translateY(-2px);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2.5rem;
            margin: 5rem 0;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2.5rem 2rem;
            border-radius: 16px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .stat-number {
            font-size: 3.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--white), var(--accent-light));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            font-size: 1.1rem;
            color: var(--gray-200);
            font-weight: 500;
        }

        .ad-slider-container {
            margin: 6rem auto;
            max-width: 1200px;
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            height: 500px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .ad-slider {
            display: flex;
            transition: transform 0.6s ease;
            height: 100%;
        }

        .ad-slide {
            min-width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .ad-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(rgba(26, 54, 93, 0.8), rgba(45, 55, 72, 0.9));
        }

        .ad-content {
            background: rgba(255, 255, 255, 0.95);
            padding: 3rem;
            border-radius: 16px;
            text-align: center;
            max-width: 500px;
            z-index: 2;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .ad-content h3 {
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            color: var(--primary-dark);
            font-weight: 700;
        }

        .ad-content p {
            color: var(--text-light);
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .product-price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        .discount-badge {
            background: var(--success);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 1rem;
            display: inline-block;
        }

        .features-section {
            padding: 100px 0;
            background: var(--white);
        }

        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title h2 {
            font-size: 3rem;
            color: var(--primary-dark);
            margin-bottom: 1rem;
        }

        .section-title p {
            font-size: 1.2rem;
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .feature-card {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            padding: 3rem 2.5rem;
            border-radius: 16px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            border-color: var(--accent-light);
        }

        .feature-icon {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .feature-card h3 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--primary-dark);
            font-weight: 600;
        }

        .feature-card p {
            color: var(--text-light);
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .categories-section {
            padding: 100px 0;
            background: var(--gray-50);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .category-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .category-icon {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        .category-card h3 {
            font-size: 1.5rem;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .category-count {
            color: var(--text-light);
            font-weight: 500;
        }

        .testimonials-section {
            padding: 100px 0;
            background: var(--white);
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .testimonial-card {
            background: var(--gray-50);
            padding: 2rem;
            border-radius: 12px;
            border-left: 4px solid var(--accent);
        }

        .testimonial-text {
            font-style: italic;
            color: var(--text-dark);
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .testimonial-author {
            font-weight: 600;
            color: var(--accent);
        }

        .main-footer {
            background: var(--primary-dark);
            padding: 4rem 0 2rem;
            margin-top: 6rem;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 3rem;
        }

        .footer-section h4 {
            margin-bottom: 1.5rem;
            color: var(--accent-light);
            font-size: 1.3rem;
            font-weight: 600;
        }

        .footer-link {
            color: var(--gray-200);
            text-decoration: none;
            display: block;
            margin-bottom: 0.8rem;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        .footer-link:hover {
            color: var(--accent-light);
            transform: translateX(5px);
        }

        .social-links {
            display: flex;
            gap: 1.2rem;
        }

        .social-link {
            font-size: 1.8rem;
            color: var(--gray-200);
            transition: all 0.3s ease;
        }

        .social-link:hover {
            color: var(--accent-light);
            transform: translateY(-2px);
        }

        .copyright {
            text-align: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--primary-light);
            color: var(--gray-200);
            font-size: 1.1rem;
        }

        @media (max-width: 1024px) {
            .categories-grid,
            .testimonials-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.8rem;
            }
            .hero p {
                font-size: 1.2rem;
            }
            .stats {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .features-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .categories-grid,
            .testimonials-grid {
                grid-template-columns: 1fr;
            }
            .footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .cta-buttons {
                flex-direction: column;
                align-items: center;
                gap: 1.5rem;
            }
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
            .main-nav {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">StoreHub</div>
                <nav class="main-nav">
                    <a href="#features">Features</a>
                    <a href="customers/registration.php">Shop Now</a>
                    <a href="public_furniture_catalog.php">Furniture</a>
                    <a href="owner_signup.php">Open Your Store</a>
                    <a href="#categories">Categories</a>
                    <a href="#testimonials">Testimonials</a>
                </nav>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Welcome to StoreHub</h1>
            <p>Your Ultimate Online Marketplace Experience</p>
            <p>Where shopping meets innovation and business meets opportunity. Discover amazing products or start your own online store today!</p>
            
            <div class="cta-buttons">
                <a href="customers/registration.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> Start Shopping
                </a>
                <a href="owner_signup.php" class="btn btn-secondary">
                    <i class="fas fa-store"></i> Open Your Store
                </a>
            </div>

            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($total_products) ?></div>
                    <p>Products Available</p>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($total_stores) ?></div>
                    <p>Active Stores</p>
                </div>
                <div class="stat-card">
                    <div class="stat-number">24/7</div>
                    <p>Customer Support</p>
                </div>
            </div>
        </div>
    </section>

    <div class="ad-slider-container">
        <div class="ad-slider" id="adSlider">
            <?php foreach ($promotional_products as $index => $product): ?>
            <div class="ad-slide" style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('images/<?= htmlspecialchars(basename($product['image_url'])) ?>');">
                <div class="ad-content">
                    <h3>🔥 HOT DEAL ALERT! 🔥</h3>
                    <p><?= htmlspecialchars($product['name']) ?></p>
                    <p class="product-price">R <?= number_format($product['price'], 2) ?></p>
                    <?php if (!empty($product['discount_percent'])): ?>
                    <div class="discount-badge">
                        SAVE <?= intval($product['discount_percent']) ?>% OFF!
                    </div>
                    <?php endif; ?>
                    <a href="customers/registration.php" class="btn btn-primary">Shop Now</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <section id="features" class="features-section">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose StoreHub?</h2>
                <p>Experience the best online marketplace with features designed for both shoppers and business owners</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                    <h3>Lightning Fast</h3>
                    <p>Experience blazing fast shopping with our optimized platform and quick loading times</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Secure Shopping</h3>
                    <p>Bank-level security for all your transactions and personal data protection</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-truck"></i></div>
                    <h3>Fast Delivery</h3>
                    <p>Get your products delivered quickly with our efficient logistics network</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-headset"></i></div>
                    <h3>24/7 Support</h3>
                    <p>Round-the-clock customer support for all your needs and inquiries</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Business Analytics</h3>
                    <p>Comprehensive analytics and insights for store owners to grow their business</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                    <h3>Mobile Friendly</h3>
                    <p>Shop and manage your store from anywhere with our mobile-optimized platform</p>
                </div>
            </div>
        </div>
    </section>

    <section id="categories" class="categories-section">
        <div class="container">
            <div class="section-title">
                <h2>Popular Categories</h2>
                <p>Explore our wide range of product categories to find exactly what you're looking for</p>
            </div>
            <div class="categories-grid">
                <?php foreach ($featured_categories as $category): ?>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-box"></i></div>
                    <h3><?= htmlspecialchars($category['category']) ?></h3>
                    <p class="category-count"><?= $category['product_count'] ?> products</p>
                </div>
                <?php endforeach; ?>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-tshirt"></i></div>
                    <h3>Fashion</h3>
                    <p class="category-count">Latest trends</p>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-laptop"></i></div>
                    <h3>Electronics</h3>
                    <p class="category-count">Cutting-edge tech</p>
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="testimonials-section">
        <div class="container">
            <div class="section-title">
                <h2>What Our Users Say</h2>
                <p>Hear from our satisfied customers and successful store owners</p>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <p class="testimonial-text">"StoreHub has transformed my shopping experience. The variety of products and seamless checkout process is amazing!"</p>
                    <p class="testimonial-author">- Sarah Johnson, Customer</p>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"Opening my store on StoreHub was the best decision I made. The analytics and support helped me grow my business 3x!"</p>
                    <p class="testimonial-author">- Michael Chen, Store Owner</p>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"The customer service is exceptional. Quick responses and helpful solutions for any issue I've encountered."</p>
                    <p class="testimonial-author">- Emily Davis, Customer</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>StoreHub</h4>
                    <p>Your ultimate online marketplace connecting buyers and sellers worldwide with quality products and exceptional service.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <a href="unified_login.php" class="footer-link">Login</a>
                    <a href="customers/registration.php" class="footer-link">Customer Registration</a>
                    <a href="owner_signup.php" class="footer-link">Owner Registration</a>
                    <a href="#features" class="footer-link">Features</a>
                    <a href="#categories" class="footer-link">Categories</a>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <a href="#" class="footer-link">Help Center</a>
                    <a href="#" class="footer-link">Contact Us</a>
                    <a href="#" class="footer-link">FAQs</a>
                    <a href="#" class="footer-link">Shipping Info</a>
                    <a href="#" class="footer-link">Return Policy</a>
                </div>
                <div class="footer-section">
                    <h4>Connect With Us</h4>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
                    </div>
                    <p style="color: var(--gray-200); margin-top: 1rem;">Follow us for updates and promotions</p>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2025 StoreHub. All rights reserved. | Designed with ❤️ for the modern shopper and entrepreneur</p>
                <p style="margin-top: 0.5rem; font-size: 1rem;">Privacy Policy | Terms of Service | Cookie Policy</p>
            </div>
        </div>
    </footer>

    <script>
        // Ad Slider Functionality
        let currentSlide = 0;
        const slides = document.querySelectorAll('.ad-slide');
        const slider = document.getElementById('adSlider');
        
        function showSlide(index) {
            if (index >= slides.length) currentSlide = 0;
            if (index < 0) currentSlide = slides.length - 1;
            
            slider.style.transform = `translateX(-${currentSlide * 100}%)`;
        }
        
        function nextSlide() {
            currentSlide++;
            showSlide(currentSlide);
        }
        
        // Auto-rotate slides every 5 seconds
        setInterval(nextSlide, 5000);
        
        // Initialize first slide
        showSlide(currentSlide);

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading animation for better UX
        window.addEventListener('load', function() {
            document.body.classList.add('loaded');
        });

        // Enhanced ad slider with touch support
        let startX, moveX;
        const adSlider = document.getElementById('adSlider');
        
        adSlider.addEventListener('touchstart', function(e) {
            startX = e.touches[0].clientX;
        });
        
        adSlider.addEventListener('touchmove', function(e) {
            moveX = e.touches[0].clientX;
        });
        
        adSlider.addEventListener('touchend', function() {
            if (startX - moveX > 50) {
                nextSlide();
            } else if (moveX - startX > 50) {
                currentSlide--;
                showSlide(currentSlide);
            }
        });
    </script>
</body>
</html>
