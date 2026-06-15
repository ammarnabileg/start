import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:loyalty_core/loyalty_core.dart';

/// نتيجة اختيار الموقع من الخريطة.
class PickedLocation {
  final double lat;
  final double lng;
  const PickedLocation(this.lat, this.lng);
}

/// شاشة اختيار موقع على خريطة OpenStreetMap (بدون مفتاح API).
/// يُحرّك المستخدم الخريطة فيبقى المؤشّر بالمنتصف، وعند التأكيد
/// تُرجع إحداثيات مركز الخريطة عبر [Navigator.pop] كـ [PickedLocation].
class MapPickerScreen extends StatefulWidget {
  final double? initialLat;
  final double? initialLng;

  const MapPickerScreen({super.key, this.initialLat, this.initialLng});

  @override
  State<MapPickerScreen> createState() => _MapPickerScreenState();
}

class _MapPickerScreenState extends State<MapPickerScreen> {
  final MapController _mapController = MapController();

  // مركز افتراضي: الرياض.
  static const LatLng _riyadh = LatLng(24.7136, 46.6753);

  LatLng get _initialCenter {
    if (widget.initialLat != null && widget.initialLng != null) {
      return LatLng(widget.initialLat!, widget.initialLng!);
    }
    return _riyadh;
  }

  void _confirm() {
    final center = _mapController.camera.center;
    Navigator.pop(context, PickedLocation(center.latitude, center.longitude));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('اختيار الموقع')),
      body: Column(
        children: [
          Expanded(
            child: Stack(
              alignment: Alignment.center,
              children: [
                FlutterMap(
                  mapController: _mapController,
                  options: MapOptions(
                    initialCenter: _initialCenter,
                    initialZoom: 14,
                  ),
                  children: [
                    TileLayer(
                      urlTemplate:
                          'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                      userAgentPackageName: 'com.hatchy.merchant_app',
                    ),
                  ],
                ),
                // مؤشّر ثابت في منتصف الخريطة.
                const Align(
                  alignment: Alignment.center,
                  child: Padding(
                    padding: EdgeInsets.only(bottom: 40),
                    child: Icon(
                      Icons.location_on,
                      size: 48,
                      color: AppColors.primaryDark,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: PrimaryButton(
              label: 'تأكيد الموقع',
              icon: Icons.check,
              onPressed: _confirm,
            ),
          ),
        ],
      ),
    );
  }
}
