<div class="payment-method-item">
    <input 
        type="radio" 
        name="payment[method]" 
        value="mercadopago" 
        id="mercadopago"
        @checked(old('payment.method') === 'mercadopago')
    >
    
    <label for="mercadopago" class="radio-label">
        {{ core()->getConfigData('sales.paymentmethods.mercadopago.title') }}
    </label>
    
    <p class="description">
        {{ core()->getConfigData('sales.paymentmethods.mercadopago.description') }}
    </p>
</div>

@pushOnce('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mpRadio = document.getElementById('mercadopago');
    const paymentForm = document.querySelector('form#payment-form');
    
    if (mpRadio && paymentForm) {
        mpRadio.addEventListener('change', () => {
            if (mpRadio.checked) {
                paymentForm.setAttribute('action', '{{ route('mercadopago.redirect') }}');
            }
        });
    }
});
</script>
@endPushOnce