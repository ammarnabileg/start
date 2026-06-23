import 'dart:async';

import 'package:geofence_service/geofence_service.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// خدمة القرب (Geofencing).
///
/// تقرأ تفضيل المستخدم proximity_opt_in، تجلب أقرب الفروع عبر RPC
/// (nearest_branches)، تسجّل حتى 20 منطقة جغرافية، وعند الدخول إلى أي منها
/// تستدعي دالة الحافة proximity-hit. كل النداءات الأصلية مغلّفة بـ try/catch
/// حتى يظل التطبيق قابلًا للتشغيل عند غياب الإعداد.
class ProximityService {
  ProximityService._();

  /// نسخة وحيدة (Singleton).
  static final ProximityService instance = ProximityService._();

  /// أقصى عدد للفروع/المناطق الجغرافية.
  static const int _maxGeofences = 20;

  /// المسافة (متر) التي تستوجب إعادة جلب أقرب الفروع عند تجاوزها.
  static const double _refreshDistanceM = 1000;

  final GeofenceService _service = GeofenceService.instance.setup(
    interval: 5000,
    accuracy: 100,
    loiteringDelayMs: 60000,
    statusChangeDelayMs: 10000,
    useActivityRecognition: false,
    allowMockLocations: false,
    printDevLog: false,
    geofenceRadiusSortType: GeofenceRadiusSortType.DESC,
  );

  bool _running = false;
  double? _lastQueryLat;
  double? _lastQueryLng;
  bool _refreshing = false;

  bool get isRunning => _running;

  /// بدء خدمة القرب.
  Future<void> start() async {
    if (_running) return;

    final client = Supabase.instance.client;
    final user = client.auth.currentUser;
    if (user == null) return;

    // 1) تحقّق من تفعيل المستخدم لميزة القرب.
    try {
      final row = await client
          .from('users')
          .select('proximity_opt_in')
          .eq('id', user.id)
          .maybeSingle();
      final optIn = row?['proximity_opt_in'] == true;
      if (!optIn) return;
    } catch (_) {
      // إن تعذّر قراءة التفضيل، لا نبدأ.
      return;
    }

    // 2) الموقع الحالي.
    Location? location;
    try {
      location = await FlLocation.getLocation();
    } catch (_) {
      location = null;
    }

    // 3) بناء قائمة المناطق الجغرافية من أقرب الفروع.
    List<Geofence> geofences = const [];
    if (location != null) {
      geofences = await _buildGeofences(
        uid: user.id,
        lat: location.latitude,
        lng: location.longitude,
      );
      _lastQueryLat = location.latitude;
      _lastQueryLng = location.longitude;
    }

    // 4) ربط المستمعين.
    _service.addGeofenceStatusChangeListener(_onGeofenceStatusChanged);
    _service.addLocationChangeListener(_onLocationChanged);

    // 5) تشغيل الخدمة.
    try {
      await _service.start(geofences.isEmpty ? null : geofences);
      _running = true;
    } catch (_) {
      // فشل التشغيل (إذن مرفوض/خدمة موقع معطّلة) — أزِل المستمعين.
      _service.removeGeofenceStatusChangeListener(_onGeofenceStatusChanged);
      _service.removeLocationChangeListener(_onLocationChanged);
      _running = false;
    }
  }

  /// إيقاف الخدمة ومسح المناطق.
  Future<void> stop() async {
    try {
      _service.removeGeofenceStatusChangeListener(_onGeofenceStatusChanged);
      _service.removeLocationChangeListener(_onLocationChanged);
      _service.clearGeofenceList();
      await _service.stop();
    } catch (_) {
      // تجاهل.
    } finally {
      _running = false;
      _lastQueryLat = null;
      _lastQueryLng = null;
    }
  }

  /// جلب أقرب الفروع وتحويلها إلى مناطق جغرافية.
  Future<List<Geofence>> _buildGeofences({
    required String uid,
    required double lat,
    required double lng,
  }) async {
    try {
      final result = await Supabase.instance.client.rpc(
        'nearest_branches',
        params: {
          'p_user': uid,
          'p_lat': lat,
          'p_lng': lng,
          'p_limit': _maxGeofences,
        },
      );

      final rows = (result as List?) ?? const [];
      final geofences = <Geofence>[];
      for (final raw in rows.take(_maxGeofences)) {
        final row = raw as Map<String, dynamic>;
        final id = row['branch_id']?.toString();
        final blat = _toDouble(row['lat']);
        final blng = _toDouble(row['lng']);
        final radius = _toDouble(row['radius_m']) ?? 150;
        if (id == null || id.isEmpty || blat == null || blng == null) continue;

        geofences.add(
          Geofence(
            id: id,
            latitude: blat,
            longitude: blng,
            radius: [
              GeofenceRadius(id: 'r_$id', length: radius <= 0 ? 150 : radius),
            ],
          ),
        );
      }
      return geofences;
    } catch (_) {
      return const [];
    }
  }

  Future<void> _onGeofenceStatusChanged(
    Geofence geofence,
    GeofenceRadius geofenceRadius,
    GeofenceStatus status,
    Location location,
  ) async {
    if (status != GeofenceStatus.ENTER) return;
    // أفضل جهد — استدعاء دالة الحافة.
    try {
      await Supabase.instance.client.functions.invoke(
        'proximity-hit',
        body: {'branch_id': geofence.id},
      );
    } catch (_) {
      // تجاهل.
    }
  }

  void _onLocationChanged(Location location) {
    // أعد جلب أقرب الفروع عند تحرّك ملحوظ.
    final lastLat = _lastQueryLat;
    final lastLng = _lastQueryLng;
    if (lastLat == null || lastLng == null) return;
    if (_refreshing) return;

    final moved = LocationUtils.distanceBetween(
      lastLat,
      lastLng,
      location.latitude,
      location.longitude,
    );
    if (moved < _refreshDistanceM) return;

    _refreshing = true;
    unawaited(_refreshGeofences(location).whenComplete(() {
      _refreshing = false;
    }));
  }

  Future<void> _refreshGeofences(Location location) async {
    final user = Supabase.instance.client.auth.currentUser;
    if (user == null) return;

    final geofences = await _buildGeofences(
      uid: user.id,
      lat: location.latitude,
      lng: location.longitude,
    );
    if (geofences.isEmpty) return;

    try {
      _service.clearGeofenceList();
      _service.addGeofenceList(geofences);
      _lastQueryLat = location.latitude;
      _lastQueryLng = location.longitude;
    } catch (_) {
      // تجاهل.
    }
  }

  static double? _toDouble(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString());
  }
}
