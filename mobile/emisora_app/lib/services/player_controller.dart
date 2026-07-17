import 'dart:async';
import 'dart:io';

import 'package:audio_session/audio_session.dart';
import 'package:flutter/foundation.dart';
import 'package:just_audio/just_audio.dart';
import 'package:just_audio_background/just_audio_background.dart';
import 'package:permission_handler/permission_handler.dart';

import '../models/radio_message.dart';
import '../models/song.dart';
import '../models/station.dart';
import 'api_service.dart';

enum PlayerMode { idle, queue, live }

class PlayerController extends ChangeNotifier {
  PlayerController(this._api);

  final ApiService _api;
  final AudioPlayer _player = AudioPlayer();

  Station? station;
  List<Song> queue = [];
  List<RadioMessage> messages = [];
  int currentIndex = 0;
  PlayerMode mode = PlayerMode.idle;

  bool loading = true;
  String? error;
  bool isPlaying = false;
  bool liveBuffering = false;
  int liveBufferSeconds = 0;
  Duration position = Duration.zero;
  Duration? duration;

  /// Buffer corto solo con chunks NUEVOS desde el borde en vivo (~4 s de retraso).
  static const int _liveStartBufferChunks = 4;
  static const int _liveFetchLimit = 8;
  static const int _liveMaxStaleBehindEdge = 6;
  static const int _liveMaxLagBeforeReset = 18;
  static const int _liveMaxPlaylistChunks = 36;

  String senderName = 'Oyente';
  String get serverUrl => _api.baseUrl;

  ConcatenatingAudioSource? _queuePlaylist;
  ConcatenatingAudioSource? _livePlaylist;
  final Set<int> _liveAddedIndices = {};
  int _liveLastAddedIndex = -1;
  bool _liveLoopActive = false;
  String? _liveStartedAt;
  bool _livePlaybackStarted = false;
  int _liveServerLatestIndex = -1;
  int? _livePlayingSequenceIndex;
  bool _disposed = false;
  DateTime _lastLiveStatusPoll = DateTime.fromMillisecondsSinceEpoch(0);

  Timer? _stationTimer;
  Timer? _messagesTimer;
  StreamSubscription<PlayerState>? _playerSub;
  StreamSubscription<Duration>? _positionSub;
  StreamSubscription<Duration?>? _durationSub;
  StreamSubscription<int?>? _sequenceSub;

  String get nowTitle {
    if (mode == PlayerMode.live) {
      return station?.hostName ?? 'En directo';
    }
    if (queue.isEmpty) return 'Sin música';
    return queue[currentIndex.clamp(0, queue.length - 1)].title;
  }

  String get nowArtist {
    if (mode == PlayerMode.live) {
      if (liveBuffering) {
        return liveBufferSeconds > 0
            ? 'Sincronizando ($liveBufferSeconds s en cola)...'
            : 'Sincronizando en vivo...';
      }
      return liveBufferSeconds > 0 ? 'EN VIVO · +${liveBufferSeconds}s' : 'EN VIVO';
    }
    if (queue.isEmpty) return '—';
    return queue[currentIndex.clamp(0, queue.length - 1)].artist;
  }

  Future<void> init() async {
    if (Platform.isAndroid) {
      await Permission.notification.request();
    }

    try {
      await _configureAudioSession();
    } catch (e) {
      debugPrint('AudioSession omitida: $e');
    }

    _playerSub = _player.playerStateStream.listen((state) {
      isPlaying = state.playing;
      notifyListeners();
    });
    _positionSub = _player.positionStream.listen((pos) {
      position = pos;
      notifyListeners();
    });
    _durationSub = _player.durationStream.listen((dur) {
      duration = dur;
      notifyListeners();
    });
    _sequenceSub = _player.currentIndexStream.listen((index) {
      if (mode == PlayerMode.queue && index != null && queue.isNotEmpty) {
        currentIndex = index % queue.length;
        notifyListeners();
      } else if (mode == PlayerMode.live && index != null) {
        _livePlayingSequenceIndex = index;
        unawaited(_updateLiveBufferState());
      }
    });

    await refreshAll();
    _startPolling();

    if (station?.isLive ?? false) {
      if (mode != PlayerMode.live) {
        await _enterLiveMode();
      }
    } else if (mode == PlayerMode.queue && queue.isNotEmpty) {
      await _startQueuePlayback(0);
    }
  }

