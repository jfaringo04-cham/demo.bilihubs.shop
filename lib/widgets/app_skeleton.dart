import 'package:flutter/material.dart';

enum AppSkeletonType { product, order, parcel, dashboard }

class AppSkeleton extends StatefulWidget {
  final AppSkeletonType type;
  final int itemCount;
  final Color? color;
  final bool compact;
  final EdgeInsetsGeometry? padding;

  const AppSkeleton({
    super.key,
    required this.type,
    this.itemCount = 4,
    this.color,
    this.compact = false,
    this.padding,
  }) : assert(itemCount >= 0);

  @override
  State<AppSkeleton> createState() => _AppSkeletonState();
}

class _AppSkeletonState extends State<AppSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _opacity;

  @override
  void initState() {
    super.initState();

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..repeat(reverse: true);

    _opacity = Tween<double>(
      begin: 0.45,
      end: 1.0,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeInOut));
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Color get _placeholderColor {
    final ThemeData theme = Theme.of(context);

    if (widget.color != null) {
      return Color.alphaBlend(
        widget.color!.withValues(alpha: 0.12),
        theme.colorScheme.surfaceContainerHighest,
      );
    }

    return theme.colorScheme.surfaceContainerHighest;
  }

  Color get _cardColor {
    return Theme.of(context).colorScheme.surface;
  }

  Color get _borderColor {
    return Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.45);
  }

  @override
  Widget build(BuildContext context) {
    if (widget.itemCount == 0) {
      return const SizedBox.shrink();
    }

    return Semantics(
      label: 'Loading content',
      child: ExcludeSemantics(
        child: AnimatedBuilder(
          animation: _opacity,
          builder: (BuildContext context, Widget? child) {
            return Opacity(opacity: _opacity.value, child: child);
          },
          child: Padding(
            padding:
                widget.padding ??
                EdgeInsets.symmetric(
                  horizontal: widget.compact ? 0 : 2,
                  vertical: widget.compact ? 4 : 8,
                ),
            child: _buildSkeleton(),
          ),
        ),
      ),
    );
  }

  Widget _buildSkeleton() {
    switch (widget.type) {
      case AppSkeletonType.product:
        return _productGrid();

      case AppSkeletonType.order:
        return _verticalList(_orderCard);

      case AppSkeletonType.parcel:
        return _verticalList(_parcelCard);

      case AppSkeletonType.dashboard:
        return _dashboardGrid();
    }
  }

  // ============================================================
  // SHARED PLACEHOLDER
  // ============================================================

  Widget _block({double? width, double height = 14, double radius = 6}) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: _placeholderColor,
        borderRadius: BorderRadius.circular(radius),
      ),
    );
  }

  Widget _line({double fraction = 1, double height = 12}) {
    return FractionallySizedBox(
      alignment: Alignment.centerLeft,
      widthFactor: fraction,
      child: _block(height: height),
    );
  }

  Widget _card({required Widget child, EdgeInsetsGeometry? padding}) {
    return Container(
      padding: padding ?? const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _borderColor),
      ),
      child: child,
    );
  }

  Widget _verticalList(Widget Function() cardBuilder) {
    return Column(
      children: List<Widget>.generate(
        widget.itemCount,
        (int index) => Padding(
          padding: EdgeInsets.only(
            bottom: index == widget.itemCount - 1 ? 0 : 12,
          ),
          child: cardBuilder(),
        ),
      ),
    );
  }

  // ============================================================
  // PRODUCT SKELETON
  // ============================================================

  Widget _productGrid() {
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final double width = constraints.maxWidth;

        final int columns = width < 300 ? 1 : 2;

        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: widget.itemCount,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: columns == 1 ? 1.15 : 0.73,
          ),
          itemBuilder: (BuildContext context, int index) {
            return _card(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    flex: 5,
                    child: Container(
                      width: double.infinity,
                      decoration: BoxDecoration(
                        color: _placeholderColor,
                        borderRadius: BorderRadius.circular(11),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _line(fraction: 0.95, height: 12),
                  const SizedBox(height: 7),
                  _line(fraction: 0.65, height: 11),
                  const SizedBox(height: 11),
                  _line(fraction: 0.45, height: 17),
                  const SizedBox(height: 9),
                  _line(fraction: 0.75, height: 10),
                ],
              ),
            );
          },
        );
      },
    );
  }

  // ============================================================
  // ORDER SKELETON
  // ============================================================

  Widget _orderCard() {
    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: _block(height: 16)),
              const SizedBox(width: 28),
              _block(width: 76, height: 26, radius: 13),
            ],
          ),
          const SizedBox(height: 15),
          const Divider(height: 1),
          const SizedBox(height: 15),
          Row(
            children: [
              _block(width: 68, height: 68, radius: 11),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _line(fraction: 0.95),
                    const SizedBox(height: 9),
                    _line(fraction: 0.65),
                    const SizedBox(height: 9),
                    _line(fraction: 0.40, height: 14),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 17),
          Row(
            children: [
              Expanded(child: _block(height: 12)),
              const SizedBox(width: 45),
              _block(width: 85, height: 18),
            ],
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PARCEL SKELETON
  // ============================================================

  Widget _parcelCard() {
    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _block(width: 42, height: 42, radius: 12),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _line(fraction: 0.85, height: 15),
                    const SizedBox(height: 8),
                    _line(fraction: 0.50),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              _block(width: 65, height: 24, radius: 12),
            ],
          ),
          const SizedBox(height: 16),
          _line(fraction: 0.90),
          const SizedBox(height: 8),
          _line(fraction: 0.70),
          const SizedBox(height: 8),
          _line(fraction: 0.95),
          const SizedBox(height: 16),
          _block(width: double.infinity, height: 39, radius: 10),
        ],
      ),
    );
  }

  // ============================================================
  // DASHBOARD SKELETON
  // ============================================================

  Widget _dashboardGrid() {
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final int columns = constraints.maxWidth < 300 ? 1 : 2;

        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: widget.itemCount,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: columns == 1 ? 2.2 : 1.20,
          ),
          itemBuilder: (BuildContext context, int index) {
            return _card(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _block(width: 39, height: 39, radius: 11),
                  const Spacer(),
                  _line(fraction: 0.65, height: 22),
                  const SizedBox(height: 8),
                  _line(fraction: 0.85, height: 11),
                ],
              ),
            );
          },
        );
      },
    );
  }
}
