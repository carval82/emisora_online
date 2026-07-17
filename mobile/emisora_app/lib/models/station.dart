class Station {
  const Station({
    required this.name,
    required this.slogan,
    this.logoUrl,
    required this.isLive,
    this.hostName,
    this.playlist,
    this.liveStartedAt,
    this.latestIndex,
  });

  final String name;
  final String slogan;
  final String? logoUrl;
  final bool isLive;
  final String? hostName;
  final String? playlist;
  final String? liveStartedAt;
  final int? latestIndex;

  factory Station.fromJson(Map<String, dynamic> json) {
    return Station(
      name: json['name'] as String? ?? 'Emisora Online',
      slogan: json['slogan'] as String? ?? '',
      logoUrl: json['logo_url'] as String?,
      isLive: json['is_live'] as bool? ?? false,
      hostName: json['host_name'] as String?,
      playlist: json['playlist'] as String?,
      liveStartedAt: json['live_started_at'] as String?,
      latestIndex: json['latest_index'] as int?,
    );
  }
}
