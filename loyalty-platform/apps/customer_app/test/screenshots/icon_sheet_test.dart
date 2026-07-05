@Tags(['screenshot'])
library;

// Verifies the Hatchy SVG icon set renders in the headless test runner and
// emits a contact sheet PNG. Run: flutter test test/screenshots/icon_sheet_test.dart
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';

// A representative subset spanning many glyphs.
const _icons = <IconData>[
  Icons.dashboard_rounded, Icons.qr_code_scanner_rounded, Icons.storefront_rounded,
  Icons.card_giftcard_rounded, Icons.workspace_premium_outlined, Icons.emoji_events_rounded,
  Icons.casino_rounded, Icons.leaderboard_rounded, Icons.insights_rounded,
  Icons.notifications_rounded, Icons.person_outline, Icons.people_alt_rounded,
  Icons.lock_outline, Icons.vpn_key_rounded, Icons.image_outlined, Icons.camera_alt_outlined,
  Icons.edit_outlined, Icons.copy_rounded, Icons.timer_outlined, Icons.calendar_today,
  Icons.settings_outlined, Icons.tune_rounded, Icons.sms_outlined, Icons.email_outlined,
  Icons.map_outlined, Icons.location_on_rounded, Icons.logout_rounded, Icons.inbox_outlined,
  Icons.history_rounded, Icons.delete_outline_rounded, Icons.campaign_outlined, Icons.cake_outlined,
  Icons.egg_alt_rounded, Icons.admin_panel_settings_outlined, Icons.warning_amber_rounded,
  Icons.point_of_sale_rounded, Icons.language_rounded, Icons.search_rounded, Icons.send_rounded,
  Icons.share_rounded, Icons.rocket_launch_rounded, Icons.lightbulb_outline, Icons.local_cafe_outlined,
  Icons.account_balance_wallet_outlined, Icons.confirmation_num_outlined, Icons.star_rounded,
  Icons.check_rounded, Icons.repeat_rounded, Icons.redeem_rounded, Icons.support_agent_outlined,
];

Future<void> _save(WidgetTester tester, Key key, String name) async {
  final boundary =
      tester.renderObject(find.byKey(key)) as RenderRepaintBoundary;
  final image = await boundary.toImage(pixelRatio: 2.0);
  final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
  final dir = Directory('test/screenshots/out')..createSync(recursive: true);
  File('${dir.path}/$name.png').writeAsBytesSync(bytes!.buffer.asUint8List());
}

void main() {
  testWidgets('icon sheet renders (SVG)', (tester) async {
    tester.view.physicalSize = const Size(420, 620);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final key = GlobalKey();
    await tester.pumpWidget(MaterialApp(
      debugShowCheckedModeBanner: false,
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: RepaintBoundary(
          key: key,
          child: Container(
            color: Colors.white,
            padding: const EdgeInsets.all(16),
            child: Wrap(
              spacing: 18,
              runSpacing: 18,
              children: [
                for (final i in _icons)
                  AppIcon(i, size: 30, color: const Color(0xFF1A1A1A)),
              ],
            ),
          ),
        ),
      ),
    ));
    // allow async SVG asset loads to complete
    await tester.pumpAndSettle(const Duration(seconds: 1));
    expect(tester.takeException(), isNull);
    await _save(tester, key, '00_icon_sheet');
  });
}
