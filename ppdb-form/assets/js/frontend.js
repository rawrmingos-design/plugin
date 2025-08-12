/**
 * PPDB Form Frontend JavaScript
 * Handles client-side validation, multi-step forms, and accessibility
 */

(function ($) {
  'use strict';

  class PPDBForm {
    constructor (formElement) {
      this.form = formElement;
      this.fields = this.form.querySelectorAll('.ppdb-input');
      this.submitBtn = this.form.querySelector('.ppdb-btn');
      this.currentStep = 0;
      this.totalSteps = 1;

      this.init();
    }

    init() {
      this.setupValidation();
      this.setupAutoSave();
      this.setupAccessibility();
      this.restoreFormData();

      // Setup multi-step if enabled
      const isMultiStep = this.form.closest('.ppdb-multistep') !== null ||
        this.form.classList.contains('ppdb-multistep-form') ||
        this.form.dataset.multiStep === 'true';
      if (isMultiStep) {
        this.setupMultiStep();
      }
    }

    setupValidation() {
      // Real-time validation
      this.fields.forEach(field => {
        field.addEventListener('blur', (e) => this.validateField(e.target));
        field.addEventListener('input', (e) => this.clearFieldError(e.target));
      });

      // Form submission validation
      this.form.addEventListener('submit', (e) => {
        if (!this.validateForm()) {
          e.preventDefault();
          this.focusFirstError();
        }
      });
    }

    validateField(field) {
      const fieldGroup = field.closest('.ppdb-field-group');
      const errorElement = fieldGroup.querySelector('.ppdb-field-error');
      const fieldKey = fieldGroup.dataset.field;
      let isValid = true;
      let errorMessage = '';

      // Clear previous states
      fieldGroup.classList.remove('has-error', 'has-success');

      // Required field validation
      if (field.hasAttribute('required') && !field.value.trim()) {
        isValid = false;
        errorMessage = this.getErrorMessage('required', field);
      }
      // Pattern validation
      else if (field.pattern && field.value && !new RegExp(field.pattern).test(field.value)) {
        isValid = false;
        errorMessage = this.getErrorMessage('pattern', field, fieldKey);
      }
      // Email validation
      else if (field.type === 'email' && field.value && !this.isValidEmail(field.value)) {
        isValid = false;
        errorMessage = this.getErrorMessage('email', field);
      }
      // Custom validations
      else if (field.value) {
        const customValidation = this.customValidate(fieldKey, field.value);
        if (!customValidation.valid) {
          isValid = false;
          errorMessage = customValidation.message;
        }
      }

      // Update UI
      if (isValid && field.value) {
        fieldGroup.classList.add('has-success');
        errorElement.textContent = '';
        field.setAttribute('aria-invalid', 'false');
      } else if (!isValid) {
        fieldGroup.classList.add('has-error');
        errorElement.textContent = errorMessage;
        field.setAttribute('aria-invalid', 'true');
      }

      return isValid;
    }

    clearFieldError(field) {
      const fieldGroup = field.closest('.ppdb-field-group');
      const errorElement = fieldGroup.querySelector('.ppdb-field-error');

      if (fieldGroup.classList.contains('has-error') && field.value.trim()) {
        fieldGroup.classList.remove('has-error');
        errorElement.textContent = '';
        field.setAttribute('aria-invalid', 'false');
      }
    }

    validateForm() {
      let isValid = true;

      this.fields.forEach(field => {
        if (!this.validateField(field)) {
          isValid = false;
        }
      });

      return isValid;
    }

    customValidate(fieldKey, value) {
      switch (fieldKey) {
        case 'nisn':
          if (!/^\d{10}$/.test(value)) {
            return { valid: false, message: 'NISN harus 10 digit angka' };
          }
          break;

        case 'nik':
        case 'no_kk':
          if (!/^\d{16}$/.test(value)) {
            return { valid: false, message: 'NIK/No.KK harus 16 digit angka' };
          }
          break;

        case 'nomor_telepon':
        case 'telepon_ayah':
        case 'telepon_ibu':
        case 'telepon_wali':
          const cleanPhone = value.replace(/\D/g, '');
          if (cleanPhone.length < 8 || cleanPhone.length > 15) {
            return { valid: false, message: 'Nomor telepon tidak valid (8-15 digit)' };
          }
          break;

        case 'tahun_lulus':
          const year = parseInt(value);
          const currentYear = new Date().getFullYear();
          if (year < 1980 || year > currentYear + 1) {
            return { valid: false, message: `Tahun lulus harus antara 1980-${currentYear + 1}` };
          }
          break;
      }

      return { valid: true, message: '' };
    }

    getErrorMessage(type, field, fieldKey = '') {
      const messages = {
        required: 'Field ini wajib diisi',
        pattern: this.getPatternMessage(fieldKey),
        email: 'Format email tidak valid'
      };

      return messages[type] || 'Input tidak valid';
    }

    getPatternMessage(fieldKey) {
      const patterns = {
        nisn: 'NISN harus 10 digit angka',
        nik: 'NIK harus 16 digit angka',
        no_kk: 'No.KK harus 16 digit angka',
        nomor_telepon: 'Format nomor telepon tidak valid',
        telepon_ayah: 'Format nomor telepon tidak valid',
        telepon_ibu: 'Format nomor telepon tidak valid',
        telepon_wali: 'Format nomor telepon tidak valid'
      };

      return patterns[fieldKey] || 'Format input tidak valid';
    }

    isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }

    focusFirstError() {
      const firstError = this.form.querySelector('.has-error .ppdb-input');
      if (firstError) {
        firstError.focus();
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    }

    setupAutoSave() {
      // Auto-save form data to localStorage
      let saveTimeout;

      this.fields.forEach(field => {
        field.addEventListener('input', () => {
          clearTimeout(saveTimeout);
          saveTimeout = setTimeout(() => {
            this.saveFormData();
          }, 1000);
        });
      });
    }

    saveFormData() {
      const formData = {};
      const formId = this.form.querySelector('[name="ppdb_form_id"]')?.value;

      if (!formId) return;

      this.fields.forEach(field => {
        if (field.name && field.value) {
          formData[field.name] = field.value;
        }
      });

      localStorage.setItem(`ppdb_form_${formId}`, JSON.stringify(formData));
    }

    restoreFormData() {
      const formId = this.form.querySelector('[name="ppdb_form_id"]')?.value;

      if (!formId) return;

      const savedData = localStorage.getItem(`ppdb_form_${formId}`);

      if (savedData) {
        try {
          const formData = JSON.parse(savedData);

          Object.keys(formData).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field && !field.value) {
              field.value = formData[fieldName];
            }
          });
        } catch (e) {
          console.warn('Failed to restore form data:', e);
        }
      }
    }

    clearSavedData() {
      const formId = this.form.querySelector('[name="ppdb_form_id"]')?.value;
      if (formId) {
        localStorage.removeItem(`ppdb_form_${formId}`);
      }
    }

    setupAccessibility() {
      // Enhance keyboard navigation
      this.form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
          e.preventDefault();
          this.focusNextField(e.target);
        }
      });

      // Add aria-live region for form status
      if (!document.getElementById('ppdb-status')) {
        const statusRegion = document.createElement('div');
        statusRegion.id = 'ppdb-status';
        statusRegion.setAttribute('aria-live', 'polite');
        statusRegion.setAttribute('aria-atomic', 'true');
        statusRegion.style.position = 'absolute';
        statusRegion.style.left = '-9999px';
        document.body.appendChild(statusRegion);
      }
    }

    focusNextField(currentField) {
      const fieldsArray = Array.from(this.fields);
      const currentIndex = fieldsArray.indexOf(currentField);
      const nextField = fieldsArray[currentIndex + 1];

      if (nextField) {
        nextField.focus();
      } else {
        this.submitBtn.focus();
      }
    }

    setupMultiStep() {
      this.currentStep = 1;
      this.container = this.form.closest('.ppdb-multistep');
      this.totalSteps = this.container ? parseInt(this.container.dataset.totalSteps) || 1 : 1;
      this.stepContents = this.form.querySelectorAll('.ppdb-step-content');
      this.stepIndicators = this.container ? this.container.querySelectorAll('.ppdb-step') : [];
      this.progressBar = this.container ? this.container.querySelector('.ppdb-progress-bar') : null;
      this.prevBtn = this.form.querySelector('.ppdb-btn-prev');
      this.nextBtn = this.form.querySelector('.ppdb-btn-next');
      this.submitBtn = this.form.querySelector('.ppdb-btn-submit');
      this.recaptchaContainer = this.form.querySelector('.ppdb-recaptcha-container');

      // Debug logging
      console.log('Multi-step setup:', {
        container: this.container,
        totalSteps: this.totalSteps,
        stepContents: this.stepContents.length,
        stepIndicators: this.stepIndicators.length,
        prevBtn: !!this.prevBtn,
        nextBtn: !!this.nextBtn,
        submitBtn: !!this.submitBtn
      });

      // Setup navigation event listeners
      if (this.nextBtn) {
        this.nextBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.nextStep();
        });
      }

      if (this.prevBtn) {
        this.prevBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.prevStep();
        });
      }

      // Update step indicator
      this.updateStepDisplay();
    }

    nextStep() {
      // Validate current step before proceeding
      if (!this.validateCurrentStep()) {
        this.focusFirstError();
        return;
      }

      if (this.currentStep < this.totalSteps) {
        this.currentStep++;
        this.updateStepDisplay();
        this.saveCurrentStepData();
        this.announceStatus(`Langkah ${this.currentStep} dari ${this.totalSteps}`);
      }
    }

    prevStep() {
      if (this.currentStep > 1) {
        this.currentStep--;
        this.updateStepDisplay();
        this.announceStatus(`Langkah ${this.currentStep} dari ${this.totalSteps}`);
      }
    }

    updateStepDisplay() {
      // Hide all step contents
      this.stepContents.forEach((content, index) => {
        const stepNumber = index + 1;
        if (stepNumber === this.currentStep) {
          content.style.display = 'block';
        } else {
          content.style.display = 'none';
        }
      });

      // Update step indicators
      this.stepIndicators.forEach((indicator, index) => {
        const stepNumber = index + 1;
        indicator.classList.remove('active', 'completed');

        if (stepNumber === this.currentStep) {
          indicator.classList.add('active');
        } else if (stepNumber < this.currentStep) {
          indicator.classList.add('completed');
        }
      });

      // Update progress bar
      if (this.progressBar) {
        const progress = (this.currentStep / this.totalSteps) * 100;
        this.progressBar.style.width = progress + '%';
      }

      // Update navigation buttons
      if (this.prevBtn) {
        this.prevBtn.style.display = this.currentStep > 1 ? 'inline-block' : 'none';
      }

      if (this.nextBtn && this.submitBtn) {
        if (this.currentStep === this.totalSteps) {
          this.nextBtn.style.display = 'none';
          this.submitBtn.style.display = 'inline-block';

          // Show reCAPTCHA on last step
          if (this.recaptchaContainer) {
            this.recaptchaContainer.style.display = 'block';
          }
        } else {
          this.nextBtn.style.display = 'inline-block';
          this.submitBtn.style.display = 'none';

          // Hide reCAPTCHA on other steps
          if (this.recaptchaContainer) {
            this.recaptchaContainer.style.display = 'none';
          }
        }
      }

      // Update hidden field for current step
      const currentStepInput = this.form.querySelector('[name="ppdb_current_step"]');
      if (currentStepInput) {
        currentStepInput.value = this.currentStep;
      }

      // Scroll to top of form
      this.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    validateCurrentStep() {
      const currentStepContent = this.form.querySelector(`[data-step="${this.currentStep}"]`);
      if (!currentStepContent) return true;

      const fieldsInStep = currentStepContent.querySelectorAll('.ppdb-input');
      let isValid = true;

      fieldsInStep.forEach(field => {
        if (!this.validateField(field)) {
          isValid = false;
        }
      });

      return isValid;
    }

    saveCurrentStepData() {
      // Save current step data to localStorage
      const formId = this.form.querySelector('[name="ppdb_form_id"]')?.value;
      if (!formId) return;

      const currentStepContent = this.form.querySelector(`[data-step="${this.currentStep - 1}"]`);
      if (!currentStepContent) return;

      const stepData = {};
      const fieldsInStep = currentStepContent.querySelectorAll('.ppdb-input');

      fieldsInStep.forEach(field => {
        if (field.name && field.value) {
          stepData[field.name] = field.value;
        }
      });

      const allData = JSON.parse(localStorage.getItem(`ppdb_form_${formId}`) || '{}');
      Object.assign(allData, stepData);
      localStorage.setItem(`ppdb_form_${formId}`, JSON.stringify(allData));
    }

    announceStatus(message) {
      const statusRegion = document.getElementById('ppdb-status');
      if (statusRegion) {
        statusRegion.textContent = message;
      }
    }
  }

  // Initialize forms when DOM is ready
  $(document).ready(function () {
    const forms = document.querySelectorAll('.ppdb-form form');

    forms.forEach(form => {
      const formInstance = new PPDBForm(form);
      // Store instance on form element for later access
      form.ppdbFormInstance = formInstance;
    });

    // Apply brand colors from localized settings
    if (typeof window.ppdbForm !== 'undefined' && ppdbForm.btnColors) {
      const { start, end } = ppdbForm.btnColors;
      document.querySelectorAll('.ppdb-form').forEach(el => {
        el.style.setProperty('--ppdb-btn-bg-start', start);
        el.style.setProperty('--ppdb-btn-bg-end', end);
      });
    }

    // Clear saved data on successful submission
    $('form').on('submit', function () {
      const formInstance = this.ppdbFormInstance;
      if (formInstance) {
        setTimeout(() => {
          formInstance.clearSavedData();
        }, 1000);
      }
    });
  });

  // Phone number formatting
  $(document).on('input', 'input[type="tel"]', function () {
    let value = this.value.replace(/\D/g, '');

    // Indonesian phone number formatting
    if (value.startsWith('62')) {
      value = '+' + value;
    } else if (value.startsWith('08')) {
      // Keep as is for local format
    } else if (value.startsWith('8') && value.length > 8) {
      value = '0' + value;
    }

    this.value = value;
  });

  // Prevent form submission on Enter key in text inputs (except textarea)
  $(document).on('keypress', '.ppdb-form input:not([type="submit"])', function (e) {
    if (e.which === 13) {
      e.preventDefault();
      $(this).blur().next().focus();
    }
  });

})(jQuery);