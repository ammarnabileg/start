import 'package:geolocator/geolocator.dart';
import 'package:network_info_plus/network_info_plus.dart';

/// قراءة حضور: موقع الجهاز + بصمة WiFi (لفرض نطاق الفرع عند المسح).
class PresenceReading {
  final double? lat;
  final double? lng;
  final double? accuracy;
  final String? bssid;
  const PresenceReading({this.lat, this.lng, this.accuracy, this.bssid});
}

/// التقاط الموقع وبصمة WiFi (أفضل جهد — لا يفشل العملية لو الإذن مرفوض).
Future<PresenceReading> capturePresence() async {
  double? lat, lng, accuracy;
  String? bssid;
  try {
    var perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied) {
      perm = await Geolocator.requestPermission();
    }
    final granted = perm == LocationPermission.always ||
        perm == LocationPermission.whileInUse;
    if (granted && await Geolocator.isLocationServiceEnabled()) {
      final pos = await Geolocator.getCurrentPosition(
        locationSettings:
            const LocationSettings(accuracy: LocationAccuracy.high),
      ).timeout(const Duration(seconds: 6));
      lat = pos.latitude;
      lng = pos.longitude;
      accuracy = pos.accuracy;
    }
  } catch (_) {/* تجاهل — أفضل جهد */}
  try {
    bssid = await NetworkInfo().getWifiBSSID();
  } catch (_) {/* iOS قد يمنع — تجاهل */}
  return PresenceReading(lat: lat, lng: lng, accuracy: accuracy, bssid: bssid);
}
