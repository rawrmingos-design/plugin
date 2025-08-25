/**
 * Success Page Animations for PPDB Form
 */
document.addEventListener('DOMContentLoaded', function () {
  const container = document.querySelector('.ppdb-success-container');

  if (!container) return;

  // Initialize with fade-in state
  container.style.opacity = '0';
  container.style.transform = 'translateY(20px)';
  container.style.transition = 'all 0.6s ease';

  // Main container entrance animation
  setTimeout(() => {
    container.style.opacity = '1';
    container.style.transform = 'translateY(0)';
  }, 100);

  // Animate cards sequentially
  const cards = container.querySelectorAll('.ppdb-success-card');
  cards.forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'all 0.5s ease';

    setTimeout(() => {
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    }, 800 + (index * 200));
  });

  // Add hover effects for action buttons
  const actionBtns = container.querySelectorAll('.ppdb-action-btn');
  actionBtns.forEach(btn => {
    btn.addEventListener('mouseenter', function () {
      this.style.transform = 'translateY(-2px) scale(1.02)';
    });

    btn.addEventListener('mouseleave', function () {
      this.style.transform = 'translateY(0) scale(1)';
    });
  });

  // Add click animation for buttons
  actionBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      this.style.transform = 'scale(0.98)';
      setTimeout(() => {
        this.style.transform = 'translateY(-2px) scale(1.02)';
      }, 150);
    });
  });

  // Auto-scroll to success section (if form was below fold)
  const scrollToSuccess = () => {
    const rect = container.getBoundingClientRect();
    const isVisible = rect.top >= 0 && rect.top <= window.innerHeight;

    if (!isVisible) {
      container.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  };

  // Scroll after initial animation
  setTimeout(scrollToSuccess, 500);

  // Add pulse effect to registration number
  const regNumber = container.querySelector('.ppdb-reg-value');
  if (regNumber) {
    setTimeout(() => {
      regNumber.style.animation = 'ppdb-pulse 2s ease-in-out infinite';
    }, 1500);
  }

  // Add sparkle effect on important elements
  const addSparkleEffect = (element) => {
    const sparkle = document.createElement('div');
    sparkle.className = 'ppdb-sparkle';
    sparkle.style.cssText = `
            position: absolute;
            width: 4px;
            height: 4px;
            background: #fbbf24;
            border-radius: 50%;
            pointer-events: none;
            z-index: 1000;
            animation: ppdb-sparkle-anim 1.5s ease-out forwards;
        `;

    const rect = element.getBoundingClientRect();
    sparkle.style.left = (rect.left + Math.random() * rect.width) + 'px';
    sparkle.style.top = (rect.top + Math.random() * rect.height) + 'px';

    document.body.appendChild(sparkle);

    setTimeout(() => {
      sparkle.remove();
    }, 1500);
  };

  // Add sparkles to success header periodically
  const successHeader = container.querySelector('.ppdb-success-header');
  if (successHeader) {
    setInterval(() => {
      if (Math.random() > 0.7) { // 30% chance
        addSparkleEffect(successHeader);
      }
    }, 2000);
  }

  // Add CSS animations via style injection
  const styleSheet = document.createElement('style');
  styleSheet.textContent = `
        @keyframes ppdb-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes ppdb-sparkle-anim {
            0% {
                opacity: 1;
                transform: translateY(0) scale(0);
            }
            50% {
                opacity: 1;
                transform: translateY(-20px) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(-40px) scale(0);
            }
        }
        
        .ppdb-success-header {
            animation: ppdb-glow 4s ease-in-out infinite alternate;
        }
        
        @keyframes ppdb-glow {
            0% { box-shadow: 0 20px 40px rgba(16, 185, 129, 0.3); }
            100% { box-shadow: 0 25px 50px rgba(16, 185, 129, 0.4); }
        }
    `;
  document.head.appendChild(styleSheet);

  // Copy registration number to clipboard functionality
  const regNumberElement = container.querySelector('.ppdb-reg-value');
  if (regNumberElement) {
    regNumberElement.style.cursor = 'pointer';
    regNumberElement.title = 'Klik untuk menyalin nomor registrasi';

    regNumberElement.addEventListener('click', function () {
      const text = this.textContent;

      // Modern clipboard API
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
          showCopyNotification('Nomor registrasi disalin!');
        });
      } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showCopyNotification('Nomor registrasi disalin!');
      }

      // Visual feedback
      this.style.background = '#fbbf24';
      this.style.transform = 'scale(1.1)';
      setTimeout(() => {
        this.style.background = '';
        this.style.transform = '';
      }, 300);
    });
  }

  // Show copy notification
  function showCopyNotification(message) {
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            z-index: 10000;
            animation: ppdb-slide-in 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.style.animation = 'ppdb-slide-out 0.3s ease-in forwards';
      setTimeout(() => notification.remove(), 300);
    }, 2000);
  }

  // Add slide animations for notifications
  const notificationStyles = document.createElement('style');
  notificationStyles.textContent = `
        @keyframes ppdb-slide-in {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes ppdb-slide-out {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
  document.head.appendChild(notificationStyles);
});
