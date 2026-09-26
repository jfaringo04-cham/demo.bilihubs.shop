import 'package:flutter/material.dart';

import '../../styles/buyer_style.dart';

class OrderTracking extends StatelessWidget {
  const OrderTracking({super.key});

  final List<Map<String, dynamic>> steps = const [
    {
      "title": "Order Placed",

      "subtitle": "Your order has been confirmed",

      "done": true,
    },

    {
      "title": "Preparing",

      "subtitle": "Seller is preparing your item",

      "done": true,
    },

    {"title": "Shipped", "subtitle": "Package is on the way", "done": true},

    {
      "title": "Out for Delivery",

      "subtitle": "Rider is delivering your package",

      "done": false,
    },

    {"title": "Delivered", "subtitle": "Package received", "done": false},
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerColors.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        title: const Text("Order Tracking", style: BuyerStyle.title),
      ),

      body: ListView.builder(
        padding: const EdgeInsets.all(20),

        itemCount: steps.length,

        itemBuilder: (context, index) {
          final step = steps[index];

          return Row(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Column(
                children: [
                  Container(
                    height: 35,

                    width: 35,

                    decoration: BoxDecoration(
                      shape: BoxShape.circle,

                      color: step["done"] ? Colors.green : Colors.grey,
                    ),

                    child: Icon(
                      step["done"] ? Icons.check : Icons.circle_outlined,

                      color: Colors.white,

                      size: 20,
                    ),
                  ),

                  if (index != steps.length - 1)
                    Container(
                      height: 60,

                      width: 2,

                      color: Colors.grey.shade300,
                    ),
                ],
              ),

              const SizedBox(width: 15),

              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(16),

                  margin: const EdgeInsets.only(bottom: 20),

                  decoration: BuyerStyle.card,

                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      Text(step["title"], style: BuyerStyle.cardTitle),

                      const SizedBox(height: 5),

                      Text(step["subtitle"], style: BuyerStyle.small),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
