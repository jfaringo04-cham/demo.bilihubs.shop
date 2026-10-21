import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';

class RiderMessages extends StatefulWidget {
  const RiderMessages({super.key});

  @override
  State<RiderMessages> createState() => _RiderMessagesState();
}

class _RiderMessagesState extends State<RiderMessages> {
  final TextEditingController _searchController = TextEditingController();

  String _searchQuery = '';

  bool _isLoading = false;

  String? _errorMessage;

  final List<Map<String, dynamic>> _conversations = <Map<String, dynamic>>[];

  @override
  void dispose() {
    _searchController.dispose();

    super.dispose();
  }

  // ============================================================
  // FILTERED CONVERSATIONS
  // ============================================================

  List<Map<String, dynamic>> get _filteredConversations {
    final query = _searchQuery.trim().toLowerCase();

    if (query.isEmpty) {
      return _conversations;
    }

    return _conversations.where((conversation) {
      final userName =
          conversation['user_name']?.toString().toLowerCase() ?? '';

      final role = conversation['role']?.toString().toLowerCase() ?? '';

      final lastMessage =
          conversation['last_message']?.toString().toLowerCase() ?? '';

      final tracking =
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
    if (!mounted) return;

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

        content: Text('Rider messaging API is not connected yet.'),
      ),
    );
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.of(context).size.width;

    final horizontalPadding = width < 380 ? 14.0 : 18.0;

    final conversations = _filteredConversations;

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
          IconButton(
            tooltip: 'Refresh',

            onPressed: _isLoading ? null : _refreshMessages,

            icon: const Icon(Icons.refresh_rounded, color: RiderColors.primary),
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

                onChanged: (value) {
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

                    borderSide: BorderSide(color: RiderColors.border),
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
    if (_isLoading) {
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

      itemBuilder: (context, index) {
        return _conversationCard(conversations[index]);
      },
    );
  }

  // ============================================================
  // CONVERSATION CARD
  // ============================================================

  Widget _conversationCard(Map<String, dynamic> conversation) {
    final userName = _displayValue(
      conversation['user_name'],
      fallback: 'Buyer',
    );

    final role = _displayValue(conversation['role'], fallback: 'Buyer');

    final lastMessage = _displayValue(
      conversation['last_message'],
      fallback: 'No messages yet.',
    );

    final lastActivity = _displayValue(
      conversation['last_activity'],
      fallback: 'No recent activity',
    );

    final tracking = _displayValue(
      conversation['tracking_number'],
      fallback: '',
    );

    final unreadCount = _toInt(conversation['unread_count']) ?? 0;

    return Material(
      color: RiderColors.white,

      borderRadius: BorderRadius.circular(16),

      child: InkWell(
        onTap: () {
          _openConversation(conversation);
        },

        borderRadius: BorderRadius.circular(16),

        child: Container(
          margin: const EdgeInsets.only(bottom: 12),

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
    );
  }

  // ============================================================
  // OPEN CONVERSATION
  // ============================================================

  void _openConversation(Map<String, dynamic> conversation) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        behavior: SnackBarBehavior.floating,

        content: Text(
          'Chat screen will be enabled once the Rider messaging API is connected.',
        ),
      ),
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
          'Buyer conversations about active deliveries will appear here.',

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
                  'Messages should be linked to the buyer and the related order or shipment so both sides know which delivery they are discussing.',

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

    return int.tryParse(value?.toString() ?? '');
  }

  String _displayValue(dynamic value, {String fallback = 'Not available'}) {
    final text = value?.toString().trim() ?? '';

    return text.isEmpty ? fallback : text;
  }
}
