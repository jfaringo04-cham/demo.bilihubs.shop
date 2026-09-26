import 'package:flutter/material.dart';

class BottomNavItem {
  final IconData icon;

  final String label;

  const BottomNavItem({required this.icon, required this.label});
}

class BottomNav extends StatelessWidget {
  final int currentIndex;

  final List<BottomNavItem> items;

  final ValueChanged<int> onTap;

  const BottomNav({
    super.key,

    required this.currentIndex,

    required this.items,

    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,

        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),

            blurRadius: 20,

            offset: const Offset(0, -5),
          ),
        ],
      ),

      child: SafeArea(
        child: Padding(
          padding: EdgeInsets.symmetric(horizontal: width * 0.02, vertical: 8),

          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,

            children: List.generate(items.length, (index) {
              final item = items[index];

              final selected = currentIndex == index;

              return Expanded(
                child: GestureDetector(
                  onTap: () {
                    onTap(index);
                  },

                  behavior: HitTestBehavior.opaque,

                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),

                    padding: const EdgeInsets.symmetric(vertical: 8),

                    decoration: BoxDecoration(
                      color: selected
                          ? Colors.blue.withValues(alpha: .1)
                          : Colors.transparent,

                      borderRadius: BorderRadius.circular(20),
                    ),

                    child: Column(
                      mainAxisSize: MainAxisSize.min,

                      children: [
                        Icon(
                          item.icon,

                          size: 24,

                          color: selected ? Colors.blue : Colors.grey,
                        ),

                        const SizedBox(height: 3),

                        Text(
                          item.label,

                          maxLines: 1,

                          overflow: TextOverflow.ellipsis,

                          style: TextStyle(
                            fontSize: 11,

                            fontWeight: selected
                                ? FontWeight.w600
                                : FontWeight.normal,

                            color: selected ? Colors.blue : Colors.grey,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }),
          ),
        ),
      ),
    );
  }
}
