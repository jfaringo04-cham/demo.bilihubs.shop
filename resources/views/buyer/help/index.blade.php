@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h2>Help Center</h2>

  <div class="row g-4 mt-2">
    <div class="col-md-4">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body text-center">
          <i class="bi bi-question-circle display-4 text-danger mb-3"></i>
          <h5>FAQs</h5>
          <p class="text-muted">Find answers to common questions about orders, payments, and deliveries.</p>
          <a href="#faq" class="btn btn-outline-primary btn-sm">View FAQs</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body text-center">
          <i class="bi bi-headset display-4 text-danger mb-3"></i>
          <h5>Contact Support</h5>
          <p class="text-muted">Need help? Our support team is here to assist you.</p>
          <a href="{{ route('buyer.support.create') }}" class="btn btn-outline-primary btn-sm">Contact Us</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 border-0 shadow-sm">
        <div class="card-body text-center">
          <i class="bi bi-truck display-4 text-danger mb-3"></i>
          <h5>Track Order</h5>
          <p class="text-muted">Check the status of your orders in real-time.</p>
          <a href="{{ route('orders.index') }}" class="btn btn-outline-primary btn-sm">Track Now</a>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-5" id="faq">
    <h3 class="mb-4">Frequently Asked Questions</h3>
    <div class="accordion" id="faqAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header" id="faq1">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
            How do I place an order?
          </button>
        </h2>
        <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Browse products, add them to your cart, and proceed to checkout. Enter your shipping address and select a payment method to complete your order.
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="faq2">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
            What payment methods are accepted?
          </button>
        </h2>
        <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            We accept Cash on Delivery (COD), GCash, and major credit cards.
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="faq3">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
            How can I track my order?
          </button>
        </h2>
        <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Go to the Orders page to see real-time updates on your order status, from processing to delivery.
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="faq4">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
            How do I request a return or refund?
          </button>
        </h2>
        <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Go to your order details page and click "Request Return" if the order is eligible. For refunds, please contact our support team.
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="faq5">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
            Can I cancel my order?
          </button>
        </h2>
        <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Orders can only be cancelled while they are in "pending" or "processing" status. Once shipped, cancellation is no longer possible.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection


