// AI-Powered Enhancements for Customer Experience
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced Voice Search with AI
    const voiceSearchBtn = document.getElementById('voiceSearchBtn');
    const searchInput = document.getElementById('searchInput');
    const voiceSearchStatus = document.getElementById('voiceSearchStatus');
    let searchSuggestions = [];

    // Predictive Search with Autocomplete
    searchInput.addEventListener('input', debounce(function() {
        const query = this.value.trim();
        if (query.length > 1) {
            fetch('get_search_suggestions.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(suggestions => {
                    searchSuggestions = suggestions;
                    showSearchSuggestions(suggestions);
                })
                .catch(console.error);
        } else {
            hideSearchSuggestions();
        }
    }, 300));

    function showSearchSuggestions(suggestions) {
        hideSearchSuggestions();
        if (suggestions.length === 0) return;

        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.id = 'search-suggestions';
        suggestionsDiv.style.position = 'absolute';
        suggestionsDiv.style.top = '100%';
        suggestionsDiv.style.left = '0';
        suggestionsDiv.style.right = '0';
        suggestionsDiv.style.backgroundColor = 'white';
        suggestionsDiv.style.border = '1px solid #ccc';
        suggestionsDiv.style.borderTop = 'none';
        suggestionsDiv.style.borderRadius = '0 0 5px 5px';
        suggestionsDiv.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        suggestionsDiv.style.zIndex = '1000';
        suggestionsDiv.style.maxHeight = '200px';
        suggestionsDiv.style.overflowY = 'auto';

        suggestions.forEach(suggestion => {
            const suggestionItem = document.createElement('div');
            suggestionItem.style.padding = '10px';
            suggestionItem.style.cursor = 'pointer';
            suggestionItem.style.borderBottom = '1px solid #eee';
            suggestionItem.innerHTML = `
                <div style="font-weight: bold;">${suggestion.name}</div>
                <div style="font-size: 0.8rem; color: #666;">${suggestion.category}</div>
            `;
            suggestionItem.addEventListener('click', () => {
                searchInput.value = suggestion.name;
                hideSearchSuggestions();
                document.querySelector('.header-search form').submit();
            });
            suggestionItem.addEventListener('mouseenter', () => {
                suggestionItem.style.backgroundColor = '#f8f9fa';
            });
            suggestionItem.addEventListener('mouseleave', () => {
                suggestionItem.style.backgroundColor = 'white';
            });
            suggestionsDiv.appendChild(suggestionItem);
        });

        searchInput.parentNode.appendChild(suggestionsDiv);
    }

    function hideSearchSuggestions() {
        const existing = document.getElementById('search-suggestions');
        if (existing) existing.remove();
    }

    // Debounce function for search
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

    // Enhanced Voice Recognition with AI Command Processing
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-US';
        recognition.maxAlternatives = 3;

        voiceSearchBtn.addEventListener('click', () => {
            try {
                voiceSearchStatus.style.display = 'block';
                voiceSearchStatus.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Listening...';
                recognition.start();
            } catch (error) {
                voiceSearchStatus.innerHTML = 'Error: ' + error.message;
                setTimeout(() => { voiceSearchStatus.style.display = 'none'; }, 3000);
            }
        });

        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            const confidence = event.results[0][0].confidence;
            
            // AI-powered voice command processing
            if (confidence > 0.7) {
                if (processAdvancedVoiceCommand(transcript)) {
                    voiceSearchStatus.innerHTML = '<i class="fas fa-check" style="color: green;"></i> Command processed';
                } else {
                    searchInput.value = transcript;
                    voiceSearchStatus.innerHTML = '<i class="fas fa-check" style="color: green;"></i>';
                    setTimeout(() => {
                        document.querySelector('.header-search form').submit();
                    }, 500);
                }
            } else {
                voiceSearchStatus.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: orange;"></i> Low confidence';
            }
            
            setTimeout(() => { voiceSearchStatus.style.display = 'none'; }, 2000);
        };

        recognition.onerror = (event) => {
            let errorMessage = 'Error: ';
            switch(event.error) {
                case 'no-speech':
                    errorMessage = 'No speech was detected.';
                    break;
                case 'audio-capture':
                    errorMessage = 'No microphone was found.';
                    break;
                case 'not-allowed':
                    errorMessage = 'Microphone permission denied.';
                    break;
                default:
                    errorMessage = 'Error occurred: ' + event.error;
            }
            voiceSearchStatus.innerHTML = errorMessage;
            setTimeout(() => { voiceSearchStatus.style.display = 'none'; }, 3000);
        };

        recognition.onend = () => {
            if (voiceSearchStatus.style.display !== 'none') {
                setTimeout(() => { voiceSearchStatus.style.display = 'none'; }, 1000);
            }
        };

    } else {
        voiceSearchBtn.style.display = 'none';
        console.log('Speech recognition not supported in this browser.');
    }

    // Advanced Voice Command Processing with AI
    const advancedVoiceCommands = {
        'show me': (query) => {
            searchInput.value = query.replace('show me', '').trim();
            document.querySelector('.header-search form').submit();
        },
        'find': (query) => {
            searchInput.value = query.replace('find', '').trim();
            document.querySelector('.header-search form').submit();
        },
        'search for': (query) => {
            searchInput.value = query.replace('search for', '').trim();
            document.querySelector('.header-search form').submit();
        },
        'I want to buy': (query) => {
            searchInput.value = query.replace('I want to buy', '').trim();
            document.querySelector('.header-search form').submit();
        },
        'add to cart': (query) => {
            const productName = query.replace('add to cart', '').trim();
            // Find product in suggestions or search results
            const matchingProduct = searchSuggestions.find(s => 
                s.name.toLowerCase().includes(productName.toLowerCase())
            );
            if (matchingProduct) {
                addToCartVoice(matchingProduct.name);
            }
        },
        'show recommendations': () => {
            showRecommendedProducts();
        },
        'what\'s on sale': () => {
            searchInput.value = 'sale';
            document.querySelector('.header-search form').submit();
        }
    };

    function processAdvancedVoiceCommand(transcript) {
        const lowerTranscript = transcript.toLowerCase();
        for (const [command, handler] of Object.entries(advancedVoiceCommands)) {
            if (lowerTranscript.includes(command)) {
                handler(transcript);
                return true;
            }
        }
        return false;
    }

    function addToCartVoice(productName) {
        fetch('cart.php?voice_add=' + encodeURIComponent(productName), {
            method: 'POST'
        })
        .then(response => response.text())
        .then(() => {
            alert('Added ' + productName + ' to cart via voice command!');
        })
        .catch(console.error);
    }

    // AI-Powered Product Recommendations Popup
    function showRecommendedProducts() {
        const recommendedProducts = window.recommendedProducts || [];
        if (recommendedProducts.length > 0) {
            const popup = document.createElement('div');
            popup.className = 'ai-recommendation-popup';
            popup.style.position = 'fixed';
            popup.style.top = '50%';
            popup.style.left = '50%';
            popup.style.transform = 'translate(-50%, -50%)';
            popup.style.backgroundColor = 'white';
            popup.style.border = '2px solid var(--primary-color)';
            popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.3)';
            popup.style.padding = '20px';
            popup.style.borderRadius = '15px';
            popup.style.zIndex = '2000';
            popup.style.maxWidth = '500px';
            popup.style.maxHeight = '80vh';
            popup.style.overflowY = 'auto';

            const title = document.createElement('h4');
            title.innerText = '🤖 AI Recommendations Just For You';
            title.style.margin = '0 0 15px 0';
            title.style.color = 'var(--primary-color)';
            title.style.textAlign = 'center';
            popup.appendChild(title);

            const subtitle = document.createElement('p');
            subtitle.innerText = 'Based on your browsing history and preferences';
            subtitle.style.margin = '0 0 20px 0';
            subtitle.style.color = '#666';
            subtitle.style.textAlign = 'center';
            subtitle.style.fontSize = '0.9rem';
            popup.appendChild(subtitle);

            recommendedProducts.forEach(product => {
                const productDiv = document.createElement('div');
                productDiv.style.display = 'flex';
                productDiv.style.alignItems = 'center';
                productDiv.style.marginBottom = '15px';
                productDiv.style.padding = '15px';
                productDiv.style.border = '1px solid #eee';
                productDiv.style.borderRadius = '10px';
                productDiv.style.cursor = 'pointer';
                productDiv.style.transition = 'all 0.3s ease';

                productDiv.onmouseover = () => {
                    productDiv.style.backgroundColor = '#f8f9fa';
                    productDiv.style.transform = 'translateX(5px)';
                };
                productDiv.onmouseout = () => {
                    productDiv.style.backgroundColor = 'white';
                    productDiv.style.transform = 'translateX(0)';
                };

                productDiv.onclick = () => {
                    searchInput.value = product.name;
                    document.querySelector('.header-search form').submit();
                };

                const img = document.createElement('img');
                const imageFilename = product.image_url ? product.image_url.split('/').pop() : 'default_product.jpg';
                img.src = '../images/' + imageFilename;
                img.alt = product.name;
                img.style.width = '60px';
                img.style.height = '60px';
                img.style.objectFit = 'cover';
                img.style.marginRight = '15px';
                img.style.borderRadius = '8px';
                img.onerror = function() {
                    this.src = '../images/default_product.jpg';
                };

                const productInfo = document.createElement('div');
                productInfo.style.flex = '1';
                productInfo.innerHTML = `
                    <strong style="font-size: 1rem; display: block; margin-bottom: 5px;">${product.name}</strong>
                    <div style="color: var(--primary-color); font-weight: bold; font-size: 1.1rem;">
                        R ${parseFloat(product.price).toFixed(2)}
                    </div>
                    <div style="color: #666; font-size: 0.9rem;">${product.category}</div>
                `;

                const addToCartBtn = document.createElement('button');
                addToCartBtn.innerHTML = '<i class="fas fa-cart-plus"></i> Add';
                addToCartBtn.style.background = 'var(--primary-color)';
                addToCartBtn.style.color = 'white';
                addToCartBtn.style.border = 'none';
                addToCartBtn.style.borderRadius = '5px';
                addToCartBtn.style.padding = '8px 12px';
                addToCartBtn.style.cursor = 'pointer';
                addToCartBtn.style.marginLeft = '10px';
                
                addToCartBtn.onclick = (e) => {
                    e.stopPropagation();
                    fetch('cart.php?add_to_cart=' + product.product_id + '&quantity=1', {
                        method: 'POST'
                    })
                    .then(response => response.text())
                    .then(() => {
                        alert('Added ' + product.name + ' to cart!');
                    })
                    .catch(console.error);
                };

                productDiv.appendChild(img);
                productDiv.appendChild(productInfo);
                productDiv.appendChild(addToCartBtn);
                popup.appendChild(productDiv);
            });

            const closeBtn = document.createElement('button');
            closeBtn.innerText = 'Close';
            closeBtn.style.marginTop = '15px';
            closeBtn.style.padding = '10px 20px';
            closeBtn.style.backgroundColor = 'var(--primary-color)';
            closeBtn.style.color = 'white';
            closeBtn.style.border = 'none';
            closeBtn.style.borderRadius = '5px';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.width = '100%';
            closeBtn.onclick = () => {
                popup.remove();
                overlay.remove();
            };
            popup.appendChild(closeBtn);

            // Overlay
            const overlay = document.createElement('div');
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.right = '0';
            overlay.style.bottom = '0';
            overlay.style.backgroundColor = 'rgba(0,0,0,0.7)';
            overlay.style.zIndex = '1999';
            overlay.onclick = () => {
                popup.remove();
                overlay.remove();
            };

            document.body.appendChild(overlay);
            document.body.appendChild(popup);

            // Auto-close after 20 seconds
            setTimeout(() => {
                if (document.body.contains(popup)) {
                    popup.remove();
                    overlay.remove();
                }
            }, 20000);
        }
    }

    // Show AI recommendations on page load
    setTimeout(showRecommendedProducts, 40000);
    
    // Voice command to show recommendations
    document.addEventListener('keydown', function(event) {
        if (event.ctrlKey && event.key === 'r') {
            event.preventDefault();
            showRecommendedProducts();
        }
    });

    // Close suggestions when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.header-search') && !event.target.closest('#search-suggestions')) {
            hideSearchSuggestions();
        }
    });

    // Make recommendations available globally
    window.showRecommendedProducts = showRecommendedProducts;
});

// Export functions for global access
window.AIEnhancements = {
    showRecommendedProducts: function() {
        if (typeof window.showRecommendedProducts === 'function') {
            window.showRecommendedProducts();
        }
    }
};
