import 'package:image_picker/image_picker.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// تسجيل فيديو توثيق للبلاغ ورفعه إلى Supabase Storage (bucket: reports).
class ReportStorage {
  ReportStorage._();

  /// يفتح كاميرا الفيديو مباشرة، يرفع المقطع، ويعيد الرابط (أو null عند الفشل/الإلغاء).
  static Future<String?> recordAndUpload() async {
    try {
      final uid = Supabase.instance.client.auth.currentUser?.id;
      if (uid == null) return null;

      final x = await ImagePicker().pickVideo(
        source: ImageSource.camera,
        maxDuration: const Duration(seconds: 60),
      );
      if (x == null) return null;

      final bytes = await x.readAsBytes();
      final path = '$uid/report_${DateTime.now().millisecondsSinceEpoch}.mp4';
      final client = Supabase.instance.client;
      await client.storage.from('reports').uploadBinary(
            path,
            bytes,
            fileOptions: const FileOptions(contentType: 'video/mp4'),
          );
      return client.storage.from('reports').getPublicUrl(path);
    } catch (_) {
      return null;
    }
  }
}
