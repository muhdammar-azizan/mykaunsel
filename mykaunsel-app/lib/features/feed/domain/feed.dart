import '../../chat/domain/chat.dart';

/// Who wrote a post or a comment: the name in their profile, and whether they are a verified counselor (the badge).
class FeedAuthor {
  const FeedAuthor({required this.name, this.counselor = false});

  factory FeedAuthor.fromJson(Map<String, dynamic> json) => FeedAuthor(name: json['name'] as String? ?? '', counselor: json['counselor'] == true);

  final String name;
  final bool counselor;
}

/// The likes of a post: how many, and whether the signed-in person is one of them.
class FeedReactions {
  const FeedReactions({this.count = 0, this.mine = false});

  factory FeedReactions.fromJson(Map<String, dynamic> json) => FeedReactions(count: (json['count'] as num?)?.toInt() ?? 0, mine: json['mine'] == true);

  final int count;
  final bool mine;
}

class FeedPost {
  const FeedPost({
    required this.id,
    required this.text,
    required this.createdAt,
    required this.author,
    this.edited = false,
    this.mine = false,
    this.reactions = const FeedReactions(),
    this.commentsCount = 0,
  });

  factory FeedPost.fromJson(Map<String, dynamic> json) => FeedPost(
        id: json['id'] as String,
        text: json['text'] as String? ?? '',
        createdAt: DateTime.parse(json['created_at'] as String),
        author: FeedAuthor.fromJson(Map<String, dynamic>.from(json['author'] as Map)),
        edited: json['edited'] == true,
        mine: json['mine'] == true,
        reactions: FeedReactions.fromJson(Map<String, dynamic>.from((json['reactions'] as Map?) ?? const {})),
        commentsCount: (json['comments_count'] as num?)?.toInt() ?? 0,
      );

  final String id;
  final String text;
  final DateTime createdAt;
  final FeedAuthor author;
  final bool edited;

  /// Written by the signed-in person: they may edit and delete it, and cannot report or mute themselves.
  final bool mine;
  final FeedReactions reactions;
  final int commentsCount;

  FeedPost copyWith({String? text, bool? edited, FeedReactions? reactions, int? commentsCount}) => FeedPost(
        id: id,
        text: text ?? this.text,
        createdAt: createdAt,
        author: author,
        edited: edited ?? this.edited,
        mine: mine,
        reactions: reactions ?? this.reactions,
        commentsCount: commentsCount ?? this.commentsCount,
      );
}

class FeedComment {
  const FeedComment({required this.id, required this.text, required this.createdAt, required this.author, this.mine = false});

  factory FeedComment.fromJson(Map<String, dynamic> json) => FeedComment(
        id: json['id'] as String,
        text: json['text'] as String? ?? '',
        createdAt: DateTime.parse(json['created_at'] as String),
        author: FeedAuthor.fromJson(Map<String, dynamic>.from(json['author'] as Map)),
        mine: json['mine'] == true,
      );

  final String id;
  final String text;
  final DateTime createdAt;
  final FeedAuthor author;
  final bool mine;
}

/// One page of posts or comments. [nextCursor] is null at the end.
class FeedPage<T> {
  const FeedPage({required this.items, this.nextCursor});

  final List<T> items;
  final String? nextCursor;
}

/// What writing a post or a comment answers: the stored item, and the server's own check that the words may mean the person is in danger.
/// The item is stored whatever [crisis] says.
class FeedWrite<T> {
  const FeedWrite({required this.item, this.crisis = false, this.helplines = const []});

  final T item;
  final bool crisis;
  final List<Helpline> helplines;
}

/// A person the signed-in person muted. [id] is the mute's own id (used to undo it).
class FeedMuted {
  const FeedMuted({required this.id, required this.name});

  factory FeedMuted.fromJson(Map<String, dynamic> json) => FeedMuted(id: json['id'] as String, name: json['name'] as String? ?? '');

  final String id;
  final String name;
}

/// The reasons the server accepts for a report, in the order they are offered.
const feedReportReasons = ['spam', 'harassment', 'self_harm', 'inappropriate', 'other'];
