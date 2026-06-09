/**
 * 사주포춘 - 메인 자바스크립트
 */

document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initAnimations();
});

/**
 * 탭 초기화
 */
function initTabs() {
    document.querySelectorAll('.tabs').forEach(tabContainer => {
        const tabs = tabContainer.querySelectorAll('.tab-item');
        const tabId = tabContainer.dataset.tabs;
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const target = this.dataset.target;
                
                // 활성 탭 변경
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // 탭 콘텐츠 변경
                if (tabId) {
                    document.querySelectorAll(`[data-tab-group="${tabId}"]`).forEach(content => {
                        content.classList.remove('active');
                    });
                    const targetContent = document.getElementById(target);
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                }
            });
        });
    });
}

/**
 * 교차 관찰자를 사용한 스크롤 애니메이션
 */
function initAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('.card, .fortune-section, .feature-card').forEach(el => {
        observer.observe(el);
    });
}

/**
 * 레이더 차트 생성
 * @param {string} canvasId - Canvas 엘리먼트 ID
 * @param {object} data - { labels: [], values: [] }
 */
function createRadarChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: 'rgba(255, 212, 59, 0.2)',
                borderColor: 'rgba(245, 197, 24, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(245, 197, 24, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    min: 0,
                    ticks: {
                        stepSize: 20,
                        font: { size: 10 },
                        display: false,
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.06)',
                    },
                    angleLines: {
                        color: 'rgba(0,0,0,0.06)',
                    },
                    pointLabels: {
                        font: {
                            size: 12,
                            family: "'Noto Sans KR', sans-serif",
                            weight: '600'
                        },
                        color: '#666'
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed.r + '점';
                        }
                    }
                }
            }
        }
    });
}

/**
 * 오행 바 차트 애니메이션
 */
function animateOhangBars() {
    document.querySelectorAll('.ohang-bar-fill').forEach(bar => {
        const width = bar.dataset.width;
        setTimeout(() => {
            bar.style.width = width + '%';
        }, 100);
    });
}

/**
 * AJAX 요청 유틸리티
 * @param {string} url
 * @param {object} options
 * @returns {Promise}
 */
async function fetchAPI(url, options = {}) {
    const defaults = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
    };
    
    const config = { ...defaults, ...options };
    
    try {
        const response = await fetch(url, config);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('API Error:', error);
        return { success: false, message: '서버 오류가 발생했습니다.' };
    }
}

/**
 * 폼 데이터를 URL 인코딩 문자열로 변환
 * @param {HTMLFormElement} form
 * @returns {string}
 */
function serializeForm(form) {
    const formData = new FormData(form);
    return new URLSearchParams(formData).toString();
}

/**
 * 알림 표시
 * @param {string} message
 * @param {string} type (success, error, warning, info)
 */
function showAlert(message, type = 'info') {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    const flash = document.createElement('div');
    flash.className = `flash-message flash-${type}`;
    flash.id = 'flashMessage';
    flash.innerHTML = `
        <div class="flash-inner">
            <i class="fas fa-${icons[type]}"></i>
            <span>${message}</span>
            <button class="flash-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.prepend(flash);
    
    setTimeout(() => {
        flash.style.opacity = '0';
        flash.style.transform = 'translateY(-100%)';
        setTimeout(() => flash.remove(), 300);
    }, 4000);
}

/**
 * 숫자 애니메이션 (카운트업)
 * @param {HTMLElement} element
 * @param {number} target
 * @param {number} duration (ms)
 */
function animateNumber(element, target, duration = 1000) {
    let start = 0;
    const increment = target / (duration / 16);
    const timer = setInterval(() => {
        start += increment;
        if (start >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(start);
        }
    }, 16);
}

/**
 * 비밀번호 강도 표시
 * @param {HTMLInputElement} input
 * @param {HTMLElement} indicator
 */
function checkPasswordStrength(input, indicator) {
    const pwd = input.value;
    let strength = 0;
    
    if (pwd.length >= 8) strength++;
    if (pwd.length >= 12) strength++;
    if (/[A-Z]/.test(pwd)) strength++;
    if (/[a-z]/.test(pwd)) strength++;
    if (/[0-9]/.test(pwd)) strength++;
    if (/[^A-Za-z0-9]/.test(pwd)) strength++;
    
    const levels = ['매우 약함', '약함', '보통', '강함', '매우 강함'];
    const colors = ['#F44336', '#FF9800', '#FFC107', '#8BC34A', '#4CAF50'];
    
    const level = Math.min(Math.floor(strength / 1.2), 4);
    
    if (indicator) {
        indicator.textContent = levels[level];
        indicator.style.color = colors[level];
    }
}

/**
 * 확인 모달
 * @param {string} message
 * @returns {boolean}
 */
function confirmAction(message) {
    return confirm(message);
}
