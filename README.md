# StoreHub - Advanced E-Commerce Marketplace Platform

<div align="center">
  
![StoreHub Logo](images/logo.png)
*Note: Add your logo image here for better branding*

[![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14%2B-336791?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6%2B-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/HTML)
[![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/CSS)

</div>

## 🚀 Overview

**StoreHub** is a comprehensive, modern e-commerce marketplace platform designed to connect customers, store managers, and business owners. It features advanced AI-driven personalization, multi-role dashboards, real-time analytics, and seamless shopping experiences. Built with PHP and PostgreSQL, StoreHub powers a dynamic online shopping ecosystem with cutting-edge features like AI recommendations, intelligent chat support, and business intelligence tools.

### Key Highlights
- **Multi-Tenant Marketplace**: Customers shop, owners manage stores, managers oversee operations
- **AI-Powered Personalization**: Province/age-based product filtering, recommendation engine, smart search
- **Advanced Customer Support**: AI chatbox with voice search, quick replies, and conversation memory
- **Business Intelligence**: Real-time analytics, sales forecasting, K-Means customer segmentation
- **Role-Based Access**: Secure dashboards for customers, managers, and owners
- **Mobile-Responsive**: Optimized for all devices with modern UI/UX

## ✨ Features

### Customer Features
```
📱 Shopping Experience
• Province & age-group personalized product discovery
• AI-powered search with voice recognition & suggestions
• Shopping cart, wishlist, order tracking
• Secure checkout with demo bank transfer
• Real-time notifications & order updates

🤖 AI Enhancements
• Intelligent chatbot with product images & ordering
• Personalized recommendations based on behavior
• Semantic search suggestions
• Quick replies & proactive product suggestions

📊 Engagement
• Product browsing with filtering toggles
• Order history & details
• Wishlist management
```

### Manager Features
```
👨‍💼 Operations Dashboard
• Real-time order monitoring & approval/decline
• Inventory tracking with stock alerts
• Product performance analytics
• Customer feedback insights
• Promotions & tracking management
```

### Owner Features
```
💼 Business Dashboard
• Product & store management
• Pricing & promotions control
• Comprehensive sales analytics
• AI business intelligence tools
• Multi-manager account management
```

### Technical Features
```
🔬 AI/ML Integration
• K-Means customer segmentation
• Time series sales forecasting
• User behavior tracking & learning
• Recommendation weights by demographics

🛡️ Security
• Role-based access control
• Session management & cleanup
• Prepared statements & input validation
• Login attempt tracking
```

## 🏗️ Project Structure

```
store-F/
├── index.php                 # Landing page with promotional slider
├── db_connection.php         # PostgreSQL database connection
├── customers/                # Customer-facing features
│   ├── product_browse.php    # Main product catalog
│   ├── ai_customer_support.php # AI chatbot
│   ├── cart.php             # Shopping cart
│   ├── checkout.php         # Secure checkout
│   └── ai_learning_system.php # AI recommendation engine
├── manager/                 # Manager dashboard
├── owner/                   # Owner dashboard (owner_dashboard.php)
├── api/                     # API endpoints (chatbot.php)
├── scripts/                 # Maintenance & seeding scripts
├── images/                  # Product images & assets
└── ...                      # CSS, auth, analytics files
```

## 🚀 Quick Start

### Prerequisites
- PHP 8.0+
- PostgreSQL 14+
- XAMPP/WAMP (for local development)
- Web browser (Chrome/Firefox recommended)

### Installation
1. **Clone/Download** the project to your web server directory:
   ```
   c:/xampp/htdocs/store-F/
   ```

2. **Database Setup**:
   ```sql
   -- Create database
   CREATE DATABASE storehub;
   
   -- Update db_connection.php with your credentials
   $host = 'localhost';
   $db   = 'storehub';
   $user = 'postgres';
   $pass = 'your_password';
   ```

3. **Run Seeding Scripts**:
   ```bash
   # Seed products
   php scripts/seed_products.php
   php scripts/seed2_products.php
   php scripts/seed3_products.php
   
   # Setup AI learning
   php customers/setup_ai_learning.php
   ```

4. **Start Server**:
   ```bash
   # XAMPP Apache + PostgreSQL
   # Access at: http://localhost/store-F/
   ```

5. **Test Features**:
   ```
   • Visit index.php (landing page)
   • Register as customer: customers/registration.php
   • Owner signup: owner_signup.php
   • Test AI chat: customers/ai_customer_support.php
   ```

## 📊 Database Schema

**Core Tables**:
- `products` - Product catalog with images, pricing, stock
- `customers` - User profiles with province, age group
- `orders` - Order management & tracking
- `user_behavior_tracking` - AI learning data
- `recommendation_weights` - Personalized weights
- `chat_sessions`, `chat_messages` - AI chat history

## 🤖 AI Features Deep Dive

### Recommendation Engine
```
1. Tracks user interactions (purchase=5pts, search=3pts, etc.)
2. Learns province/age patterns from behavior
3. Computes category preference weights
4. Delivers personalized product rankings
```

### Smart Chatbot
```
• Human-like conversations with context memory
• Product search with image display
• Voice search & quick replies
• Order status queries
• Live inventory & pricing
```

## 🔧 Development Scripts

| Script | Purpose |
|--------|---------|
| `seed_products.php` | Populate initial product catalog |
| `update_order_status.php` | Cron job for order processing |
| `archive_old_products.php` | Cleanup old/outdated products |
| `setup_ai_learning.php` | Initialize AI tables & data |

## 🧪 Testing

```bash
# Test database connection
php test_db.php

# Test owner signup
php test_owner_signup.php

# Test AI features
php test_ai_features.php

# Test tracking
php test_tracking.php
```

## 📈 Performance & Scalability

- **Optimized Queries**: Indexed tables, prepared statements
- **Caching**: AI weights & popular products
- **Async Processing**: Order updates via cron
- **Responsive Design**: Mobile-first approach
- **CDN Ready**: Static assets optimized

## 🔒 Security Measures

- Role-based access control (Customer/Manager/Owner)
- Login attempt tracking & blocking
- Session cleanup cron jobs
- Input sanitization throughout
- Secure PostgreSQL connections

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📄 License

This project is open-source and available under the [MIT License](LICENSE).

## 👥 Contact

**StoreHub Team**  
📧 [contact@storehub.com](mailto:contact@storehub.com)  
🌐 [storehub.com](http://localhost/store-F/)  

<div align="center">

**⭐ Star us on GitHub if you found this helpful!**  
**🙏 Thanks for using StoreHub!**

</div>

---

*Last Updated: December 2024*  
*Version: 2.0 - AI Enhanced Marketplace*