  Future<void> _configureAudioSession() async {
    final session = await AudioSession.instance;
    await session.configure(const AudioSessionConfiguration(
      avAudioSessionCategory: AVAudioSessionCategory.playback,
      avAudioSessionCategoryOptions: AVAudioSessionCategoryOptions.duckOthers,
      avAudioSessionMode: AVAudioSessionMode.defaultMode,
      androidAudioAttributes: AndroidAudioAttributes(
        contentType: AndroidAudioContentType.music,
        usage: AndroidAudioUsage.media,
      ),
      androidAudioFocusGainType: AndroidAudioFocusGainType.gain,
    ));
  }

  void _startPolling() {
    _stationTimer?.cancel();
    _messagesTimer?.cancel();
    _stationTimer = Timer.periodic(
      Duration(seconds: mode == PlayerMode.live ? 12 : 4),
      (_) => _pollStation(),
    );
    _messagesTimer = Timer.periodic(
      Duration(seconds: mode == PlayerMode.live ? 45 : 15),
      (_) => _pollMessages(),
    );
  }

  Future<void> refreshAll() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      await Future.wait([_pollStation(), _pollMessages(), _loadQueue()]);
      loading = false;
    } catch (e) {
      error = 'No se pudo conectar con la emisora';
      loading = false;
    }
    notifyListeners();
  }

  Future<void> _pollStation() async {
    try {
      final next = await _api.fetchStation();
      final wasLive = station?.isLive ?? false;
      final prevStartedAt = station?.liveStartedAt;
      station = next;

      if (next.isLive &&
          mode == PlayerMode.live &&
          next.liveStartedAt != null &&
          prevStartedAt != null &&
          next.liveStartedAt != prevStartedAt) {
        await _restartLiveSession(next);
        notifyListeners();
        return;
      }

      if (next.isLive && !wasLive && mode != PlayerMode.live) {
        await _enterLiveMode();
      } else if (!next.isLive && mode == PlayerMode.live) {
        await _exitLiveMode(resumeQueue: true);
      }
      notifyListeners();
    } catch (e) {
      debugPrint('Station poll: $e');
    }
  }

  Future<void> _pollMessages() async {
    try {
      messages = await _api.fetchMessages();
      notifyListeners();
    } catch (_) {}
  }

  Future<void> _loadQueue() async {
    queue = await _api.fetchQueue();
    if (mode != PlayerMode.live && queue.isNotEmpty && mode == PlayerMode.idle) {
      mode = PlayerMode.queue;
    }
  }

  Future<void> togglePlayPause() async {
    if (_player.playing) {
      await _player.pause();
    } else {
      if (mode == PlayerMode.idle && queue.isNotEmpty) {
        await _startQueuePlayback(currentIndex);
      } else {
        await _player.play();
      }
    }
  }

  Future<void> playNext() async {
    if (mode == PlayerMode.live || queue.isEmpty) return;
    if (_queuePlaylist != null) {
      await _player.seekToNext();
    } else {
      currentIndex = (currentIndex + 1) % queue.length;
      await _startQueuePlayback(currentIndex);
    }
  }

  Future<void> playPrevious() async {
    if (mode == PlayerMode.live || queue.isEmpty) return;
    if (_queuePlaylist != null) {
      await _player.seekToPrevious();
    } else {
      currentIndex = (currentIndex - 1 + queue.length) % queue.length;
      await _startQueuePlayback(currentIndex);
    }
  }

  Uri? get _artUri {
    final logo = station?.logoUrl;
    if (logo == null || logo.isEmpty) return null;
    return Uri.parse(_api.resolveUrl(logo));
  }

  MediaItem _songMediaItem(Song song) {
    return MediaItem(
      id: song.url,
      title: song.title,
      artist: song.artist,
      album: song.album,
      artUri: _artUri,
    );
  }

  MediaItem _liveMediaItem(int index) {
    return MediaItem(
      id: 'live-$index',
      title: station?.hostName ?? 'En directo',
      artist: station?.name ?? 'Emisora Online',
      artUri: _artUri,
    );
  }

  AudioSource _cachedSource(String url, MediaItem tag) {
    return LockCachingAudioSource(Uri.parse(url), tag: tag);
  }

  AudioSource _liveSource(String url, MediaItem tag) {
    return AudioSource.uri(Uri.parse(url), tag: tag);
  }

  bool _acceptLiveChunk(int chunkIndex, int latestIndex) {
    if (chunkIndex <= _liveLastAddedIndex) return false;
    if (latestIndex >= 0 && chunkIndex < latestIndex - _liveMaxStaleBehindEdge) {
      return false;
    }
    return true;
  }

  int _liveChunksAhead() {
    if (_livePlaylist == null) return 0;
    final total = _livePlaylist!.length;
    if (total == 0) return 0;
    final current = _livePlayingSequenceIndex ?? 0;
    return (total - current - 1).clamp(0, total);
  }

  void _syncLiveBufferMetrics() {
    liveBufferSeconds = _liveChunksAhead();
  }

  Future<void> _updateLiveBufferState() async {
    if (!_liveLoopActive || _livePlaylist == null || mode != PlayerMode.live) return;

    _syncLiveBufferMetrics();
    final total = _livePlaylist!.length;

    if (!_livePlaybackStarted && total >= _liveStartBufferChunks) {
      _livePlaybackStarted = true;
      liveBuffering = false;
      try {
        await _player.play();
      } catch (e) {
        debugPrint('Live play: $e');
      }
    } else if (!_livePlaybackStarted) {
      liveBuffering = true;
    }

    notifyListeners();
  }

  void _resetLiveBufferState({required bool buffering}) {
    _livePlaybackStarted = false;
    _livePlayingSequenceIndex = null;
    liveBuffering = buffering;
    liveBufferSeconds = 0;
  }

  Future<void> _jumpToLiveEdge(int latestIndex) async {
    _liveServerLatestIndex = latestIndex;
    _liveLastAddedIndex = latestIndex;
    _liveAddedIndices.clear();
    _resetLiveBufferState(buffering: true);
    await _player.stop();
    _livePlaylist = ConcatenatingAudioSource(
      useLazyPreparation: true,
      children: [],
    );
    await _player.setAudioSource(_livePlaylist!, preload: true);
    notifyListeners();
  }

  Future<void> _startQueuePlayback(int startIndex) async {
    if (queue.isEmpty) return;
    mode = PlayerMode.queue;
    _liveLoopActive = false;
    currentIndex = startIndex;

    final sources = queue
        .map((s) => _cachedSource(_api.resolveUrl(s.url), _songMediaItem(s)))
        .toList();
    final rotated = [
      ...sources.sublist(startIndex),
      ...sources.sublist(0, startIndex),
    ];

    _queuePlaylist = ConcatenatingAudioSource(
      useLazyPreparation: true,
      children: rotated,
    );
    _livePlaylist = null;

    await _player.setAudioSource(_queuePlaylist!, preload: true);
    await _player.play();
    notifyListeners();
  }

  Future<void> _enterLiveMode() async {
    mode = PlayerMode.live;
    _liveLoopActive = true;
    _queuePlaylist = null;
    _liveAddedIndices.clear();
    _liveStartedAt = station?.liveStartedAt;
    _resetLiveBufferState(buffering: true);
    _startPolling();

    await _player.stop();

    final status = await _api.fetchLiveStatus();
    _liveServerLatestIndex = status.latestIndex;
    _liveLastAddedIndex = status.latestIndex;
    _lastLiveStatusPoll = DateTime.now();

    _livePlaylist = ConcatenatingAudioSource(
      useLazyPreparation: true,
      children: [],
    );

    await _player.setAudioSource(_livePlaylist!, preload: true);
    notifyListeners();

    unawaited(_liveFetchLoop());
  }

  Future<void> _restartLiveSession(Station next) async {
    _liveStartedAt = next.liveStartedAt;
    await _jumpToLiveEdge(next.latestIndex ?? -1);
  }

  Future<void> _resetLivePlaylist(int latestIndex) async {
    await _jumpToLiveEdge(latestIndex);
  }

  Future<void> _exitLiveMode({bool resumeQueue = false}) async {
    _liveLoopActive = false;
    _liveLastAddedIndex = -1;
    _liveAddedIndices.clear();
    _liveStartedAt = null;
    _livePlaylist = null;
    _resetLiveBufferState(buffering: false);
    await _player.stop();
    mode = queue.isEmpty ? PlayerMode.idle : PlayerMode.queue;
    _startPolling();
    notifyListeners();
    if (resumeQueue && queue.isNotEmpty) {
      await _startQueuePlayback(currentIndex);
    }
  }

  Future<void> _liveFetchLoop() async {
    while (_liveLoopActive && !_disposed) {
      try {
        final now = DateTime.now();
        final shouldPollStatus = now.difference(_lastLiveStatusPoll).inSeconds >= 8;

        if (shouldPollStatus) {
          final status = await _api.fetchLiveStatus();
          _lastLiveStatusPoll = now;
          if (!status.isLive) {
            await _exitLiveMode(resumeQueue: true);
            break;
          }
          if (_liveLastAddedIndex >= 0 &&
              status.latestIndex - _liveLastAddedIndex > _liveMaxLagBeforeReset) {
            await _jumpToLiveEdge(status.latestIndex);
            continue;
          }
        }

        if (_livePlaylist != null && _livePlaylist!.length > _liveMaxPlaylistChunks) {
          final status = await _api.fetchLiveStatus();
          await _jumpToLiveEdge(status.latestIndex);
          continue;
        }

        final response = await _api.fetchLiveChunks(
          after: _liveLastAddedIndex,
          limit: _liveFetchLimit,
        );

        if (!response.isLive) {
          await _exitLiveMode(resumeQueue: true);
          break;
        }

        _liveServerLatestIndex = response.latestIndex;

        if (response.reset) {
          await _jumpToLiveEdge(response.latestIndex);
          continue;
        }

        var added = 0;
        for (final chunk in response.chunks) {
          if (!_liveLoopActive || _livePlaylist == null) break;
          if (!_acceptLiveChunk(chunk.index, response.latestIndex)) continue;
          if (_liveAddedIndices.contains(chunk.index)) continue;

          final url = _api.resolveUrl(chunk.url);
          await _livePlaylist!.add(
            _liveSource(url, _liveMediaItem(chunk.index)),
          );
          _liveAddedIndices.add(chunk.index);
          _liveLastAddedIndex = chunk.index;
          added++;
        }

        if (added > 0 || !_livePlaybackStarted) {
          await _updateLiveBufferState();
        }

        final pending = response.latestIndex - _liveLastAddedIndex;
        final delayMs = !_livePlaybackStarted
            ? 200
            : (pending > 1 ? 280 : 420);
        await Future.delayed(Duration(milliseconds: delayMs));
      } catch (e) {
        debugPrint('Live fetch: $e');
        await Future.delayed(const Duration(milliseconds: 600));
      }
    }
  }

  Future<void> sendMessage(String content) async {
    final trimmed = content.trim();
    if (trimmed.isEmpty || senderName.trim().isEmpty) return;
    final msg = await _api.sendMessage(senderName: senderName.trim(), content: trimmed);
    messages = [msg, ...messages];
    notifyListeners();
  }

  void setSenderName(String name) {
    senderName = name;
    notifyListeners();
  }

  Future<void> updateServer(String url) async {
    _api.baseUrl = url.replaceAll(RegExp(r'/+$'), '');
    _liveLoopActive = false;
    _livePlaylist = null;
    _queuePlaylist = null;
    _liveAddedIndices.clear();
    await _player.stop();
    mode = PlayerMode.idle;
    await refreshAll();
  }

  @override
  void dispose() {
    _disposed = true;
    _liveLoopActive = false;
    _stationTimer?.cancel();
    _messagesTimer?.cancel();
    _playerSub?.cancel();
    _positionSub?.cancel();
    _durationSub?.cancel();
    _sequenceSub?.cancel();
    _player.dispose();
    super.dispose();
  }
}
