class LiveChunk {
  const LiveChunk({
    required this.index,
    required this.url,
    required this.mime,
  });

  final int index;
  final String url;
  final String mime;

  factory LiveChunk.fromJson(Map<String, dynamic> json) {
    return LiveChunk(
      index: json['index'] as int,
      url: json['url'] as String,
      mime: json['mime'] as String? ?? 'audio/webm',
    );
  }
}

class LiveChunksResponse {
  const LiveChunksResponse({
    required this.isLive,
    required this.chunks,
    required this.latestIndex,
    required this.reset,
  });

  final bool isLive;
  final List<LiveChunk> chunks;
  final int latestIndex;
  final bool reset;

  factory LiveChunksResponse.fromJson(Map<String, dynamic> json) {
    final raw = json['chunks'] as List<dynamic>? ?? [];
    return LiveChunksResponse(
      isLive: json['is_live'] as bool? ?? false,
      chunks: raw.map((e) => LiveChunk.fromJson(e as Map<String, dynamic>)).toList(),
      latestIndex: json['latest_index'] as int? ?? -1,
      reset: json['reset'] as bool? ?? false,
    );
  }
}
