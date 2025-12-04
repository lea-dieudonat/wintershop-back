document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('checkout-form');
    const shippingInput = document.getElementById('shipping_address_id');
    const billingInput = document.getElementById('billing_address_id');
    // IDs in the template use dashes; use a tolerant lookup and fallbacks
    const sameAsShippingCheckbox = document.getElementById('same-as-shipping') || document.getElementById('same_as_shipping');
    const confirmButton = document.getElementById('confirm-order-btn') || form.querySelector('button[type="submit"]');
    const billingSection = document.getElementById('billing-addresses');

    const defaultShipping = document.querySelector('input[name="shipping_address"]:checked');
    const defaultBilling = document.querySelector('input[name="billing_address"]:checked');
    
    if (defaultShipping) {
        shippingInput.value = defaultShipping.value;
    }
    
    if (defaultBilling) {
        billingInput.value = defaultBilling.value;
    }

    // Guard validateForm so missing confirmButton doesn't break the script
    try {
        validateForm();
    } catch (err) {
        // If validation fails (missing elements), log for easier debugging but continue
        // console.debug('validateForm error', err);
    }
    
    // Handle address card clicks
    document.querySelectorAll('.address-card').forEach(card => {
        card.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
            updateHiddenInputs();
        });
    });

    // Handle "same as shipping" checkbox (only if present)
    if (sameAsShippingCheckbox) {
        sameAsShippingCheckbox.addEventListener('change', function() {
        if (this.checked) {
            billingSection.style.opacity = '0.5';
            billingSection.style.pointerEvents = 'none';
            // Copy shipping address to billing
            const shippingId = shippingInput.value;
            if (shippingId) {
                billingInput.value = shippingId;
                document.querySelectorAll('input[name="billing_address"]').forEach(radio => {
                    radio.checked = (radio.value === shippingId);
                });
            }
        } else {
            billingSection.style.opacity = '1';
            billingSection.style.pointerEvents = 'auto';
            billingInput.value = '';
            document.querySelectorAll('input[name="billing_address"]').forEach(radio => {
                radio.checked = false;
            });
        }
            validateForm();
        });
    }

    // Update hidden inputs when radio buttons change
    // Attach listeners only if those inputs exist
    const shippingRadios = document.querySelectorAll('input[name="shipping_address"]');
    if (shippingRadios.length) {
        shippingRadios.forEach(radio => radio.addEventListener('change', updateHiddenInputs));
    }

    const billingRadios = document.querySelectorAll('input[name="billing_address"]');
    if (billingRadios.length) {
        billingRadios.forEach(radio => radio.addEventListener('change', updateHiddenInputs));
    }

    function updateHiddenInputs() {
        const selectedShipping = document.querySelector('input[name="shipping_address"]:checked');
        const selectedBilling = document.querySelector('input[name="billing_address"]:checked');

        if (selectedShipping) {
            shippingInput.value = selectedShipping.value;
        }

        if (selectedBilling) {
            billingInput.value = selectedBilling.value;
        }

        // If "same as shipping" is checked, sync billing
        if (sameAsShippingCheckbox.checked && selectedShipping) {
            billingInput.value = selectedShipping.value;
        }

        validateForm();
    }

    function validateForm() {
        const hasShipping = shippingInput.value !== '';
        const hasBilling = billingInput.value !== '';
        if (confirmButton) {
            confirmButton.disabled = !(hasShipping && hasBilling);
        }
    }

    // CSRF token handling
    form.addEventListener('submit', function(e) {
        if (!shippingInput.value || !billingInput.value) {
            e.preventDefault();
            alert('Veuillez sélectionner une adresse de livraison et une adresse de facturation.');
        }
    });
});