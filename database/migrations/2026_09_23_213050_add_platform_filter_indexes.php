<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | role_user
        |--------------------------------------------------------------------------
        */
        Schema::table('role_user', function (Blueprint $table) {
            $table->index('user_id', 'role_user_user_id_index');
        });

        /*
        |--------------------------------------------------------------------------
        | addresses
        |--------------------------------------------------------------------------
        */
        Schema::table('addresses', function (Blueprint $table) {
            $table->index('user_id', 'addresses_user_id_index');
        });

        /*
        |--------------------------------------------------------------------------
        | products
        |--------------------------------------------------------------------------
        */
        Schema::table('products', function (Blueprint $table) {
            $table->index('user_id', 'products_user_id_index');
            $table->index('seller_id', 'products_seller_id_index');
            $table->index('category_id', 'products_category_id_index');
            $table->index('subcategory_id', 'products_subcategory_id_index');
            $table->index('status', 'products_status_index');
            $table->index('compliance_status', 'products_compliance_status_index');
        });

        /*
        |--------------------------------------------------------------------------
        | product_variants
        |--------------------------------------------------------------------------
        */
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('product_id', 'product_variants_product_id_index');
        });

        /*
        |--------------------------------------------------------------------------
        | product_images
        |--------------------------------------------------------------------------
        */
        Schema::table('product_images', function (Blueprint $table) {
            $table->index('product_id', 'product_images_product_id_index');
        });
/*
        |--------------------------------------------------------------------------
        | cart_items
        |--------------------------------------------------------------------------
        */
        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('cart_id', 'cart_items_cart_id_index');
            $table->index('product_id', 'cart_items_product_id_index');
        });

        /*
        |--------------------------------------------------------------------------
        | orders
        |--------------------------------------------------------------------------
        */
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id', 'orders_user_id_index');
            $table->index('status', 'orders_status_index');
            $table->index('rider_id', 'orders_rider_id_index');
            $table->index('delivery_status', 'orders_delivery_status_index');
            $table->index('payment_status', 'orders_payment_status_index');
        });

        /*
        |--------------------------------------------------------------------------
        | seller_orders
        |--------------------------------------------------------------------------
        */
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->index('order_id', 'seller_orders_order_id_index');
            $table->index('seller_id', 'seller_orders_seller_id_index');
            $table->index('logistic_id', 'seller_orders_logistic_id_index');
            $table->index('status', 'seller_orders_status_index');
        });

        /*
        |--------------------------------------------------------------------------
        | order_items
        |--------------------------------------------------------------------------
        */
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('seller_order_id', 'order_items_seller_order_id_index');
            $table->index('product_id', 'order_items_product_id_index');
            $table->index('variant_id', 'order_items_variant_id_index');
            $table->index('size_id', 'order_items_size_id_index');
        });

        /*
        |--------------------------------------------------------------------------
        | payments
        |--------------------------------------------------------------------------
        */
        Schema::table('payments', function (Blueprint $table) {
            $table->index('method', 'payments_method_index');
        });

        /*
        |--------------------------------------------------------------------------
        | shipments
        |--------------------------------------------------------------------------
        */
        Schema::table('shipments', function (Blueprint $table) {
            $table->index('seller_order_id', 'shipments_seller_order_id_index');
            $table->index('hub_id', 'shipments_hub_id_index');
            $table->index('rider_profile_id', 'shipments_rider_profile_id_index');
            $table->index('status', 'shipments_status_index');
            $table->index('sorting_status', 'shipments_sorting_status_index');
            $table->index('delivery_zone', 'shipments_delivery_zone_index');
        });

        /*
        |--------------------------------------------------------------------------
        | delivery_events
        |--------------------------------------------------------------------------
        */
        // Already has:
        // shipment_id + occurred_at
        // status
        //
        // No additional index is necessary here.
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropIndex('role_user_user_id_index');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_user_id_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_user_id_index');
            $table->dropIndex('products_seller_id_index');
            $table->dropIndex('products_category_id_index');
            $table->dropIndex('products_subcategory_id_index');
            $table->dropIndex('products_status_index');
            $table->dropIndex('products_compliance_status_index');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_product_id_index');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('product_images_product_id_index');
        });


        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_cart_id_index');
            $table->dropIndex('cart_items_product_id_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_id_index');
            $table->dropIndex('orders_status_index');
            $table->dropIndex('orders_rider_id_index');
            $table->dropIndex('orders_delivery_status_index');
            $table->dropIndex('orders_payment_status_index');
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropIndex('seller_orders_order_id_index');
            $table->dropIndex('seller_orders_seller_id_index');
            $table->dropIndex('seller_orders_logistic_id_index');
            $table->dropIndex('seller_orders_status_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_seller_order_id_index');
            $table->dropIndex('order_items_product_id_index');
            $table->dropIndex('order_items_variant_id_index');
            $table->dropIndex('order_items_size_id_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_method_index');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('shipments_seller_order_id_index');
            $table->dropIndex('shipments_hub_id_index');
            $table->dropIndex('shipments_rider_profile_id_index');
            $table->dropIndex('shipments_status_index');
            $table->dropIndex('shipments_sorting_status_index');
            $table->dropIndex('shipments_delivery_zone_index');
        });
    }
};