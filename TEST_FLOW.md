# Complete Order Flow Test Guide

## Prerequisites
- Have test accounts for each role:
  - Buyer account
  - Seller account (with products in stock)
  - Rider account(s) under same logistics company
  - Admin/Logistics owner account

---

## Step 1: Buyer Places Order

### Action:
1. Login as **Buyer**
2. Browse products, add to cart
3. Go to checkout (`/checkout`)
4. Fill in shipping address
5. Select payment method (COD recommended for testing)
6. Place order

### Expected Result:
- Order created with status: **`placed`**
- Buyer sees order in `/orders`
- Buyer receives confirmation
- Notification sent to seller

---

## Step 2: Seller Sees Order

### Action:
1. Login as **Seller**
2. Go to `/seller/dashboard` or `/seller/orders`

### Expected Result:
- Order appears in list with status: **`placed`**
- Seller sees: buyer name, items, total, shipping address

---

## Step 3: Seller Accepts Order

### Action:
1. Click on order to view details
2. Click **"Accept & Start Processing"** button

### Expected Result:
- Order status changes to: **`confirmed`**
- Seller can now prepare the product

---

## Step 4: Seller Prepares Order

### Action:
1. Seller changes status to **`preparing`** (optional step, can skip to ready_for_pickup)

### Expected Result:
- Order status: **`preparing`**

---

## Step 5: Seller Marks Ready for Pickup

### Action:
1. Click **"Mark as Ready for Pickup"** button
2. Confirm the action

### Expected Result:
- Order status changes to: **`ready_for_pickup`**
- Stock is deducted from product
- Shipment is created automatically
- Shipment status: **`pending`**
- Notification sent to:
  - Logistics owner (new shipment assignment)
  - All available riders of that logistics company (new pickup available)

---

## Step 6: Rider Sees Pickup Assignment

### Action:
1. Login as **Rider** (must be under same logistics company as seller's preferred)
2. Go to `/rider/pickups`

### Expected Result:
- Order appears in pickup list
- Rider sees: seller address, buyer address, order details
- Button: **"Accept"** and **"Scan QR"**

---

## Step 7: Rider Accepts Pickup

### Action:
1. Click **"Accept"** button

### Expected Result:
- Order is assigned to rider
- Order status: **`assigned_to_rider`**
- Rider's current_load increases by 1

---

## Step 8: Rider Goes to Seller & Scans QR

### Action:
1. Rider goes to seller location (or simulate by clicking Scan QR)
2. Click **"Scan QR"** button
3. Enter QR token (or use tracking number)
4. Confirm scan

### Expected Result:
- Order status changes to: **`picked_up`**
- Shipment status changes to: **`in_transit`**
- Rider can now see **"Deliver to Sorting Center"** button
- Notification sent to buyer: "Rider picked up your parcel, on the way to hub"

---

## Step 9: Rider Delivers to Sorting Center

### Action:
1. Rider goes to sorting center (or click "Deliver to Sorting Center")
2. Add optional notes
3. Confirm delivery to sorting center

### Expected Result:
- Order status changes to: **`at_sorting_center`**
- Shipment status changes to: **`at_sorting_center`**
- Rider's current_load decreases by 1 (parcel is now at hub)

---

## Step 10: Logistics Scans at Hub

### Action:
1. Login as **Logistics Owner/Admin**
2. Go to `/logistic/sortings` or `/logistic/shipments`
3. Find the shipment
4. Click **"Scan Parcel"**
5. Enter delivery zone (e.g., "Zone A")
6. Select delivery type (standard/same_day/cod)
7. Confirm scan

### Expected Result:
- Shipment status changes to: **`sorted`**
- Order status changes to: **`sorted`**
- System automatically assigns a new rider based on:
  - Priority: Rider with matching `assigned_zone` to `delivery_zone`
  - Fallback: First available rider in same logistics company
- New rider receives notification: "New Delivery Assigned from Hub"
- Buyer receives notification: "Order sorted at hub, ready for final delivery"

---

## Step 11: New Rider Picks Up from Sorting Center

### Action:
1. Login as **New Rider** (assigned in previous step)
2. Go to `/rider/pickups?status=sorting_center`
3. See order in "Pickups from Sorting Center" list
4. Click **"Pick for Delivery"** button

### Expected Result:
- Order status changes to: **`assigned_to_rider`** (or `ready_for_delivery_pickup`)
- Shipment status changes to: **`staged`** or **`picked_up`**
- New rider's current_load increases by 1

---

## Step 12: Rider Delivers to Buyer

### Action:
1. New rider goes to buyer address
2. In rider dashboard, change status to **"Out for Delivery"**
3. Deliver parcel
4. Collect payment (if COD)
5. Submit proof of delivery (photo/signature/OTP)
6. Mark as **"Delivered"**

### Expected Result:
- Order status changes to: **`delivered`**
- Shipment status changes to: **`delivered`**
- Buyer receives notification: "Your order has been delivered"
- Rider's current_load decreases by 1

---

## Step 13: Buyer Confirms Receipt

### Action:
1. Login as **Buyer**
2. Go to `/orders`
3. View delivered order
4. Click **"Confirm Received"** button
5. Confirm the action

### Expected Result:
- Order status changes to: **`completed`**
- Transaction is marked as complete
- Seller receives notification: "Order completed by the buyer"
- Order is now in completed state

---

## Complete Status Flow Summary

```
Buyer: PLACED
  ↓
Seller: CONFIRMED
  ↓
Seller: PREPARING (optional)
  ↓
Seller: READY_FOR_PICKUP
  ↓
Rider 1: PICKED_UP
  ↓
Rider 1: AT_SORTING_CENTER (delivered to hub)
  ↓
Logistics: SORTED (auto-assigns Rider 2)
  ↓
Rider 2: ASSIGNED_TO_RIDER
  ↓
Rider 2: OUT_FOR_DELIVERY
  ↓
Rider 2: DELIVERED
  ↓
Buyer: COMPLETED (after confirmation)
```

---

## Notifications Received at Each Step

1. **Buyer places order** → Seller notified
2. **Seller accepts** → Buyer notified
3. **Seller ready for pickup** → Logistics owner + Riders notified
4. **Rider scans QR** → Buyer notified ("picked up")
5. **Rider delivers to hub** → Buyer notified ("at sorting center")
6. **Logistics sorts** → New Rider + Buyer notified ("sorted at hub")
7. **Rider delivers** → Buyer notified ("delivered")
8. **Buyer confirms** → Seller notified ("order completed")

---

## Troubleshooting

### If rider doesn't see pickup:
- Check rider is under same logistics company as seller's preferred
- Check rider `availability_status` = `available`
- Check rider `current_load` < `max_capacity`

### If no rider assigned after hub scan:
- Check if there are available riders in the logistics company
- Check `assigned_zone` matches `delivery_zone`
- System falls back to first available rider if no zone match

### If status colors look wrong:
- Clear view cache: `php artisan view:clear`
- Check `Order::statusBadgeClass()` method

---

## Test Checklist

- [ ] Buyer can place order
- [ ] Seller sees order immediately
- [ ] Seller can accept order
- [ ] Seller can mark as ready for pickup
- [ ] Shipment is created automatically
- [ ] Riders receive notification
- [ ] Rider can accept pickup
- [ ] Rider can scan QR
- [ ] Rider can deliver to sorting center
- [ ] Logistics can scan at hub
- [ ] New rider is assigned automatically
- [ ] New rider can pick up from sorting center
- [ ] New rider can deliver to buyer
- [ ] Buyer can confirm receipt
- [ ] All notifications are sent correctly
- [ ] Status badges show correct colors throughout
