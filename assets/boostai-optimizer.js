/**
 * StaticBoost Pro BoostAI™ Optimizer
 * Sistema de optimización inteligente propietario
 */
class SBPBoostAI {
    constructor() {
        this.config = window.sbpBoostAI?.config || {};
        this.sessionData = {
            startTime: Date.now(),
            scrollEvents: [],
            interactions: [],
            performanceMetrics: {}
        };
        this.isTracking = false;
    }

    /**
     * Inicializar el optimizador BoostAI™
     */
    async init() {
        try {
            // Inicializar tracking
            this.initTracking();
            
            // Aplicar optimizaciones inteligentes
            this.applyIntelligentOptimizations();
            
            console.log('StaticBoost Pro BoostAI™ initialized successfully');
        } catch (error) {
            console.warn('BoostAI™ failed to initialize:', error);
            // Fallback a optimizaciones básicas
            this.applyBasicOptimizations();
        }
    }

    /**
     * Inicializar tracking de métricas
     */
    initTracking() {
        this.isTracking = true;

        // Tracking de scroll
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                this.trackScrollBehavior();
            }, 100);
        });

        // Tracking de Core Web Vitals
        this.trackWebVitals();

        // Tracking de interacciones
        this.trackInteractions();

        // Enviar datos cada 30 segundos
        setInterval(() => {
            this.sendMetrics();
        }, 30000);

        // Enviar datos al salir de la página
        window.addEventListener('beforeunload', () => {
            this.sendMetrics();
        });
    }

    /**
     * Rastrear comportamiento de scroll
     */
    trackScrollBehavior() {
        const scrollTop = window.pageYOffset;
        const documentHeight = document.documentElement.scrollHeight;
        const windowHeight = window.innerHeight;
        const scrollDepth = (scrollTop + windowHeight) / documentHeight;

        this.sessionData.scrollEvents.push({
            timestamp: Date.now(),
            scrollTop: scrollTop,
            scrollDepth: scrollDepth,
            velocity: this.calculateScrollVelocity()
        });

        // Predicción inteligente de intención de scroll
        this.predictScrollIntention(scrollDepth, this.calculateScrollVelocity());
    }

    /**
     * Calcular velocidad de scroll
     */
    calculateScrollVelocity() {
        const events = this.sessionData.scrollEvents;
        if (events.length < 2) return 0;

        const current = events[events.length - 1];
        const previous = events[events.length - 2];
        
        const timeDiff = current.timestamp - previous.timestamp;
        const scrollDiff = current.scrollTop - previous.scrollTop;
        
        return timeDiff > 0 ? Math.abs(scrollDiff / timeDiff) : 0;
    }

    /**
     * Predecir intención de scroll usando BoostAI™
     */
    predictScrollIntention(scrollDepth, velocity) {
        // Algoritmo propietario de predicción
        const deviceFactor = this.getDeviceType();
        const connectionFactor = this.getConnectionSpeed();
        
        // Fórmula propietaria BoostAI™
        const prediction = (scrollDepth * 0.4) + 
                          (velocity * 0.3) + 
                          (deviceFactor * 0.2) + 
                          (connectionFactor * 0.1);

        // Aplicar optimizaciones basadas en predicción
        if (prediction > this.config.scroll_prediction_threshold) {
            this.preloadNextContent();
        }
    }

    /**
     * Obtener tipo de dispositivo como factor
     */
    getDeviceType() {
        return window.sbpBoostAI?.device_type === 'mobile' ? 0.8 : 1.0;
    }

    /**
     * Obtener velocidad de conexión estimada
     */
    getConnectionSpeed() {
        if (navigator.connection) {
            const connection = navigator.connection;
            switch (connection.effectiveType) {
                case 'slow-2g': return 0.25;
                case '2g': return 0.5;
                case '3g': return 0.75;
                case '4g': return 1.0;
                default: return 0.75;
            }
        }
        return 0.75; // Default
    }

    /**
     * Precargar contenido siguiente
     */
    preloadNextContent() {
        // Precargar imágenes que están por aparecer
        const images = document.querySelectorAll('img[data-src]');
        const scrollTop = window.pageYOffset;
        const windowHeight = window.innerHeight;
        const preloadDistance = this.config.preload_distance || 200;

        images.forEach(img => {
            const imgTop = img.getBoundingClientRect().top + scrollTop;
            if (imgTop - scrollTop < windowHeight + preloadDistance) {
                this.lazyLoadImage(img);
            }
        });

        // Precargar siguiente página si está configurado
        if (this.config.prefetch_next_page) {
            this.prefetchNextPage();
        }
    }

    /**
     * Carga lazy de imágenes optimizada
     */
    lazyLoadImage(img) {
        if (img.dataset.src && !img.dataset.loaded) {
            // Crear imagen optimizada
            const optimizedSrc = this.getOptimizedImageSrc(img.dataset.src);
            
            img.src = optimizedSrc;
            img.dataset.loaded = 'true';
            
            // Añadir clase para animación
            img.classList.add('sbp-loaded');
        }
    }

    /**
     * Obtener URL de imagen optimizada
     */
    getOptimizedImageSrc(originalSrc) {
        // Detectar soporte para formatos modernos
        const supportsWebP = this.supportsImageFormat('webp');
        const supportsAVIF = this.supportsImageFormat('avif');
        
        // Construir URL optimizada
        let optimizedSrc = originalSrc;
        
        if (supportsAVIF) {
            optimizedSrc = originalSrc.replace(/\.(jpg|jpeg|png)$/i, '.avif');
        } else if (supportsWebP) {
            optimizedSrc = originalSrc.replace(/\.(jpg|jpeg|png)$/i, '.webp');
        }
        
        return optimizedSrc;
    }

    /**
     * Verificar soporte para formato de imagen
     */
    supportsImageFormat(format) {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL(`image/${format}`).indexOf(`data:image/${format}`) === 0;
    }

    /**
     * Precargar siguiente página
     */
    prefetchNextPage() {
        const nextPageLink = document.querySelector('a[rel="next"], .next-page a, .pagination .next a');
        
        if (nextPageLink && !nextPageLink.dataset.prefetched) {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = nextPageLink.href;
            document.head.appendChild(link);
            
            nextPageLink.dataset.prefetched = 'true';
        }
    }

    /**
     * Rastrear Core Web Vitals
     */
    trackWebVitals() {
        // LCP (Largest Contentful Paint)
        if ('PerformanceObserver' in window) {
            try {
                const lcpObserver = new PerformanceObserver((entryList) => {
                    const entries = entryList.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    this.sessionData.performanceMetrics.lcp = lastEntry.startTime;
                });
                lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });

                // FID (First Input Delay)
                const fidObserver = new PerformanceObserver((entryList) => {
                    const entries = entryList.getEntries();
                    entries.forEach(entry => {
                        this.sessionData.performanceMetrics.fid = entry.processingStart - entry.startTime;
                    });
                });
                fidObserver.observe({ entryTypes: ['first-input'] });

                // CLS (Cumulative Layout Shift)
                let clsValue = 0;
                const clsObserver = new PerformanceObserver((entryList) => {
                    const entries = entryList.getEntries();
                    entries.forEach(entry => {
                        if (!entry.hadRecentInput) {
                            clsValue += entry.value;
                        }
                    });
                    this.sessionData.performanceMetrics.cls = clsValue;
                });
                clsObserver.observe({ entryTypes: ['layout-shift'] });

            } catch (error) {
                console.warn('Performance tracking failed:', error);
            }
        }
    }

    /**
     * Rastrear interacciones del usuario
     */
    trackInteractions() {
        ['click', 'touchstart', 'keydown'].forEach(eventType => {
            document.addEventListener(eventType, (event) => {
                this.sessionData.interactions.push({
                    type: eventType,
                    timestamp: Date.now(),
                    target: event.target.tagName
                });
            });
        });
    }

    /**
     * Aplicar optimizaciones inteligentes
     */
    applyIntelligentOptimizations() {
        // CSS crítico inline si está configurado
        if (this.config.critical_css_inline) {
            this.inlineCriticalCSS();
        }

        // Optimizar fuentes
        this.optimizeFonts();

        // Configurar lazy loading inteligente
        this.setupIntelligentLazyLoading();

        // Preconectar a dominios externos
        this.preconnectExternalDomains();
    }

    /**
     * Aplicar optimizaciones básicas (fallback)
     */
    applyBasicOptimizations() {
        // Lazy loading básico
        this.setupBasicLazyLoading();
        
        // Precargar recursos críticos
        this.preloadCriticalResources();
    }

    /**
     * CSS crítico inline
     */
    inlineCriticalCSS() {
        const criticalCSS = document.querySelector('link[data-critical-css]');
        if (criticalCSS) {
            fetch(criticalCSS.href)
                .then(response => response.text())
                .then(css => {
                    const style = document.createElement('style');
                    style.textContent = css;
                    document.head.appendChild(style);
                    criticalCSS.remove();
                })
                .catch(error => console.warn('Failed to inline critical CSS:', error));
        }
    }

    /**
     * Optimizar fuentes
     */
    optimizeFonts() {
        const fontLinks = document.querySelectorAll('link[href*="fonts.googleapis.com"]');
        fontLinks.forEach(link => {
            link.setAttribute('rel', 'preload');
            link.setAttribute('as', 'style');
            link.setAttribute('onload', "this.onload=null;this.rel='stylesheet'");
        });
    }

    /**
     * Configurar lazy loading inteligente
     */
    setupIntelligentLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        const threshold = this.config.lazy_load_threshold || 300;

        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.lazyLoadImage(entry.target);
                    imageObserver.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: `${threshold}px`
        });

        images.forEach(img => imageObserver.observe(img));
    }

    /**
     * Configurar lazy loading básico
     */
    setupBasicLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('sbp-loaded');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    /**
     * Precargar recursos críticos
     */
    preloadCriticalResources() {
        // Precargar CSS crítico
        const criticalCSS = document.querySelector('link[data-critical]');
        if (criticalCSS) {
            const preload = document.createElement('link');
            preload.rel = 'preload';
            preload.as = 'style';
            preload.href = criticalCSS.href;
            document.head.appendChild(preload);
        }
    }

    /**
     * Preconectar a dominios externos
     */
    preconnectExternalDomains() {
        const externalDomains = [
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'cdn.jsdelivr.net'
        ];

        externalDomains.forEach(domain => {
            const link = document.createElement('link');
            link.rel = 'preconnect';
            link.href = `https://${domain}`;
            link.crossOrigin = 'anonymous';
            document.head.appendChild(link);
        });
    }

    /**
     * Enviar métricas al servidor
     */
    sendMetrics() {
        if (!this.isTracking || !window.sbpBoostAI?.ajax_url) return;

        const timeOnPage = Math.round((Date.now() - this.sessionData.startTime) / 1000);
        const scrollDepth = this.getMaxScrollDepth();

        const data = {
            action: 'sbp_track_metrics',
            nonce: window.sbpBoostAI.nonce,
            session_id: window.sbpBoostAI.session_id,
            page_url: window.sbpBoostAI.page_url,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            scroll_depth: scrollDepth,
            time_on_page: timeOnPage,
            device_type: window.sbpBoostAI.device_type,
            connection_type: this.getConnectionType(),
            ...this.sessionData.performanceMetrics
        };

        // Enviar usando sendBeacon si está disponible
        if (navigator.sendBeacon) {
            const formData = new FormData();
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });
            navigator.sendBeacon(window.sbpBoostAI.ajax_url, formData);
        } else {
            // Fallback a fetch
            fetch(window.sbpBoostAI.ajax_url, {
                method: 'POST',
                body: new URLSearchParams(data)
            }).catch(error => console.warn('Failed to send metrics:', error));
        }
    }

    /**
     * Obtener profundidad máxima de scroll
     */
    getMaxScrollDepth() {
        const events = this.sessionData.scrollEvents;
        if (events.length === 0) return 0;
        
        return Math.max(...events.map(event => event.scrollDepth));
    }

    /**
     * Obtener tipo de conexión
     */
    getConnectionType() {
        if (navigator.connection) {
            return navigator.connection.effectiveType;
        }
        return 'unknown';
    }
}

// Inicializar automáticamente
window.SBPBoostAI = new SBPBoostAI();

// CSS para animaciones de carga
const style = document.createElement('style');
style.textContent = `
.sbp-loaded {
    opacity: 1 !important;
    transition: opacity 0.3s ease-in-out;
}

img[data-src] {
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

img[data-src].sbp-loaded {
    opacity: 1;
}
`;
document.head.appendChild(style);