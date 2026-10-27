import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';

class RiderMessages extends StatefulWidget {
  final List<Map<String, dynamic>> conversations;

  const RiderMessages({
    super.key,
    this.conversations = const <Map<String, dynamic>>[],
  });

  @override
  State<RiderMessages> createState() => _RiderMessagesState();
}

class _RiderMessagesState extends State<RiderMessages> {
  final TextEditingController _searchController = TextEditingController();

  String _searchQuery = '';

  bool _isLoading = false;

  String? _errorMessage;

  List<Map<String, dynamic>> _conversations = <Map<String, dynamic>>[];

  @override
  void initState() {
    super.initState();

    _conversations = widget.conversations
        .map((Map<String, dynamic> item) => _normalizeConversation(item))
        .toList();
  }

  @override
  void didUpdateWidget(covariant RiderMessages oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (oldWidget.conversations != widget.conversations) {
      setState(() {
        _conversations = widget.conversations
            .map((Map<String, dynamic> item) => _normalizeConversation(item))
            .toList();
      });
    }
  }

  @override
  void dispose() {
    _searchController.dispose();

    super.dispose();
  }

  // ============================================================
  // NORMALIZE CONVERSATION
  // ============================================================

  Map<String, dynamic> _normalizeConversation(Map<String, dynamic> raw) {
    final Map<String, dynamic> map = Map<String, dynamic>.from(raw);

    final dynamic user = raw['user'];

    if (user is Map) {
      final Map<String, dynamic> userMap = Map<String, dynamic>.from(user);

      map['user_name'] ??= userMap['name'] ?? userMap['full_name'];

      map['role'] ??= userMap['role'];
    }

    final dynamic buyer = raw['buyer'];

    if (buyer is Map) {
      final Map<String, dynamic> buyerMap = Map<String, dynamic>.from(buyer);

      map['user_name'] ??= buyerMap['name'] ?? buyerMap['full_name'];

      map['role'] ??= 'Buyer';
    }

    final dynamic shipment = raw['shipment'];

    if (shipment is Map) {
      final Map<String, dynamic> shipmentMap = Map<String, dynamic>.from(
        shipment,
      );

      map['tracking_number'] ??=
          shipmentMap['tracking_number'] ?? shipmentMap['tracking_code'];
    }

    final dynamic order = raw['order'];

    if (order is Map) {
      final Map<String, dynamic> orderMap = Map<String, dynamic>.from(order);

      map['tracking_number'] ??= orderMap['tracking_number'];

      map['order_number'] ??= orderMap['order_number'];
    }

    map['id'] ??= map['conversation_id'] ?? map['thread_id'] ?? 0;

    map['user_name'] ??=
        map['name'] ?? map['buyer_name'] ?? map['customer_name'] ?? 'Buyer';

    map['role'] ??= 'Buyer';

    map['last_message'] ??= map['message'] ?? map['preview'] ?? '';

    map['last_activity'] ??=
        map['updated_at'] ?? map['last_message_at'] ?? map['created_at'] ?? '';

    map['tracking_number'] ??=
        map['tracking_code'] ?? map['waybill_number'] ?? '';

    map['unread_count'] ??= map['unread'] ?? 0;

    return map;
  }

  // ============================================================
  // FILTERED CONVERSATIONS
  // ============================================================

  List<Map<String, dynamic>> get _filteredConversations {
    final String query = _searchQuery.trim().toLowerCase();

    if (query.isEmpty) {
      return _conversations;
    }

    return _conversations.where((Map<String, dynamic> conversation) {
      final String userName =
          conversation['user_name']?.toString().toLowerCase() ?? '';

      final String role = conversation['role']?.toString().toLowerCase() ?? '';

      final String lastMessage =
          conversation['last_message']?.toString().toLowerCase() ?? '';

      final String tracking =
          conversation['tracking_number']?.toString().toLowerCase() ?? '';

      return userName.contains(query) ||
          role.contains(query) ||
          lastMessage.contains(query) ||
          tracking.contains(query);
    }).toList();
  }

