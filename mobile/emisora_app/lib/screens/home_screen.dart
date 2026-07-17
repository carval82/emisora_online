import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/player_controller.dart';
import '../widgets/message_panel.dart';
import 'settings_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  String _formatDuration(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<PlayerController>(
      builder: (context, player, _) {
        final isLive = player.mode == PlayerMode.live;
        final progress = player.duration != null && player.duration!.inMilliseconds > 0
            ? player.position.inMilliseconds / player.duration!.inMilliseconds
            : 0.0;

        return Scaffold(
          body: Container(
            decoration: const BoxDecoration(
              gradient: RadialGradient(
                center: Alignment.topCenter,
                radius: 1.2,
                colors: [Color(0xFF1E1B4B), Color(0xFF0F0A1A), Color(0xFF030712)],
              ),
            ),
            child: SafeArea(
              child: player.loading
                  ? const Center(child: CircularProgressIndicator(color: Color(0xFF7C3AED)))
                  : Column(
                      children: [
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 8, 8, 0),
                          child: Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      player.station?.name ?? 'Emisora Online',
                                      style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                                    ),
                                    Text(
                                      player.station?.slogan ?? '',
                                      style: TextStyle(color: Colors.purple.shade200.withOpacity(0.7), fontSize: 13),
                                    ),
                                  ],
                                ),
                              ),
                              IconButton(
                                onPressed: () => player.refreshAll(),
                                icon: const Icon(Icons.refresh),
                                tooltip: 'Recargar',
                              ),
                              IconButton(
                                onPressed: () => Navigator.pushNamed(context, SettingsScreen.route),
                                icon: const Icon(Icons.settings),
                                tooltip: 'Servidor',
                              ),
                            ],
                          ),
                        ),
                        if (isLive)
                          Padding(
                            padding: const EdgeInsets.only(top: 12),
                            child: Column(
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                                  decoration: BoxDecoration(
                                    color: Colors.red.shade700.withOpacity(0.85),
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(
                                        player.liveBuffering ? Icons.hourglass_top : Icons.circle,
                                        size: player.liveBuffering ? 14 : 8,
                                        color: Colors.white,
                                      ),
                                      const SizedBox(width: 8),
                                      Text(
                                        player.liveBuffering ? 'BUFFER' : 'EN VIVO',
                                        style: const TextStyle(fontWeight: FontWeight.w600),
                                      ),
                                    ],
                                  ),
                                ),
                                if (player.liveBuffering)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 6),
                                    child: Text(
                                      'Preparando audio fluido (~6 s de retraso)',
                                      style: TextStyle(
                                        color: Colors.purple.shade200.withOpacity(0.55),
                                        fontSize: 11,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                        if (player.error != null)
                          Padding(
                            padding: const EdgeInsets.all(16),
                            child: Text(player.error!, style: const TextStyle(color: Colors.redAccent)),
                          ),
                        Expanded(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              _VinylDisc(spinning: player.isPlaying),
                              const SizedBox(height: 24),
                              Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 24),
                                child: Text(
                                  player.nowTitle,
                                  textAlign: TextAlign.center,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600),
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                player.nowArtist,
                                style: TextStyle(color: Colors.purple.shade200.withOpacity(0.6)),
                              ),
                              if (player.station?.playlist != null && !isLive)
                                Padding(
                                  padding: const EdgeInsets.only(top: 8),
                                  child: Text(
                                    player.station!.playlist!,
                                    style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                                  ),
                                ),
                              const SizedBox(height: 28),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  _RoundButton(
                                    icon: Icons.skip_previous,
                                    onPressed: isLive ? null : player.playPrevious,
                                  ),
                                  const SizedBox(width: 20),
                                  _RoundButton(
                                    icon: player.isPlaying ? Icons.pause : Icons.play_arrow,
                                    large: true,
                                    onPressed: player.togglePlayPause,
                                  ),
                                  const SizedBox(width: 20),
                                  _RoundButton(
                                    icon: Icons.skip_next,
                                    onPressed: isLive ? null : player.playNext,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 24),
                              Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 32),
                                child: Column(
                                  children: [
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(999),
                                      child: LinearProgressIndicator(
                                        value: isLive ? null : progress.clamp(0.0, 1.0),
                                        minHeight: 4,
                                        backgroundColor: Colors.white10,
                                        color: const Color(0xFF8B5CF6),
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Text(
                                          isLive ? 'LIVE' : _formatDuration(player.position),
                                          style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                                        ),
                                        Text(
                                          isLive ? '●' : _formatDuration(player.duration ?? Duration.zero),
                                          style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                          child: MessagePanel(
                            messages: player.messages,
                            senderName: player.senderName,
                            onSend: player.sendMessage,
                            onSenderChanged: player.setSenderName,
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        );
      },
    );
  }
}

class _VinylDisc extends StatelessWidget {
  const _VinylDisc({required this.spinning});

  final bool spinning;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 180,
      height: 180,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: const LinearGradient(
          colors: [Color(0xFF4C1D95), Color(0xFF0F172A)],
        ),
        border: Border.all(color: const Color(0x668B5CF6), width: 3),
        boxShadow: [
          BoxShadow(color: Colors.purple.withOpacity(0.25), blurRadius: 24, spreadRadius: 4),
        ],
      ),
      child: Center(
        child: ClipOval(
          child: Image.asset(
            'assets/logo.png',
            width: 100,
            height: 100,
            fit: BoxFit.cover,
            errorBuilder: (_, __, ___) => const Icon(Icons.radio, color: Color(0xFFA78BFA), size: 48),
          ),
        ),
      ),
    );
  }
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({
    required this.icon,
    required this.onPressed,
    this.large = false,
  });

  final IconData icon;
  final VoidCallback? onPressed;
  final bool large;

  @override
  Widget build(BuildContext context) {
    final size = large ? 64.0 : 48.0;
    return Material(
      color: large ? const Color(0xFF7C3AED) : Colors.white10,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onPressed,
        child: SizedBox(
          width: size,
          height: size,
          child: Icon(icon, size: large ? 32 : 22),
        ),
      ),
    );
  }
}