  // ============================================================
  // REFRESH
  // ============================================================

  Future<void> _refreshMessages() async {
    if (_isLoading) {
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    await Future<void>.delayed(const Duration(milliseconds: 250));

    if (!mounted) return;

    setState(() {
      _isLoading = false;
    });

    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        behavior: SnackBarBehavior.floating,

        content: Text(
          'Messaging is ready on the Flutter side. Live messages will appear once the Rider messaging API is connected.',
        ),
      ),
    );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final double width = MediaQuery.of(context).size.width;

    final double horizontalPadding = width < 380 ? 14 : 18;

    final List<Map<String, dynamic>> conversations = _filteredConversations;

    return Scaffold(
      backgroundColor: RiderColors.background,

      appBar: AppBar(
        backgroundColor: RiderColors.white,

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        iconTheme: const IconThemeData(color: RiderColors.textDark),

        titleSpacing: 18,

        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            Text('Messages', style: RiderStyle.title),

            SizedBox(height: 2),

            Text('Chat with buyers about deliveries', style: RiderStyle.small),
          ],
        ),

        actions: [
          if (_isLoading)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16),

              child: Center(
                child: SizedBox(
                  height: 19,
                  width: 19,

                  child: CircularProgressIndicator(
                    strokeWidth: 2,

                    color: RiderColors.primary,
                  ),
                ),
              ),
            )
          else
            IconButton(
              tooltip: 'Refresh',

              onPressed: _refreshMessages,

              icon: const Icon(
                Icons.refresh_rounded,

                color: RiderColors.primary,
              ),
            ),

          const SizedBox(width: 5),
        ],
      ),

      body: SafeArea(
        top: false,

        child: Column(
          children: [
            // ==================================================
            // SEARCH
            // ==================================================

            Container(
              width: double.infinity,

              color: RiderColors.white,

              padding: EdgeInsets.fromLTRB(
                horizontalPadding,
                14,
                horizontalPadding,
                15,
              ),

              child: TextField(
                controller: _searchController,

                onChanged: (String value) {
                  setState(() {
                    _searchQuery = value;
                  });
                },

                textInputAction: TextInputAction.search,

                decoration: InputDecoration(
                  hintText: 'Search buyer or tracking number...',

                  hintStyle: RiderStyle.small,

                  prefixIcon: const Icon(
                    Icons.search_rounded,

                    color: RiderColors.primary,

                    size: 21,
                  ),

                  suffixIcon: _searchQuery.isNotEmpty
                      ? IconButton(
                          onPressed: () {
                            _searchController.clear();

                            setState(() {
                              _searchQuery = '';
                            });
                          },

                          icon: const Icon(
                            Icons.close_rounded,

                            size: 19,

                            color: RiderColors.textGrey,
                          ),
                        )
                      : null,

                  filled: true,

                  fillColor: RiderColors.background,

                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 13,
                    vertical: 13,
                  ),

                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(13),

                    borderSide: BorderSide.none,
                  ),

                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(13),

                    borderSide: const BorderSide(color: RiderColors.border),
                  ),

                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(13),

                    borderSide: const BorderSide(
                      color: RiderColors.primary,

                      width: 1.3,
                    ),
                  ),
                ),
              ),
            ),

            // ==================================================
            // BODY
            // ==================================================
            Expanded(
              child: RefreshIndicator(
                onRefresh: _refreshMessages,

                child: _buildBody(horizontalPadding, conversations),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // BODY
  // ============================================================

  Widget _buildBody(
    double horizontalPadding,
    List<Map<String, dynamic>> conversations,
  ) {
    if (_isLoading && _conversations.isEmpty) {
      return _loadingState();
    }

    if (_errorMessage != null) {
      return _errorState();
    }

    if (_conversations.isEmpty) {
      return _emptyState();
    }

    if (conversations.isEmpty) {
      return _noSearchResults();
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),

      padding: EdgeInsets.fromLTRB(
        horizontalPadding,
        16,
        horizontalPadding,
        28,
      ),

      itemCount: conversations.length,

      itemBuilder: (BuildContext context, int index) {
        return _conversationCard(conversations[index]);
      },
    );
  }

  // ============================================================
  // CONVERSATION CARD
  // ============================================================

  Widget _conversationCard(Map<String, dynamic> conversation) {
    final String userName = _displayValue(
      conversation['user_name'],
      fallback: 'Buyer',
    );

    final String role = _displayValue(conversation['role'], fallback: 'Buyer');

    final String lastMessage = _displayValue(
      conversation['last_message'],
      fallback: 'No messages yet.',
    );

    final String lastActivity = _formatActivity(conversation['last_activity']);

    final String tracking = _displayValue(
      conversation['tracking_number'],
      fallback: '',
    );

    final int unreadCount = _toInt(conversation['unread_count']) ?? 0;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),

      child: Material(
        color: RiderColors.white,

        borderRadius: BorderRadius.circular(16),

        child: InkWell(
          onTap: () {
            _openConversation(conversation);
          },

          borderRadius: BorderRadius.circular(16),

          child: Container(
            padding: const EdgeInsets.all(15),

            decoration: RiderStyle.card,

            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Stack(
                  clipBehavior: Clip.none,

                  children: [
                    Container(
                      height: 48,
                      width: 48,

                      decoration: const BoxDecoration(
                        color: RiderColors.lightPurple,

                        shape: BoxShape.circle,
                      ),

                      alignment: Alignment.center,

                      child: const Icon(
                        Icons.person_outline_rounded,

                        color: RiderColors.primary,

                        size: 25,
                      ),
                    ),

                    if (unreadCount > 0)
                      Positioned(
                        top: -3,
                        right: -3,

                        child: Container(
                          constraints: const BoxConstraints(
                            minWidth: 18,
                            minHeight: 18,
                          ),

                          padding: const EdgeInsets.symmetric(
                            horizontal: 5,
                            vertical: 2,
                          ),

                          decoration: const BoxDecoration(
                            color: RiderColors.danger,

                            shape: BoxShape.circle,
                          ),

                          alignment: Alignment.center,

                          child: Text(
                            unreadCount > 99 ? '99+' : unreadCount.toString(),

                            style: const TextStyle(
                              color: Colors.white,

                              fontSize: 8,

                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),

                const SizedBox(width: 12),

                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              userName,

                              maxLines: 1,

                              overflow: TextOverflow.ellipsis,

                              style: RiderStyle.cardTitle,
                            ),
                          ),

                          const SizedBox(width: 7),

                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 7,
                              vertical: 4,
                            ),

                            decoration: BoxDecoration(
                              color: RiderColors.lightPurple,

                              borderRadius: BorderRadius.circular(14),
                            ),

                            child: Text(
                              role,

                              style: const TextStyle(
                                color: RiderColors.primary,

                                fontSize: 8,

                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        ],
                      ),

                      if (tracking.isNotEmpty) ...[
                        const SizedBox(height: 4),

                        Row(
                          children: [
                            const Icon(
                              Icons.inventory_2_outlined,

                              color: RiderColors.textGrey,

                              size: 13,
                            ),

                            const SizedBox(width: 4),

                            Expanded(
                              child: Text(
                                tracking,

                                maxLines: 1,

                                overflow: TextOverflow.ellipsis,

                                style: RiderStyle.small,
                              ),
                            ),
                          ],
                        ),
                      ],

                      const SizedBox(height: 5),

                      Text(
                        lastMessage,

                        maxLines: 2,

                        overflow: TextOverflow.ellipsis,

                        style: TextStyle(
                          color: unreadCount > 0
                              ? RiderColors.textDark
                              : RiderColors.textGrey,

                          fontSize: 11,

                          fontWeight: unreadCount > 0
                              ? FontWeight.w700
                              : FontWeight.w400,
                        ),
                      ),

                      const SizedBox(height: 6),

                      Text(
                        lastActivity,

                        maxLines: 1,

                        overflow: TextOverflow.ellipsis,

                        style: const TextStyle(
                          color: RiderColors.textLight,

                          fontSize: 9,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(width: 6),

                const Padding(
                  padding: EdgeInsets.only(top: 12),

                  child: Icon(
                    Icons.chevron_right_rounded,

                    color: RiderColors.textLight,

                    size: 22,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // OPEN CONVERSATION
  // ============================================================

  void _openConversation(Map<String, dynamic> conversation) {
    final String buyer = _displayValue(
      conversation['user_name'],
      fallback: 'Buyer',
    );

    final String tracking = _displayValue(
      conversation['tracking_number'],
      fallback: '',
    );

    showModalBottomSheet<void>(
      context: context,

      backgroundColor: Colors.transparent,

      isScrollControlled: true,

      builder: (BuildContext sheetContext) {
        return SafeArea(
          child: Container(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),

            decoration: const BoxDecoration(
              color: RiderColors.white,

              borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
            ),

            child: Column(
              mainAxisSize: MainAxisSize.min,

              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Center(
                  child: Container(
                    width: 42,
                    height: 4,

                    decoration: BoxDecoration(
                      color: RiderColors.border,

                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),

                const SizedBox(height: 18),

                Row(
                  children: [
                    Container(
                      height: 45,
                      width: 45,

                      decoration: const BoxDecoration(
                        color: RiderColors.lightPurple,

                        shape: BoxShape.circle,
                      ),

                      child: const Icon(
                        Icons.person_outline_rounded,

                        color: RiderColors.primary,
                      ),
                    ),

                    const SizedBox(width: 12),

                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,

                        children: [
                          Text(buyer, style: RiderStyle.sectionTitle),

                          if (tracking.isNotEmpty) ...[
                            const SizedBox(height: 3),

                            Text(tracking, style: RiderStyle.small),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 20),

                Container(
                  width: double.infinity,

                  padding: const EdgeInsets.all(15),

                  decoration: BoxDecoration(
                    color: RiderColors.background,

                    borderRadius: BorderRadius.circular(14),

                    border: Border.all(color: RiderColors.border),
                  ),

                  child: const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      Icon(
                        Icons.info_outline_rounded,

                        color: RiderColors.primary,

                        size: 20,
                      ),

                      SizedBox(width: 9),

                      Expanded(
                        child: Text(
                          'The conversation interface will be activated once the Rider messaging endpoints are connected to the mobile app.',

                          style: RiderStyle.small,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 16),

                SizedBox(
                  width: double.infinity,

                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.pop(sheetContext);
                    },

                    style: RiderStyle.primaryButton,

                    child: const Text('Close', style: RiderStyle.buttonText),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================

  Widget _emptyState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      padding: const EdgeInsets.all(30),

      children: [
        const SizedBox(height: 100),

        Center(
          child: Container(
            height: 90,
            width: 90,

            decoration: const BoxDecoration(
              color: RiderColors.lightPurple,

              shape: BoxShape.circle,
            ),

            child: const Icon(
              Icons.chat_bubble_outline_rounded,

              color: RiderColors.primary,

              size: 42,
            ),
          ),
        ),

        const SizedBox(height: 20),

        const Text(
          'No Conversations Yet',

          textAlign: TextAlign.center,

          style: TextStyle(
            color: RiderColors.textDark,

            fontSize: 18,

            fontWeight: FontWeight.w800,
          ),
        ),

        const SizedBox(height: 8),

        const Text(
          'Buyer conversations about active deliveries will appear here once messaging is connected.',

          textAlign: TextAlign.center,

          style: RiderStyle.small,
        ),

        const SizedBox(height: 18),

        Container(
          width: double.infinity,

          padding: const EdgeInsets.all(14),

          decoration: BoxDecoration(
            color: RiderColors.lightPurple,

            borderRadius: BorderRadius.circular(14),
          ),

          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Icon(
                Icons.info_outline_rounded,

                color: RiderColors.primary,

                size: 20,
              ),

              SizedBox(width: 9),

              Expanded(
                child: Text(
                  'Messages should be linked to a buyer and the related shipment so the Rider knows which delivery is being discussed.',

                  style: RiderStyle.small,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ============================================================
  // NO SEARCH RESULTS
  // ============================================================

  Widget _noSearchResults() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      padding: const EdgeInsets.all(30),

      children: [
        const SizedBox(height: 100),

        const Icon(
          Icons.search_off_rounded,

          color: RiderColors.textGrey,

          size: 55,
        ),

        const SizedBox(height: 16),

        const Text(
          'No Conversation Found',

          textAlign: TextAlign.center,

          style: TextStyle(
            color: RiderColors.textDark,

            fontSize: 17,

            fontWeight: FontWeight.w800,
          ),
        ),

        const SizedBox(height: 7),

        Text(
          'No conversation matches "$_searchQuery".',

          textAlign: TextAlign.center,

          style: RiderStyle.small,
        ),

        const SizedBox(height: 14),

        Center(
          child: TextButton(
            onPressed: () {
              _searchController.clear();

              setState(() {
                _searchQuery = '';
              });
            },

            child: const Text('Clear Search'),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // LOADING
  // ============================================================

  Widget _loadingState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      children: const [
        SizedBox(height: 120),

        Center(
          child: Column(
            children: [
              CircularProgressIndicator(color: RiderColors.primary),

              SizedBox(height: 15),

              Text('Loading conversations...', style: RiderStyle.small),
            ],
          ),
        ),
      ],
    );
  }

  // ============================================================
  // ERROR
  // ============================================================

  Widget _errorState() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),

      padding: const EdgeInsets.all(30),

      children: [
        const SizedBox(height: 100),

        const Icon(
          Icons.cloud_off_outlined,

          color: RiderColors.textGrey,

          size: 60,
        ),

        const SizedBox(height: 16),

        const Text(
          'Unable to Load Messages',

          textAlign: TextAlign.center,

          style: TextStyle(
            color: RiderColors.textDark,

            fontSize: 17,

            fontWeight: FontWeight.w800,
          ),
        ),

        const SizedBox(height: 8),

        Text(
          _errorMessage ?? 'Something went wrong.',

          textAlign: TextAlign.center,

          style: RiderStyle.small,
        ),

        const SizedBox(height: 18),

        Center(
          child: ElevatedButton.icon(
            onPressed: _refreshMessages,

            icon: const Icon(Icons.refresh_rounded, color: Colors.white),

            label: const Text('Retry', style: TextStyle(color: Colors.white)),

            style: RiderStyle.primaryButton,
          ),
        ),
      ],
    );
  }

  // ============================================================
  // HELPERS
  // ============================================================

  int? _toInt(dynamic value) {
    if (value is int) {
      return value;
    }

    if (value is num) {
      return value.toInt();
    }

    return int.tryParse(value?.toString() ?? '');
  }

  String _displayValue(dynamic value, {String fallback = 'Not available'}) {
    final String text = value?.toString().trim() ?? '';

    if (text.isEmpty || text.toLowerCase() == 'null') {
      return fallback;
    }

    return text;
  }

  String _formatActivity(dynamic value) {
    final String raw = value?.toString().trim() ?? '';

    if (raw.isEmpty) {
      return 'No recent activity';
    }

    final DateTime? parsed = DateTime.tryParse(raw);

    if (parsed == null) {
      return raw;
    }

    final DateTime local = parsed.toLocal();

    const List<String> months = <String>[
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];

    final String month = months[local.month - 1];

    final int hour = local.hour == 0
        ? 12
        : local.hour > 12
        ? local.hour - 12
        : local.hour;

    final String minute = local.minute.toString().padLeft(2, '0');

    final String meridiem = local.hour >= 12 ? 'PM' : 'AM';

    return '$month ${local.day}, ${local.year} • '
        '$hour:$minute $meridiem';
  }
}
